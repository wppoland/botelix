<?php

declare(strict_types=1);

namespace Botelix\Service;

use Botelix\Model\Scheme;

defined('ABSPATH') || exit;

/**
 * Resolves the applicable DRS scheme based on shop settings and customer destination.
 */
final class SchemeResolver
{
    private const COUNTRY_MAP = [
        'PL' => Scheme::SCHEME_PL,
        'RO' => Scheme::SCHEME_RO,
        'AT' => Scheme::SCHEME_AT,
        'DE' => Scheme::SCHEME_DE,
        'SK' => Scheme::SCHEME_SK,
        'HU' => Scheme::SCHEME_HU,
    ];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private array $settings)
    {
    }

    /**
     * Determine active scheme ID for the given country code or current context.
     */
    public function resolveScheme(?string $country = null): ?string
    {
        $defaultScheme = (string) ($this->settings['default_scheme'] ?? Scheme::SCHEME_PL);
        $multiCountry  = ! empty($this->settings['enable_destination_matching']);

        if (! $multiCountry) {
            return $defaultScheme;
        }

        if ($country === null || $country === '') {
            $country = $this->detectCustomerCountry();
        }

        if ($country === null || $country === '') {
            return $defaultScheme;
        }

        $country = strtoupper(trim($country));

        if (isset(self::COUNTRY_MAP[$country])) {
            return self::COUNTRY_MAP[$country];
        }

        // Export outside supported schemes: check whether export exemption is enabled.
        $exemptExports = ! empty($this->settings['exempt_unsupported_exports']);
        if ($exemptExports) {
            return null;
        }

        return $defaultScheme;
    }

    /**
     * Detect shipping country from current WooCommerce session.
     */
    private function detectCustomerCountry(): ?string
    {
        if (! function_exists('WC') || WC()->customer === null) {
            return null;
        }

        $shipping = WC()->customer->get_shipping_country();
        if (! empty($shipping)) {
            return (string) $shipping;
        }

        $billing = WC()->customer->get_billing_country();
        if (! empty($billing)) {
            return (string) $billing;
        }

        return null;
    }
}
