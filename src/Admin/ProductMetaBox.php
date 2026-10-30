<?php

declare(strict_types=1);

namespace Botelix\Admin;

use Botelix\Contract\HasHooks;
use Botelix\Model\Scheme;
use Botelix\Service\DepositService;

defined('ABSPATH') || exit;

/**
 * Product edit screen fields for statutory packaging deposits.
 */
final class ProductMetaBox implements HasHooks
{
    public function __construct(private DepositService $depositService)
    {
    }

    public function registerHooks(): void
    {
        if (! $this->depositService->isEnabled()) {
            return;
        }

        add_action('woocommerce_product_options_general_product_data', [$this, 'renderProductFields']);
        add_action('woocommerce_process_product_meta', [$this, 'saveProductFields']);

        // Variations support
        add_action('woocommerce_variation_options_pricing', [$this, 'renderVariationFields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'saveVariationFields'], 10, 2);
    }

    public function renderProductFields(): void
    {
        global $thepostid;

        $stored   = (string) get_post_meta((int) $thepostid, DepositService::META_TYPE, true);
        $override = (string) get_post_meta((int) $thepostid, DepositService::META_SCHEME, true);

        wp_nonce_field('botelix_save_product_fields', 'botelix_product_nonce');

        echo '<div class="options_group botelix-options-group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Deposit Return Scheme (DRS / Pfand / Kaucja)', 'botelix') . '</h4>';

        $schemeOptions = ['' => __('Store default scheme', 'botelix')];
        foreach (Scheme::all() as $id => $scheme) {
            $schemeOptions[$id] = (string) $scheme['name'];
        }

        woocommerce_wp_select([
            'id' => DepositService::META_SCHEME,
            'label' => __('Scheme override', 'botelix'),
            'description' => __('Optionally lock this product to a specific national scheme, or leave as default.', 'botelix'),
            'desc_tip' => true,
            'options' => $schemeOptions,
        ]);

        woocommerce_wp_select([
            'id' => DepositService::META_TYPE,
            'label' => __('Packaging container', 'botelix'),
            'description' => __('Select the container type. Each national scheme charges its own rate for it, or nothing where it is not covered.', 'botelix'),
            'desc_tip' => true,
            'value' => $stored,
            'options' => $this->depositService->getPackagingTypeOptions($override),
        ]);

        woocommerce_wp_text_input([
            'id' => DepositService::META_UNITS,
            'label' => __('Container units', 'botelix'),
            'description' => __('Number of covered containers in this item (e.g. 6 for a 6-pack). Defaults to 1.', 'botelix'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['min' => '1', 'step' => '1'],
        ]);

        echo '</div>';
    }

    public function saveProductFields(int $productId): void
    {
        if (
            ! isset($_POST['botelix_product_nonce']) ||
            ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['botelix_product_nonce'])), 'botelix_save_product_fields')
        ) {
            return;
        }

        if (! current_user_can('edit_post', $productId)) {
            return;
        }

        $product = wc_get_product($productId);
        if (! $product instanceof \WC_Product) {
            return;
        }

        $scheme = isset($_POST[DepositService::META_SCHEME])
            ? sanitize_key(wp_unslash($_POST[DepositService::META_SCHEME]))
            : '';
        $type = isset($_POST[DepositService::META_TYPE])
            ? $this->category(sanitize_key(wp_unslash($_POST[DepositService::META_TYPE])))
            : '';
        $units = isset($_POST[DepositService::META_UNITS])
            ? max(1, absint(wp_unslash($_POST[DepositService::META_UNITS])))
            : 1;

        $product->update_meta_data(DepositService::META_SCHEME, $scheme);
        $product->update_meta_data(DepositService::META_TYPE, $type);
        $product->update_meta_data(DepositService::META_UNITS, (string) $units);
        $product->save_meta_data();
    }

    /**
     * @param int $loop
     * @param array<string, mixed> $variationData
     * @param \WP_Post $variation
     */
    public function renderVariationFields(int $loop, array $variationData, \WP_Post $variation): void
    {
        $product = wc_get_product($variation->ID);
        if (! $product instanceof \WC_Product) {
            return;
        }

        $currentType  = (string) $product->get_meta(DepositService::META_TYPE, true);
        $currentUnits = (string) $product->get_meta(DepositService::META_UNITS, true);
        $override     = $this->depositService->schemeOverride($product);

        echo '<div class="botelix-variation-fields" style="padding: 10px 0;">';

        woocommerce_wp_select([
            'id' => DepositService::META_TYPE . '[' . $loop . ']',
            'label' => __('Packaging container', 'botelix'),
            'value' => $currentType,
            'desc_tip' => true,
            'description' => __('Leave empty to inherit the parent packaging container setting.', 'botelix'),
            'options' => $this->depositService->getPackagingTypeOptions($override),
            'wrapper_class' => 'form-row form-row-first',
        ]);

        woocommerce_wp_text_input([
            'id' => DepositService::META_UNITS . '[' . $loop . ']',
            'label' => __('Container units', 'botelix'),
            'value' => $currentUnits ?: '1',
            'desc_tip' => true,
            'description' => __('Containers in this variation.', 'botelix'),
            'type' => 'number',
            'custom_attributes' => ['min' => '1', 'step' => '1'],
            'wrapper_class' => 'form-row form-row-last',
        ]);

        echo '</div>';
    }

    public function saveVariationFields(int $variationId, int $i): void
    {
        $nonceValid = false;
        if (
            isset($_POST['botelix_product_nonce']) &&
            wp_verify_nonce(sanitize_key(wp_unslash($_POST['botelix_product_nonce'])), 'botelix_save_product_fields')
        ) {
            $nonceValid = true;
        } elseif (
            isset($_POST['security']) &&
            wp_verify_nonce(sanitize_key(wp_unslash($_POST['security'])), 'save-variations')
        ) {
            $nonceValid = true;
        }

        if (! $nonceValid) {
            return;
        }

        if (! current_user_can('edit_post', $variationId)) {
            return;
        }

        $product = wc_get_product($variationId);
        if (! $product instanceof \WC_Product) {
            return;
        }

        $type = '';
        if (isset($_POST[DepositService::META_TYPE][$i])) {
            $type = $this->category(sanitize_key(wp_unslash($_POST[DepositService::META_TYPE][$i])));
        }

        $units = 1;
        if (isset($_POST[DepositService::META_UNITS][$i])) {
            $units = max(1, absint(wp_unslash($_POST[DepositService::META_UNITS][$i])));
        }

        $product->update_meta_data(DepositService::META_TYPE, $type);
        $product->update_meta_data(DepositService::META_UNITS, (string) $units);
        $product->save_meta_data();
    }

    /**
     * A posted container value, kept only when it is one of the categories.
     */
    private function category(string $value): string
    {
        return isset(Scheme::containers()[$value]) ? $value : '';
    }
}
