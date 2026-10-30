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
     * Settings in effect before the settings screen is ever saved.
     */
    public const DEFAULTS = [
        'enabled' => '1',
        'default_scheme' => Scheme::SCHEME_PL,
        'enable_destination_matching' => '1',
        'exempt_unsupported_exports' => '1',
    ];

    /**
     * Scheme-specific type keys saved before 1.1.0, read as a neutral container
     * category when the active scheme has no type of that name.
     */
    private const LEGACY_CONTAINERS = [
        'pet' => 'plastic',
        'einweg' => 'plastic',
        'sgr_single_use' => 'plastic',
        'zaloh_single_use' => 'plastic',
        'drs_single_use' => 'plastic',
        'custom_1' => 'plastic',
        'mehrweg_bier' => 'glass_reusable',
        'mehrweg_mineral' => 'glass_reusable',
        'drs_reusable' => 'glass_reusable',
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
    }

    /**
     * Container options for the product editor: the neutral categories plus the
     * default scheme's own types that no category maps to (e.g. DE Mehrweg).
     *
     * @return array<string, string>
     */
    public function getPackagingTypeOptions(?string $schemeId = null): array
    {
        $schemeId = $schemeId ?: (string) $this->settings['default_scheme'];
        $scheme   = Scheme::get($schemeId);

        $options = ['' => __('None (not covered by deposit scheme)', 'botelix')] + Scheme::containers();

        if ($scheme !== null) {
            $mapped = array_values((array) ($scheme['containers'] ?? []));
            foreach ((array) $scheme['types'] as $key => $typeData) {
                if (! in_array($key, $mapped, true)) {
                    $options[$key] = (string) ($typeData['label'] ?? $key);
                }
            }
        }

        return $options;
    }

    /**
     * Value to preselect for a stored container meta, so a pre-1.1.0 key is shown
     * as its category and a plain save keeps the deposit.
     */
    public function containerOptionValue(string $stored): string
    {
        if (isset($this->getPackagingTypeOptions()[$stored])) {
            return $stored;
        }

        return self::LEGACY_CONTAINERS[$stored] ?? '';
    }

    /**
     * The scheme type key a stored container value means under a scheme, or null
     * when that scheme does not cover it.
     */
    public function typeKeyFor(string $stored, string $schemeId): ?string
    {
        $scheme = Scheme::get($schemeId);
        if ($scheme === null || $stored === '') {
            return null;
        }

        $map = (array) ($scheme['containers'] ?? []);

        if (isset($map[$stored])) {
            return (string) $map[$stored];
        }

        if (isset($scheme['types'][$stored])) {
            return $stored;
        }

        $category = self::LEGACY_CONTAINERS[$stored] ?? null;

        return $category !== null && isset($map[$category]) ? (string) $map[$category] : null;
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
     * Amount for a scheme type key under a scheme, merchant override first.
     */
    public function amountForType(string $type, ?string $schemeId = null): float
    {
        $schemeId = $schemeId ?: $this->resolver->resolveScheme();
        if ($schemeId === null) {
            return 0.0;
        }

        $scheme = Scheme::get($schemeId);
        if ($scheme === null || ! isset($scheme['types'][$type])) {
            return 0.0;
        }

        $configured = $this->settings['amount_' . $schemeId . '_' . $type] ?? null;

        if ($configured !== null && $configured !== '') {
            return max(0.0, (float) $configured);
        }

        return (float) ($scheme['types'][$type]['amount'] ?? 0.0);
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
     * @return array{0: ?string, 1: float}
     */
    private function itemDeposit(\WC_Product $product, ?string $schemeId = null): array
    {
        $resolved = $schemeId ?: $this->resolver->resolveScheme();
        if ($resolved === null) {
            return [null, 0.0];
        }

        $source = $product;

        // A variation without its own container inherits the parent's settings.
        if ((string) $product->get_meta(self::META_TYPE, true) === '' && $product->get_parent_id() > 0) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent instanceof \WC_Product) {
                $source = $parent;
            }
        }

        $stored   = (string) $source->get_meta(self::META_TYPE, true);
        $units    = $source->get_meta(self::META_UNITS, true);
        $override = (string) $source->get_meta(self::META_SCHEME, true);
        $active   = $override !== '' ? $override : $resolved;

        if ($stored === '' || ! $this->isChargeable($active)) {
            return [$active, 0.0];
        }

        $type = $this->typeKeyFor($stored, $active);
        if ($type === null) {
            return [$active, 0.0];
        }

        return [$active, $this->amountForType($type, $active) * max(1, (int) $units)];
    }

    /**
     * Add the deposit as cart fees, one per scheme and tax class.
     *
     * Where the act states the deposit including VAT (DE), the fee is the net
     * share of the gross deposit in the product's tax class, so WooCommerce's
     * fee tax brings it back to exactly the statutory amount. Elsewhere the
     * deposit sits outside the VAT base and is added untaxed.
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

            $class = $this->isTaxable($active) && $product->is_taxable() ? $product->get_tax_class() : 'untaxed';
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

    private function isTaxable(string $schemeId): bool
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
