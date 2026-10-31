<?php

declare(strict_types=1);

namespace Botelix\Service;

use Botelix\Contract\HasHooks;
use Botelix\Model\Scheme;

defined('ABSPATH') || exit;

/**
 * Core calculation and hook orchestration for statutory container deposits.
 */
final class DepositService implements HasHooks
{
    public const OPTION = 'botelix_settings';

    public const META_TYPE   = '_botelix_packaging_type';
    public const META_UNITS  = '_botelix_packaging_units';
    public const META_SCHEME = '_botelix_scheme_override';

    /**
     * Order item meta: the deposit snapshot taken when the order was placed.
     */
    public const ITEM_META = '_botelix_deposit';

    /**
     * Settings in effect before the settings screen is ever saved.
     */
    public const DEFAULTS = [
        'enabled' => '1',
        'default_scheme' => Scheme::SCHEME_PL,
        'enable_destination_matching' => '1',
        'exempt_unsupported_exports' => '1',
    ];

    private SchemeResolver $resolver;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    /**
     * Product ids whose notice was already printed in this request.
     *
     * @var array<int, true>
     */
    private array $noticePrinted = [];

    public function __construct()
    {
        $raw            = get_option(self::OPTION, []);
        $this->settings = array_merge(self::DEFAULTS, is_array($raw) ? $raw : []);
        $this->resolver = new SchemeResolver($this->settings);
    }

