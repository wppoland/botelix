<?php

declare(strict_types=1);

namespace Botelix\Model;

defined('ABSPATH') || exit;

/**
 * Statutory DRS schemes and default rules.
 */
final class Scheme
{
    public const SCHEME_PL = 'pl';
    public const SCHEME_RO = 'ro';
    public const SCHEME_AT = 'at';
    public const SCHEME_DE = 'de';
    public const SCHEME_SK = 'sk';
    public const SCHEME_HU = 'hu';
    public const SCHEME_CUSTOM = 'custom';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::SCHEME_PL => [
                'name' => __('Poland (System kaucyjny)', 'botelix'),
                'law' => 'Ustawa z dnia 13 lipca 2023 r. (Dz.U. 2023 poz. 1852); Dz.U. 2024 poz. 1045',
                'currency' => 'PLN',
                'taxable' => false,
                'default_label' => __('Deposit (kaucja)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,50 zl */
                'default_notice' => __('Plus %s statutory deposit, refunded when you return the packaging.', 'botelix'),
                'types' => [
                    'pet' => [
                        'label' => __('Single-use PET bottle up to 3 l', 'botelix'),
                        'amount' => 0.50,
                    ],
                    'can' => [
                        'label' => __('Metal beverage can up to 1 l', 'botelix'),
                        'amount' => 0.50,
                    ],
                    'glass_reusable' => [
                        'label' => __('Reusable glass bottle up to 1.5 l', 'botelix'),
                        'amount' => 1.00,
                    ],
                ],
            ],
            self::SCHEME_RO => [
                'name' => __('Romania (SGR - Sistemul de Garantie-Returnare)', 'botelix'),
                'law' => 'Hotararea Guvernului nr. 1074/2021 (M.Of. 985/2021)',
                'currency' => 'RON',
                'taxable' => false,
                'default_label' => __('Garantie SGR', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,50 lei */
                'default_notice' => __('Inclusiv garantie SGR %s, returnabila la predarea ambalajului.', 'botelix'),
                'types' => [
                    'sgr_single_use' => [
                        'label' => __('Single-use primary packaging 0.1 l to 3 l (glass, plastic, metal)', 'botelix'),
                        'amount' => 0.50,
                    ],
                ],
            ],
            self::SCHEME_AT => [
                'name' => __('Austria (Einwegpfand)', 'botelix'),
                'law' => 'AWG 2002; Einwegpfand-Verordnung (BGBl. II Nr. 283/2023)',
                'currency' => 'EUR',
                'taxable' => true,
                'default_label' => __('Einwegpfand', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,25 EUR */
                'default_notice' => __('Zzgl. %s Einwegpfand (inkl. USt.), wird bei Ruckgabe erstattet.', 'botelix'),
                'types' => [
                    'einweg' => [
                        'label' => __('Single-use plastic bottle or metal can 0.1 l to 3 l', 'botelix'),
                        'amount' => 0.25,
                    ],
                ],
            ],
            self::SCHEME_DE => [
                'name' => __('Germany (Pfand / DPG)', 'botelix'),
                'law' => 's. 46 VerpackDG (formerly s. 31 VerpackG)',
                'currency' => 'EUR',
                'taxable' => true,
                'default_label' => __('Pfand', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,25 EUR */
                'default_notice' => __('Zzgl. %s Pfand, wird bei Ruckgabe erstattet.', 'botelix'),
                'types' => [
                    'einweg' => [
                        'label' => __('Single-use container / Einwegpfand (statutory minimum)', 'botelix'),
                        'amount' => 0.25,
                    ],
                    'can' => [
                        'label' => __('Beverage can / Dose', 'botelix'),
                        'amount' => 0.25,
                    ],
                    'mehrweg_bier' => [
                        'label' => __('Reusable beer bottle / Mehrweg Bier', 'botelix'),
                        'amount' => 0.08,
                    ],
                    'mehrweg_mineral' => [
                        'label' => __('Reusable mineral water / Mehrweg Mineralwasser', 'botelix'),
                        'amount' => 0.15,
                    ],
                ],
            ],
            self::SCHEME_SK => [
                'name' => __('Slovakia (Zalohovy system)', 'botelix'),
                'law' => 'Zakon c. 302/2019 Z. z.; Vyhlaska c. 347/2019 Z. z.',
                'currency' => 'EUR',
                'taxable' => false,
                'default_label' => __('Zaloh', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,15 EUR */
                'default_notice' => __('Plus %s zaloh za obal, vratny pri odovzdani obalu.', 'botelix'),
                'types' => [
                    'zaloh_single_use' => [
                        'label' => __('Single-use plastic bottle or metal can 0.1 l to 3 l', 'botelix'),
                        'amount' => 0.15,
                    ],
                ],
            ],
            self::SCHEME_HU => [
                'name' => __('Hungary (DRS - Visszavaltasi dij)', 'botelix'),
                'law' => '2023. evi LXXX. torveny; 450/2023. (X. 4.) Korm. rendelet',
                'currency' => 'HUF',
                'taxable' => false,
                'default_label' => __('Visszavaltasi dij', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 50 Ft */
                'default_notice' => __('Plusz %s visszavaltasi dij, visszavaltaskor visszajar.', 'botelix'),
                'types' => [
                    'drs_single_use' => [
                        'label' => __('Single-use beverage container 0.1 l to 3 l (metal, plastic, glass)', 'botelix'),
                        'amount' => 50.0,
                    ],
                    'drs_reusable' => [
                        'label' => __('Reusable container (manufacturer rate)', 'botelix'),
                        'amount' => 25.0,
                    ],
                ],
            ],
            self::SCHEME_CUSTOM => [
                'name' => __('Custom deposit scheme', 'botelix'),
                'law' => 'Store-configured deposit scheme',
                'currency' => '',
                'taxable' => false,
                'default_label' => __('Deposit', 'botelix'),
                /* translators: %s: formatted deposit amount */
                'default_notice' => __('Plus %s deposit, refunded upon return.', 'botelix'),
                'types' => [
                    'custom_1' => [
                        'label' => __('Standard container deposit', 'botelix'),
                        'amount' => 0.25,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $schemeId): ?array
    {
        return self::all()[$schemeId] ?? null;
    }
}
