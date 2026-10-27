<?php

declare(strict_types=1);

namespace Botelix\Admin;

use Botelix\Contract\HasHooks;
use Botelix\Model\Scheme;
use Botelix\Service\DepositService;

defined('ABSPATH') || exit;

/**
 * Settings admin interface for Botelix DRS.
 */
final class SettingsPage implements HasHooks
{
    public const PAGE_SLUG = 'botelix-settings';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Botelix Deposit Return', 'botelix'),
            __('Deposit Return (DRS)', 'botelix'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::PAGE_SLUG, DepositService::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
            'default' => [
                'enabled' => '1',
                'default_scheme' => Scheme::SCHEME_PL,
                'enable_destination_matching' => '1',
                'exempt_unsupported_exports' => '1',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];

        $sanitized['enabled'] = ! empty($input['enabled']) ? '1' : '0';
        $sanitized['default_scheme'] = sanitize_key((string) ($input['default_scheme'] ?? Scheme::SCHEME_PL));
        $sanitized['enable_destination_matching'] = ! empty($input['enable_destination_matching']) ? '1' : '0';
        $sanitized['exempt_unsupported_exports'] = ! empty($input['exempt_unsupported_exports']) ? '1' : '0';

        foreach (Scheme::all() as $schemeId => $scheme) {
            $labelKey = 'fee_label_' . $schemeId;
            if (isset($input[$labelKey])) {
                $sanitized[$labelKey] = sanitize_text_field((string) $input[$labelKey]);
            }

            $noticeKey = 'product_notice_' . $schemeId;
            if (isset($input[$noticeKey])) {
                $sanitized[$noticeKey] = sanitize_text_field((string) $input[$noticeKey]);
            }

            if (isset($scheme['types']) && is_array($scheme['types'])) {
                foreach (array_keys($scheme['types']) as $typeKey) {
                    $amountKey = 'amount_' . $schemeId . '_' . $typeKey;
                    if (isset($input[$amountKey]) && $input[$amountKey] !== '') {
                        $sanitized[$amountKey] = max(0.0, (float) $input[$amountKey]);
                    }
                }
            }
        }

        return $sanitized;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $raw      = get_option(DepositService::OPTION, []);
        $settings = is_array($raw) ? $raw : [];
        ?>
        <div class="wrap botelix-settings-wrap">
            <h1><?php echo esc_html__('Botelix - Multi-Country Deposit Return Scheme (DRS)', 'botelix'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Configure statutory container deposit schemes for European ecommerce (Pfand, SGR, Kaucja, Zaloh, Visszavaltasi dij).', 'botelix'); ?>
            </p>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::PAGE_SLUG);
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Deposit Scheme', 'botelix'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(DepositService::OPTION); ?>[enabled]" value="1" <?php checked(! empty($settings['enabled'])); ?> />
                                <?php echo esc_html__('Activate container deposit calculation and notices', 'botelix'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default Scheme', 'botelix'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(DepositService::OPTION); ?>[default_scheme]">
                                <?php foreach (Scheme::all() as $id => $scheme) : ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected(($settings['default_scheme'] ?? Scheme::SCHEME_PL), $id); ?>>
                                        <?php echo esc_html($scheme['name'] . ' (' . $scheme['law'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Default statutory scheme applied when destination matching is off or country is domestic.', 'botelix'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Cross-Border Destination Matching', 'botelix'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(DepositService::OPTION); ?>[enable_destination_matching]" value="1" <?php checked(! empty($settings['enable_destination_matching'])); ?> />
                                <?php echo esc_html__('Automatically apply the destination country scheme (PL, RO, AT, DE, SK, HU) based on shipping address', 'botelix'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Export Exemption', 'botelix'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(DepositService::OPTION); ?>[exempt_unsupported_exports]" value="1" <?php checked(! empty($settings['exempt_unsupported_exports'])); ?> />
                                <?php echo esc_html__('Do not charge container deposits when shipping outside covered deposit jurisdictions', 'botelix'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Statutory Schemes & Default Amounts', 'botelix'); ?></h2>
                <div class="botelix-schemes-accordion">
                    <?php foreach (Scheme::all() as $schemeId => $scheme) : ?>
                        <div class="botelix-scheme-card" style="background:#fff; border:1px solid #ccd0d4; padding:15px; margin-bottom:15px; border-radius:4px;">
                            <h3><?php echo esc_html($scheme['name']); ?></h3>
                            <p><strong><?php echo esc_html__('Legal basis:', 'botelix'); ?></strong> <code><?php echo esc_html($scheme['law']); ?></code></p>
                            <p><strong><?php echo esc_html__('Tax status:', 'botelix'); ?></strong> <?php echo ! empty($scheme['taxable']) ? esc_html__('Taxable fee (included in / subject to VAT)', 'botelix') : esc_html__('Non-taxable fee (outside VAT base)', 'botelix'); ?></p>

                            <table class="form-table" style="margin-top:0;">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Fee Label', 'botelix'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" name="<?php echo esc_attr(DepositService::OPTION); ?>[fee_label_<?php echo esc_attr($schemeId); ?>]" value="<?php echo esc_attr($settings['fee_label_' . $schemeId] ?? $scheme['default_label']); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Product Notice Template', 'botelix'); ?></th>
                                    <td>
                                        <input type="text" class="large-text" name="<?php echo esc_attr(DepositService::OPTION); ?>[product_notice_<?php echo esc_attr($schemeId); ?>]" value="<?php echo esc_attr($settings['product_notice_' . $schemeId] ?? $scheme['default_notice']); ?>" />
                                        <p class="description"><?php
                                            /* translators: %s: placeholder token for formatted deposit amount */
                                            echo esc_html__('Use {amount} or %s for formatted deposit amount.', 'botelix');
                                        ?></p>
                                    </td>
                                </tr>
                                <?php if (! empty($scheme['types']) && is_array($scheme['types'])) : ?>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Statutory Rates', 'botelix'); ?></th>
                                        <td>
                                            <?php foreach ($scheme['types'] as $typeKey => $typeData) :
                                                $amountVal = $settings['amount_' . $schemeId . '_' . $typeKey] ?? $typeData['amount'];
                                                ?>
                                                <div style="margin-bottom:8px;">
                                                    <label style="display:inline-block; min-width:320px;">
                                                        <?php echo esc_html($typeData['label']); ?>:
                                                    </label>
                                                    <input type="number" step="0.01" min="0" style="width:100px;" name="<?php echo esc_attr(DepositService::OPTION); ?>[amount_<?php echo esc_attr($schemeId . '_' . $typeKey); ?>]" value="<?php echo esc_attr((string) $amountVal); ?>" />
                                                    <span><?php echo esc_html($scheme['currency']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