    public function isEnabled(): bool
    {
        return ! empty($this->settings['enabled']);
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_cart_calculate_fees', [$this, 'applyCartFee']);
        // Classic template, and block templates through WooCommerce's compatibility layer.
        add_action('woocommerce_single_product_summary', [$this, 'renderProductNotice'], 12);
        // Block templates: right after the main product's Product Price block.
        add_filter('render_block_woocommerce/product-price', [$this, 'appendNoticeToPriceBlock'], 10, 3);
        // Every other price: shop, category, search, related products, block grids, Store API.
        add_filter('woocommerce_get_price_html', [$this, 'filterPriceHtml'], 10, 2);
        // Deposit per line: classic and block cart and checkout.
        add_filter('woocommerce_get_item_data', [$this, 'cartItemData'], 10, 2);
        // Snapshot on the order item: order view, admin, emails show the visible meta.
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'snapshotOrderItem'], 10, 4);
    }

    /**
     * Container options for the product editor. Every category is offered; the
     * ones the given scheme (the product's override, else the store default)
     * does not cover say so.
     *
     * @return array<string, string>
     */
    public function getPackagingTypeOptions(?string $schemeId = null): array
    {
        $scheme  = Scheme::get($schemeId ?: (string) $this->settings['default_scheme']);
        $options = ['' => __('None (not covered by deposit scheme)', 'botelix')];

        foreach (Scheme::containers() as $category => $label) {
            $options[$category] = $scheme === null || isset($scheme['rates'][$category])
                ? $label
                /* translators: 1: container category, 2: scheme name */
                : sprintf(__('%1$s - not covered by %2$s', 'botelix'), $label, (string) $scheme['name']);
        }

        return $options;
    }

    /**
     * The product's scheme override; a variation, which has no override field of
     * its own, takes its parent's.
     */
    public function schemeOverride(\WC_Product $product): string
    {
        $override = (string) $product->get_meta(self::META_SCHEME, true);

        if ($override === '' && $product->get_parent_id() > 0) {
            $parent   = wc_get_product($product->get_parent_id());
            $override = $parent instanceof \WC_Product ? (string) $parent->get_meta(self::META_SCHEME, true) : '';
        }

        return $override;
    }

    /**
     * A scheme is charged only in its own currency: rates are statutory numbers
     * in that currency and Botelix does not convert. The custom scheme has none.
     */
    public function isChargeable(string $schemeId): bool
    {
        $scheme = Scheme::get($schemeId);
        if ($scheme === null) {
            return false;
        }

        $currency = (string) $scheme['currency'];

        return $currency === '' || $currency === $this->storeCurrency();
    }

    /**
     * Schemes this store may apply (default scheme, plus every national scheme
     * under destination matching) that cannot be charged in the store currency.
     *
     * @return array<string, string> scheme id => scheme currency
     */
    public function unchargeableSchemes(): array
    {
        $candidates = [(string) $this->settings['default_scheme']];

        if (! empty($this->settings['enable_destination_matching'])) {
            $candidates = array_merge($candidates, array_values(SchemeResolver::COUNTRY_MAP));
        }

        $out = [];
        foreach (array_unique($candidates) as $schemeId) {
            if (! $this->isChargeable($schemeId)) {
                $out[$schemeId] = (string) (Scheme::get($schemeId)['currency'] ?? '');
            }
        }

        return $out;
    }

    public function storeCurrency(): string
    {
        return function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';
    }

    /**
     * Amount for a container category under a scheme, merchant override first;
     * 0 where the scheme does not cover the category.
     */
    public function amountFor(string $category, ?string $schemeId = null): float
    {
        $schemeId = $schemeId ?: $this->resolver->resolveScheme();
        if ($schemeId === null) {
            return 0.0;
        }

        $rate = Scheme::get($schemeId)['rates'][$category] ?? null;
        if ($rate === null) {
            return 0.0;
        }

        $configured = $this->settings['amount_' . $schemeId . '_' . $category] ?? null;

        if ($configured !== null && $configured !== '') {
            return max(0.0, (float) $configured);
        }

        return (float) $rate['amount'];
    }

    /**
     * Deposit carried by one item of the product, units included.
     */
    public function depositFor(\WC_Product $product, ?string $schemeId = null): float
    {
        return $this->itemDeposit($product, $schemeId)[1];
    }

    /**
     * Scheme that applies to the product (its override wins over the resolved
     * scheme) and the deposit for one item. A scheme that cannot be charged in
     * the store currency gives 0.
     *
     * @return array{0: ?string, 1: float, 2: string} scheme, deposit, container category
     */
    private function itemDeposit(\WC_Product $product, ?string $schemeId = null): array
    {
        $resolved = $schemeId ?: $this->resolver->resolveScheme();
        if ($resolved === null) {
            return [null, 0.0, ''];
        }

        $source = $product;

        // A variation without its own container inherits the parent's settings.
        if ((string) $product->get_meta(self::META_TYPE, true) === '' && $product->get_parent_id() > 0) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent instanceof \WC_Product) {
                $source = $parent;
            }
        }

        $category = (string) $source->get_meta(self::META_TYPE, true);
        $units    = $source->get_meta(self::META_UNITS, true);
        $override = $this->schemeOverride($source);
        $active   = $override !== '' ? $override : $resolved;

        if ($category === '' || ! $this->isChargeable($active)) {
            return [$active, 0.0, $category];
        }

        return [$active, $this->amountFor($category, $active) * max(1, (int) $units), $category];
    }

    /**
     * EINWEG or MEHRWEG for a container under the German scheme, '' elsewhere.
     * VerpackDG s. 47(1) and (2) prescribe these German words, so they are not
     * translated. Over 3.0 l and s. 46(4) no. 7 drinks need no MEHRWEG notice;
     * Botelix has no volume data, so the merchant's container tag decides.
     */
    public function packagingLabel(?string $schemeId, string $category): string
    {
        if ($schemeId !== Scheme::SCHEME_DE || $category === '') {
            return '';
        }

        return $category === 'glass_reusable' ? 'MEHRWEG' : 'EINWEG';
    }

    /**
     * Add the packaging label and the deposit to a price. Both sit inside the
     * price markup, so the label takes the price's font and size (VerpackDG
     * s. 47(4)) and the deposit stands next to the price (PAngV s. 7). The main
     * product of a single product page already gets the notice under its price.
     */
    public function filterPriceHtml(string $html, \WC_Product $product): string
    {
        if ($html === '' || (is_admin() && ! wp_doing_ajax())) {
            return $html;
        }

        [$schemeId, , $category] = $this->itemDeposit($product);

        $label = $this->packagingLabel($schemeId, $category);
        if ($label !== '') {
            $html .= ' <span class="botelix-packaging-type">' . esc_html($label) . '</span>';
        }

        $main = is_product() && in_array(get_queried_object_id(), [$product->get_id(), $product->get_parent_id()], true);
        $text = $main ? '' : $this->noticeText($product);

        if ($text !== '') {
            $html .= ' <small class="botelix-deposit-notice">' . wp_kses_post($text) . '</small>';
        }

        return $html;
    }

    /**
     * Deposit row under a cart line.
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed>             $cartItem
     * @return array<int, array<string, mixed>>
     */
    public function cartItemData(array $data, array $cartItem): array
    {
        $line = $this->lineDeposit($cartItem['data'] ?? null, (int) ($cartItem['quantity'] ?? 1));

        if ($line !== null) {
            $data[] = ['key' => $line['name'], 'value' => $line['text']];
        }

        return $data;
    }

    /**
     * Store the line's deposit on the order item: a hidden snapshot for
     * returns and accounting, and a visible row WooCommerce prints on the
     * order page, in the admin and in every order email.
     *
     * @param array<string, mixed> $values Cart item.
     */
    public function snapshotOrderItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        $line = $this->lineDeposit($values['data'] ?? null, (int) ($values['quantity'] ?? 1), $order->get_currency());
        if ($line === null) {
            return;
        }

        $item->add_meta_data(self::ITEM_META, array_diff_key($line, ['name' => 0, 'text' => 0]), true);
        $item->add_meta_data($line['name'], $line['text'], true);
    }

    /**
     * Deposit of a cart line, or null when it carries none.
     *
     * @return array{scheme: string, category: string, packaging: string, unit: float, quantity: int, total: float, name: string, text: string}|null
     */
    private function lineDeposit(mixed $product, int $quantity, string $currency = ''): ?array
    {
        if (! $product instanceof \WC_Product) {
            return null;
        }

        [$schemeId, $unit, $category] = $this->itemDeposit($product);
        if ($schemeId === null || $unit <= 0.0) {
            return null;
        }

        $quantity  = max(1, $quantity);
        $total     = $unit * $quantity;
        $args      = $currency !== '' ? ['currency' => $currency] : [];
        $packaging = $this->packagingLabel($schemeId, $category);
        $text      = $this->plainPrice($total, $args);

        if ($quantity > 1) {
            /* translators: 1: line deposit total, 2: deposit per item */
            $text = sprintf(__('%1$s (%2$s per item)', 'botelix'), $text, $this->plainPrice($unit, $args));
        }

        if ($packaging !== '') {
            $text = $packaging . ', ' . $text;
        }

        return [
            'scheme' => $schemeId,
            'category' => $category,
            'packaging' => $packaging,
            'unit' => $unit,
            'quantity' => $quantity,
            'total' => $total,
            'name' => $this->feeLabel($schemeId),
            'text' => $text,
        ];
    }

    /**
     * @param array<string, string> $args wc_price() arguments.
     */
    private function plainPrice(float $amount, array $args): string
    {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount, $args)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Add the deposit as cart fees, one per scheme and tax class.
     *
     * Where the act states the deposit including VAT (DE), the fee is the net
     * share of the gross deposit in the product's tax class, so WooCommerce's
     * fee tax brings it back to exactly the statutory amount. A VAT-exempt
     * customer, a non-taxable product or a store without taxes gets the gross
     * untaxed. Elsewhere the deposit sits outside the VAT base and is added untaxed.
     */
    public function applyCartFee(\WC_Cart $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        $schemeId = $this->resolver->resolveScheme();
        if ($schemeId === null) {
            return;
        }

        // WooCommerce charges a VAT-exempt customer no tax, so the gross goes in untaxed.
        $customer = $cart->get_customer();
        $exempt   = $customer instanceof \WC_Customer && $customer->get_is_vat_exempt();

        // Gross deposit per "scheme|tax class"; class 'untaxed' carries no tax.
        $groups = [];

        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (! $product instanceof \WC_Product) {
                continue;
            }

            [$active, $deposit] = $this->itemDeposit($product, $schemeId);
            if ($active === null || $deposit <= 0.0) {
                continue;
            }

            $class = $this->isTaxable($active) && $product->is_taxable() && ! $exempt ? $product->get_tax_class() : 'untaxed';
            $key   = $active . '|' . $class;

            $groups[$key] = ($groups[$key] ?? 0.0) + $deposit * max(1, (int) ($cartItem['quantity'] ?? 1));
        }

        foreach ($groups as $key => $gross) {
            [$active, $class] = explode('|', $key, 2);

            $gross = round($gross, wc_get_price_decimals());
            $label = $this->feeLabel($active);
            $id    = 'botelix-deposit-' . $active . '-' . ($class === '' ? 'standard' : $class);

            if ($class === 'untaxed') {
                $cart->fees_api()->add_fee([
                    'id' => $id,
                    'name' => $label,
                    'amount' => $gross,
                    'taxable' => false,
                ]);
                continue;
            }

            $rates = \WC_Tax::get_rates($class, $cart->get_customer());
            $net   = $gross - array_sum(\WC_Tax::calc_tax($gross, $rates, true));

            if (count($groups) > 1) {
                $label .= sprintf(' (%s%%)', wc_format_localized_decimal((string) array_sum(array_column($rates, 'rate'))));
            }

            $cart->fees_api()->add_fee([
                'id' => $id,
                'name' => $label,
                'amount' => $net,
                'taxable' => true,
                'tax_class' => $class,
            ]);
        }
    }

    public function isTaxable(string $schemeId): bool
    {
        $flag = (bool) (Scheme::get($schemeId)['taxable'] ?? false);

        /**
         * Whether a scheme's deposit is stated including VAT and so part of the tax base.
         *
         * @param bool   $flag     Taxability from the scheme data.
         * @param string $schemeId Scheme id, e.g. 'de' or 'at'.
         */
        return wc_tax_enabled() && (bool) apply_filters('botelix_scheme_taxable', $flag, $schemeId);
    }

    /**
     * Render the deposit notice on a classic single product page.
     */
    public function renderProductNotice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        echo wp_kses_post($this->noticeHtml($product));
    }

    /**
     * Append the notice to the main product's price on block templates. A price
     * block inside a product grid renders another product id and is skipped.
     *
     * @param string               $content  Rendered block.
     * @param array<string, mixed> $block    Parsed block.
     * @param \WP_Block|null       $instance Block instance carrying the postId context.
     */
    public function appendNoticeToPriceBlock(string $content, array $block, ?\WP_Block $instance = null): string
    {
        $productId = (int) ($instance->context['postId'] ?? 0);

        if (! is_product() || $productId === 0 || $productId !== get_queried_object_id()) {
            return $content;
        }

        $product = wc_get_product($productId);

        return $product instanceof \WC_Product ? $content . wp_kses_post($this->noticeHtml($product)) : $content;
    }

    /**
     * Notice markup for a product, once per request; '' when it carries no deposit.
     */
    public function noticeHtml(\WC_Product $product): string
    {
        if (isset($this->noticePrinted[$product->get_id()])) {
            return '';
        }

        $text = $this->noticeText($product);
        if ($text === '') {
            return '';
        }

        $this->noticePrinted[$product->get_id()] = true;

        return sprintf('<p class="botelix-deposit-notice">%s</p>', $text);
    }

    /**
     * Notice text with the amount filled in. A template may use {amount} or %s.
     */
    public function noticeText(\WC_Product $product): string
    {
        [$schemeId, $deposit] = $this->itemDeposit($product);

        if ($schemeId === null || $deposit <= 0.0) {
            return '';
        }

        $template = trim((string) ($this->settings['product_notice_' . $schemeId] ?? ''));

        if ($template === '') {
            $template = (string) (Scheme::get($schemeId)['default_notice'] ?? '');
        }

        return str_replace(['{amount}', '%s'], wc_price($deposit), $template);
    }

    private function feeLabel(string $schemeId): string
    {
        $custom = trim((string) ($this->settings['fee_label_' . $schemeId] ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        return (string) (Scheme::get($schemeId)['default_label'] ?? __('Deposit', 'botelix'));
    }
}
