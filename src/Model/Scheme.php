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
     * Neutral container categories. A product stores one of these keys; each
     * scheme gives a rate for it in 'rates', or null where it is not covered.
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
     * Schemes, rates and VAT treatment as read in each act (see readme). 'rates'
     * holds every container category: null when the scheme does not cover it,
     * else the default amount in the scheme currency and its settings label.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $plastic = __('Single-use plastic bottle 0.1 l to 3 l', 'botelix');
        $can     = __('Metal can 0.1 l to 3 l', 'botelix');
        $glass   = __('Single-use glass bottle 0.1 l to 3 l', 'botelix');

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
                // Annex 1a to the packaging act: single-use glass is not covered.
                'rates' => [
                    'plastic' => ['amount' => 0.50, 'label' => __('Single-use plastic bottle up to 3 l', 'botelix')],
                    'can' => ['amount' => 0.50, 'label' => __('Metal beverage can up to 1 l', 'botelix')],
                    'glass' => null,
                    'glass_reusable' => ['amount' => 1.00, 'label' => __('Reusable glass bottle up to 1.5 l', 'botelix')],
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
                // Art. 12(5): single-use glass, plastic and metal; reusable not covered here.
                'rates' => [
                    'plastic' => ['amount' => 0.50, 'label' => $plastic],
                    'can' => ['amount' => 0.50, 'label' => $can],
                    'glass' => ['amount' => 0.50, 'label' => $glass],
                    'glass_reusable' => null,
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
                // s. 4: single-use plastic bottles and metal cans only.
                'rates' => [
                    'plastic' => ['amount' => 0.25, 'label' => $plastic],
                    'can' => ['amount' => 0.25, 'label' => $can],
                    'glass' => null,
                    'glass_reusable' => null,
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
                // s. 46(1) covers every single-use material. Reusable glass has no
                // statutory amount: 0.15 EUR is the customary Mehrweg water bottle
                // rate (beer is usually 0.08), editable in the settings.
                'rates' => [
                    'plastic' => ['amount' => 0.25, 'label' => __('Single-use plastic bottle (statutory minimum)', 'botelix')],
                    'can' => ['amount' => 0.25, 'label' => __('Beverage can / Dose (statutory minimum)', 'botelix')],
                    'glass' => ['amount' => 0.25, 'label' => __('Single-use glass bottle (statutory minimum)', 'botelix')],
                    'glass_reusable' => ['amount' => 0.15, 'label' => __('Reusable glass bottle / Mehrweg (trade practice, not statutory)', 'botelix')],
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
                // Vyhlaska 347/2019 s. 2 sets minimums for plastic and cans only; the
                // administrator sets the rate.
                'rates' => [
                    'plastic' => ['amount' => 0.12, 'label' => __('Single-use plastic bottle 0.1 l to 3 l (statutory minimum, the administrator sets the rate)', 'botelix')],
                    'can' => ['amount' => 0.10, 'label' => __('Metal can 0.1 l to 3 l (statutory minimum, the administrator sets the rate)', 'botelix')],
                    'glass' => null,
                    'glass_reusable' => null,
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
                // 3. s. (1): 50 HUF for single-use metal, plastic and glass; the
                // producer sets the reusable fee, so it starts at 0.
                'rates' => [
                    'plastic' => ['amount' => 50.0, 'label' => $plastic],
                    'can' => ['amount' => 50.0, 'label' => $can],
                    'glass' => ['amount' => 50.0, 'label' => $glass],
                    'glass_reusable' => ['amount' => 0.0, 'label' => __('Reusable glass bottle (fee set by the producer, enter it here)', 'botelix')],
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
                'rates' => array_map(
                    static fn (string $label): array => ['amount' => 0.25, 'label' => $label],
                    self::containers()
                ),
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
