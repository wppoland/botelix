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
     * Neutral container categories a product is tagged with, mapped per scheme
     * through each scheme's 'containers' entry.
     *
     * @return array<string, string>
     */
    public static function containers(): array
    {
        return [
            'plastic'        => __('Single-use plastic bottle', 'botelix'),
            'can'            => __('Metal beverage can', 'botelix'),
            'glass'          => __('Single-use glass bottle', 'botelix'),
            'glass_reusable' => __('Reusable glass bottle', 'botelix'),
        ];
    }

    /**
     * Schemes, rates and VAT treatment as read in each act (see readme).
     * 'containers' maps a neutral category to the scheme's type key; a category
     * missing from the map is not covered by that scheme.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::SCHEME_PL => [
                'name' => __('Poland (System kaucyjny)', 'botelix'),
                'law' => 'Ustawa o gospodarce opakowaniami i odpadami opakowaniowymi (t.j. Dz.U. 2026 poz. 619); Rozp. MKiŚ z 8.07.2024 (Dz.U. 2024 poz. 1046)',
                'currency' => 'PLN',
                // VAT act art. 29a ust. 11a: the deposit is outside the tax base.
                'taxable' => false,
                'default_label' => __('Deposit (kaucja)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,50 zl */
                'default_notice' => __('Plus %s statutory deposit, refunded when you return the packaging.', 'botelix'),
                'containers' => [
                    'plastic' => 'pet',
                    'can' => 'can',
                    'glass_reusable' => 'glass_reusable',
                ],
                'types' => [
                    'pet' => [
                        'label' => __('Single-use plastic bottle up to 3 l', 'botelix'),
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
                'name' => __('Romania (SGR - Sistemul de garanție-returnare)', 'botelix'),
                'law' => 'HG nr. 1074/2021, republicată în M.Of. nr. 1120/21.11.2022, cu modificările ulterioare (ultima HG nr. 659/2025)',
                'currency' => 'RON',
                // Codul fiscal art. 315^5 alin. (2): outside the scope of VAT.
                'taxable' => false,
                'default_label' => __('Garanție SGR', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,50 lei */
                'default_notice' => __('Plus garanție SGR %s, returnabilă la predarea ambalajului.', 'botelix'),
                'containers' => [
                    'plastic' => 'sgr_single_use',
                    'can' => 'sgr_single_use',
                    'glass' => 'sgr_single_use',
                ],
                'types' => [
                    'sgr_single_use' => [
                        'label' => __('Single-use primary packaging 0.1 l to 3 l (glass, plastic, metal)', 'botelix'),
                        'amount' => 0.50,
                    ],
                ],
            ],
            self::SCHEME_AT => [
                'name' => __('Austria (Einwegpfand)', 'botelix'),
                'law' => 'Pfandverordnung für Einweggetränkeverpackungen (BGBl. II Nr. 283/2023)',
                'currency' => 'EUR',
                // The regulation says nothing about VAT. It has the deposit collected
                // in the name and on account of the central body, which reads as a
                // pass-through amount (UStG s. 4(3)). A reading, not a statute: the
                // botelix_scheme_taxable filter flips it.
                'taxable' => false,
                'default_label' => __('Einwegpfand', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,25 EUR */
                'default_notice' => __('Zzgl. %s Einwegpfand, wird bei Rückgabe erstattet.', 'botelix'),
                'containers' => [
                    'plastic' => 'einweg',
                    'can' => 'einweg',
                ],
                'types' => [
                    'einweg' => [
                        'label' => __('Single-use plastic bottle or metal can 0.1 l to 3 l', 'botelix'),
                        'amount' => 0.25,
                    ],
                ],
            ],
            self::SCHEME_DE => [
                'name' => __('Germany (Pfand / DPG)', 'botelix'),
                'law' => '§ 46 VerpackDG',
                'currency' => 'EUR',
                // s. 46(1) VerpackDG: "mindestens 0,25 Euro einschliesslich Umsatzsteuer".
                'taxable' => true,
                'default_label' => __('Pfand', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,25 EUR */
                'default_notice' => __('Zzgl. %s Pfand, wird bei Rückgabe erstattet.', 'botelix'),
                // ponytail: reusable glass has no statutory amount, so the category is
                // unmapped; beer and water Mehrweg stay selectable as DE-only types.
                'containers' => [
                    'plastic' => 'einweg',
                    'glass' => 'einweg',
                    'can' => 'can',
                ],
                'types' => [
                    'einweg' => [
                        'label' => __('Single-use container / Einwegpfand (statutory minimum)', 'botelix'),
                        'amount' => 0.25,
                    ],
                    'can' => [
                        'label' => __('Beverage can / Dose (statutory minimum)', 'botelix'),
                        'amount' => 0.25,
                    ],
                    'mehrweg_bier' => [
                        'label' => __('Reusable beer bottle / Mehrweg Bier (trade practice, not statutory)', 'botelix'),
                        'amount' => 0.08,
                    ],
                    'mehrweg_mineral' => [
                        'label' => __('Reusable mineral water / Mehrweg Mineralwasser (trade practice, not statutory)', 'botelix'),
                        'amount' => 0.15,
                    ],
                ],
            ],
            self::SCHEME_SK => [
                'name' => __('Slovakia (Zálohový systém)', 'botelix'),
                'law' => 'Zákon č. 302/2019 Z. z.; Vyhláška č. 347/2019 Z. z.',
                'currency' => 'EUR',
                // Zakon 222/2004 s. 22(3): the deposit is not part of the tax base.
                'taxable' => false,
                'default_label' => __('Záloh', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,15 EUR */
                'default_notice' => __('Plus %s záloh za obal, vratný pri odovzdaní obalu.', 'botelix'),
                'containers' => [
                    'plastic' => 'zaloh_single_use',
                    'can' => 'zaloh_can',
                ],
                // Vyhlaska 347/2019 s. 2 sets minimums; the administrator sets the rate.
                'types' => [
                    'zaloh_single_use' => [
                        'label' => __('Single-use plastic bottle 0.1 l to 3 l (statutory minimum, the administrator sets the rate)', 'botelix'),
                        'amount' => 0.12,
                    ],
                    'zaloh_can' => [
                        'label' => __('Metal can 0.1 l to 3 l (statutory minimum, the administrator sets the rate)', 'botelix'),
                        'amount' => 0.10,
                    ],
                ],
            ],
            self::SCHEME_HU => [
                'name' => __('Hungary (DRS - Visszaváltási díj)', 'botelix'),
                'law' => '450/2023. (X. 4.) Korm. rendelet',
                'currency' => 'HUF',
                'taxable' => false,
                'default_label' => __('Visszaváltási díj', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 50 Ft */
                'default_notice' => __('Plusz %s visszaváltási díj, visszaváltáskor visszajár.', 'botelix'),
                'containers' => [
                    'plastic' => 'drs_single_use',
                    'can' => 'drs_single_use',
                    'glass' => 'drs_single_use',
                    'glass_reusable' => 'drs_reusable',
                ],
                'types' => [
                    'drs_single_use' => [
                        'label' => __('Single-use beverage container 0.1 l to 3 l (metal, plastic, glass)', 'botelix'),
                        'amount' => 50.0,
                    ],
                    // 3. s. (1): the producer sets the reusable fee; there is no default.
                    'drs_reusable' => [
                        'label' => __('Reusable container (fee set by the producer, enter it here)', 'botelix'),
                        'amount' => 0.0,
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
                'containers' => [
                    'plastic' => 'custom_1',
                    'can' => 'custom_1',
                    'glass' => 'custom_1',
                    'glass_reusable' => 'custom_1',
                ],
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
