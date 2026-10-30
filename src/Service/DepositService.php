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

    private SchemeResolver $resolver;

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct()
    {
        $raw            = get_option(self::OPTION, []);
        $this->settings = is_array($raw) ? $raw : [];
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
        add_action('woocommerce_single_product_summary', [$this, 'renderProductNotice'], 12);
    }

    /**
     * Packaging types configured for a given scheme.
     *
     * @return array<string, string>
     */
    public function getPackagingTypeOptions(?string $schemeId = null): array
    {
        $schemeId = $schemeId ?: (string) ($this->settings['default_scheme'] ?? Scheme::SCHEME_PL);
        $scheme   = Scheme::get($schemeId);

        $options = ['' => __('None (not covered by deposit scheme)', 'botelix')];

        if ($scheme && isset($scheme['types']) && is_array($scheme['types'])) {
            foreach ($scheme['types'] as $key => $typeData) {
                $options[$key] = (string) ($typeData['label'] ?? $key);
            }
        }

        return $options;
    }

    /**
     * Statutory amount for packaging type under a scheme.
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

        // Check if merchant set a custom amount in settings.
        $overrideKey = 'amount_' . $schemeId . '_' . $type;
        $configured  = $this->settings[$overrideKey] ?? null;

        if ($configured !== null && $configured !== '') {
            return max(0.0, (float) $configured);
        }

        return (float) ($scheme['types'][$type]['amount'] ?? 0.0);
    }

    /**
     * Calculate deposit carried by a single product/variation item.
     */
    public function depositFor(\WC_Product $product, ?string $schemeId = null): float
    {
        $type = (string) $product->get_meta(self::META_TYPE, true);
        $units = $product->get_meta(self::META_UNITS, true);
        $overrideScheme = (string) $product->get_meta(self::META_SCHEME, true);

        // Fall back to parent for variations if empty.
        if ($type === '' && $product->get_parent_id() > 0) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent instanceof \WC_Product) {
                $type           = (string) $parent->get_meta(self::META_TYPE, true);
                $units          = $parent->get_meta(self::META_UNITS, true);
                $overrideScheme = (string) $parent->get_meta(self::META_SCHEME, true);
            }
        }

        if ($type === '') {
            return 0.0;
        }

        $activeScheme = ! empty($overrideScheme) ? $overrideScheme : ($schemeId ?: $this->resolver->resolveScheme());
        if ($activeScheme === null) {
            return 0.0;
        }

        $amount = $this->amountForType($type, $activeScheme);
        if ($amount <= 0.0) {
            return 0.0;
        }

        $unitCount = max(1, (int) $units);

        return $amount * $unitCount;
    }

    /**
     * Add deposit as a cart fee during calculation.
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

        $scheme = Scheme::get($schemeId);
        if ($scheme === null) {
            return;
        }

        $totalDeposit = 0.0;

        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (! $product instanceof \WC_Product) {
                continue;
            }

            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
            $totalDeposit += $this->depositFor($product, $schemeId) * $quantity;
        }

        if ($totalDeposit <= 0.0) {
            return;
        }

        $label    = $this->feeLabel($scheme);
        $taxable  = (bool) ($scheme['taxable'] ?? false);
        $decimals = wc_get_price_decimals();

        // PL, RO, SK, HU: deposit sits outside the VAT base at checkout ($taxable = false).
        // AT, DE: Pfand is inclusive of VAT / taxable ($taxable = true).
        $cart->add_fee($label, round($totalDeposit, $decimals), $taxable);
    }

    /**
     * Render the statutory deposit notice on the product page.
     */
    public function renderProductNotice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $schemeId = $this->resolver->resolveScheme();
        if ($schemeId === null) {
            return;
        }

        $scheme = Scheme::get($schemeId);
        if ($scheme === null) {
            return;
        }

        $deposit = $this->depositFor($product, $schemeId);
        if ($deposit <= 0.0) {
            return;
        }

        $amountFormatted = wc_price($deposit);
        $customNotice    = trim((string) ($this->settings['product_notice_' . $schemeId] ?? ''));

        if ($customNotice !== '') {
            $text = str_replace('{amount}', $amountFormatted, $customNotice);
        } else {
            /* translators: %s: formatted deposit amount */
            $template = (string) ($scheme['default_notice'] ?? __('Plus %s deposit.', 'botelix'));
            $text     = sprintf($template, $amountFormatted);
        }

        printf('<p class="botelix-deposit-notice">%s</p>', wp_kses_post($text));
    }

    /**
     * @param array<string, mixed> $scheme
     */
    private function feeLabel(array $scheme): string
    {
        $schemeId = (string) ($this->resolver->resolveScheme() ?: Scheme::SCHEME_PL);
        $custom   = trim((string) ($this->settings['fee_label_' . $schemeId] ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($scheme['default_label'] ?? __('Deposit', 'botelix'));
    }
}
