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
    public const SCHEME_DK = 'dk';
    public const SCHEME_SE = 'se';
    public const SCHEME_FI = 'fi';
    public const SCHEME_EE = 'ee';
    public const SCHEME_LV = 'lv';
    public const SCHEME_NL = 'nl';
    public const SCHEME_IE = 'ie';
    public const SCHEME_HR = 'hr';
    public const SCHEME_MT = 'mt';
    public const SCHEME_PT = 'pt';
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
            self::SCHEME_DK => [
                'name' => __('Denmark (Pant)', 'botelix'),
                'law' => 'Bekendtgørelse nr. 853 af 28/09/2026 om pantbelagte drikkevareemballager, § 3 stk. 2',
                'currency' => 'DKK',
                // § 3 stk. 2 states every amount "inklusiv moms"; momsloven s. 27(2) no. 2
                // puts packaging charged to the buyer in the tax base.
                'taxable' => true,
                'default_label' => __('Deposit (pant)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 1,00 kr */
                'default_notice' => __('Plus %s deposit (pant), refunded when you return the container.', 'botelix'),
                // § 3 stk. 2: nr. 1 plastic up to 99 cl 1.50; nr. 3 other packaging up to
                // 99 cl 1.00 (refillable glass up to 50 cl included); nr. 2 refillable
                // glass over 50 cl 3.00; nr. 4 anything over 99 cl 3.00. One rate per
                // category, so the common size is the default and the product takes
                // the other amount.
                'rates' => [
                    'plastic' => ['amount' => 1.50, 'label' => __('Plastic bottle up to 99 cl (over 99 cl: 3.00 DKK, set it on the product)', 'botelix')],
                    'can' => ['amount' => 1.00, 'label' => __('Metal can up to 99 cl (over 99 cl: 3.00 DKK, set it on the product)', 'botelix')],
                    'glass' => ['amount' => 1.00, 'label' => __('Single-use glass bottle up to 99 cl (over 99 cl: 3.00 DKK, set it on the product)', 'botelix')],
                    'glass_reusable' => ['amount' => 1.00, 'label' => __('Refillable glass bottle up to 50 cl (over 50 cl: 3.00 DKK, set it on the product)', 'botelix')],
                ],
            ],
            self::SCHEME_SE => [
                'name' => __('Sweden (Pant)', 'botelix'),
                'law' => 'Förordning (2022:1274) om producentansvar för förpackningar, 4 kap. 3 §; pant set by Returpack (Bilaga 3 Pant och avgifter, 2026-04-01)',
                'currency' => 'SEK',
                // Mervärdesskattelag (2023:200) 8 kap. 13 §: packaging charged to the
                // buyer is in the tax base. The pant follows the drink's VAT rate, which
                // the fee takes from the product's tax class.
                'taxable' => true,
                'default_label' => __('Deposit (pant)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 1,00 kr */
                'default_notice' => __('Plus %s deposit (pant), refunded when you return the container.', 'botelix'),
                // The ordinance sets no amount; these are the operator's consumer
                // amounts. Plastic bottles and metal cans only. A pack still marked
                // 1 kr takes its printed amount on the product.
                'rates' => [
                    'plastic' => ['amount' => 2.00, 'label' => __('Plastic bottle under 100 cl, operator rate (over 100 cl: 3.00 SEK, set it on the product)', 'botelix')],
                    'can' => ['amount' => 2.00, 'label' => __('Metal can, operator rate', 'botelix')],
                    'glass' => null,
                    'glass_reusable' => null,
                ],
            ],
            self::SCHEME_FI => [
                'name' => __('Finland (Pantti)', 'botelix'),
                'law' => 'Jätelaki 646/2011, 68-69 §; Valtioneuvoston asetus 526/2013, 2 §',
                'currency' => 'EUR',
                // Arvonlisäverolaki 1501/1993 85 b §: the deposit is taxed; 139 a § allows
                // the drink's own rate, which the fee takes from the product's tax class.
                'taxable' => true,
                'default_label' => __('Deposit (pantti)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,20 EUR */
                'default_notice' => __('Plus %s deposit (pantti), refunded when you return the container.', 'botelix'),
                // Asetus 526/2013 2 § minimums: 0.15 metal; 0.20 plastic over 0.35 l and
                // under 1.0 l; 0.40 plastic 1.0 l or more; 0.10 other packaging. Refillable
                // glass runs its own return system, so 0.10 is only the minimum.
                'rates' => [
                    'plastic' => ['amount' => 0.20, 'label' => __('Plastic bottle over 0.35 l and under 1.0 l (up to 0.35 l: 0.10 EUR, 1.0 l or more: 0.40 EUR, set it on the product)', 'botelix')],
                    'can' => ['amount' => 0.15, 'label' => __('Metal can', 'botelix')],
                    'glass' => ['amount' => 0.10, 'label' => __('Single-use glass bottle', 'botelix')],
                    'glass_reusable' => ['amount' => 0.10, 'label' => __('Refillable glass bottle (statutory minimum, enter the rate of its own return system)', 'botelix')],
                ],
            ],
            self::SCHEME_EE => [
                'name' => __('Estonia (Pandipakend)', 'botelix'),
                'law' => 'Pakendiseadus § 21; Keskkonnaministri määrus nr 19 (23.03.2005) § 1',
                'currency' => 'EUR',
                // Käibemaksuseadus § 12 lg 12: the deposit is not part of the taxable value.
                'taxable' => false,
                'default_label' => __('Deposit (tagatisraha)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,10 EUR */
                'default_notice' => __('Plus %s deposit (tagatisraha), refunded when you return the container.', 'botelix'),
                // Määrus nr 19 § 1 lg 1 p 1-6: 0.10 for every category; PakS § 21 lg 4^1
                // p 3: none under 0.1 l or over 3.0 l.
                'rates' => [
                    'plastic' => ['amount' => 0.10, 'label' => $plastic],
                    'can' => ['amount' => 0.10, 'label' => $can],
                    'glass' => ['amount' => 0.10, 'label' => $glass],
                    'glass_reusable' => ['amount' => 0.10, 'label' => __('Reusable glass bottle 0.1 l to 3 l', 'botelix')],
                ],
            ],
            self::SCHEME_LV => [
                'name' => __('Latvia (Depozīta sistēma)', 'botelix'),
                'law' => 'MK noteikumi Nr. 519 (11.08.2020), point 7 and Annex 1, as amended by MK 589/2022 and MK 809/2025',
                'currency' => 'EUR',
                // PVN likums art. 39(3)(3) keeps the reusable deposit out of the taxable
                // amount; for single-use packs only VID guidance (28.01.2022) says so.
                // A reading, not a statute, for single-use: the botelix_scheme_taxable
                // filter flips it.
                'taxable' => false,
                'default_label' => __('Deposit (depozīta maksa)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,10 EUR */
                'default_notice' => __('Plus %s deposit (depozīta maksa), refunded when you return the container.', 'botelix'),
                // Point 7: 0.10 per unit; point 2 covers single-use and reusable packs;
                // Annex 1: PET, cans and glass 0.1 l to under 3 l. Wine, sparkling,
                // fruit, mulled and aromatised wine, bitters and liqueurs are out of glass.
                'rates' => [
                    'plastic' => ['amount' => 0.10, 'label' => __('PET bottle 0.1 l to under 3 l', 'botelix')],
                    'can' => ['amount' => 0.10, 'label' => __('Metal can 0.1 l to under 3 l', 'botelix')],
                    'glass' => ['amount' => 0.10, 'label' => __('Single-use glass bottle 0.1 l to under 3 l (not wine, bitters or liqueurs)', 'botelix')],
                    'glass_reusable' => ['amount' => 0.10, 'label' => __('Reusable glass bottle 0.1 l to under 3 l (not wine, bitters or liqueurs)', 'botelix')],
                ],
            ],
            self::SCHEME_NL => [
                'name' => __('Netherlands (Statiegeld)', 'botelix'),
                'law' => 'Besluit beheer verpakkingen 2014, art. 12-14; Regeling beheer verpakkingen, art. 6',
                'currency' => 'EUR',
                // Besluit maatstaf van heffing omzetbelasting (2025-23004) par. 4.4: by
                // approval, not statute, statiegeld is not part of the vergoeding. The
                // botelix_scheme_taxable filter flips it.
                'taxable' => false,
                'default_label' => __('Deposit (statiegeld)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,15 EUR */
                'default_notice' => __('Plus %s deposit (statiegeld), refunded when you return the container.', 'botelix'),
                // Regeling art. 6(1): plastic bottle under 1 l 0.15, 1 l to 3 l 0.25,
                // water and frisdrank only (Besluit art. 12(1)); art. 6(2): metal
                // container up to 3 l 0.15, any drink. Statutory minimums. Glass is out.
                'rates' => [
                    'plastic' => ['amount' => 0.15, 'label' => __('Plastic bottle under 1 l, water or soft drink (1 l to 3 l: 0.25 EUR, set it on the product)', 'botelix')],
                    'can' => ['amount' => 0.15, 'label' => __('Metal can up to 3 l', 'botelix')],
                    'glass' => null,
                    'glass_reusable' => null,
                ],
            ],
            self::SCHEME_IE => [
                'name' => __('Ireland (Deposit Return Scheme)', 'botelix'),
                'law' => 'S.I. No. 33 of 2024, reg. 18 and First Schedule (as substituted by S.I. No. 555 of 2024)',
                'currency' => 'EUR',
                // VAT Consolidation Act 2010 s. 92A(2): the taxable amount referable to
                // the deposit is deemed reduced to nil.
                'taxable' => false,
                'default_label' => __('Deposit', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,15 EUR */
                'default_notice' => __('Plus %s deposit, refunded when you return the container.', 'botelix'),
                // First Schedule: 0.15 up to 0.5 l, 0.25 over 0.5 l; reg. 3(1): PET
                // bottles and aluminium or steel containers of 0.150 l to 3 l. Glass is out.
                'rates' => [
                    'plastic' => ['amount' => 0.15, 'label' => __('PET bottle 0.15 l to 0.5 l (over 0.5 l: 0.25 EUR, set it on the product)', 'botelix')],
                    'can' => ['amount' => 0.15, 'label' => __('Aluminium or steel can 0.15 l to 0.5 l (over 0.5 l: 0.25 EUR, set it on the product)', 'botelix')],
                    'glass' => null,
                    'glass_reusable' => null,
                ],
            ],
            self::SCHEME_HR => [
                'name' => __('Croatia (Povratna naknada)', 'botelix'),
                'law' => 'Uredba o naknadi gospodarenja otpadom i povratnoj naknadi (NN 137/2024), čl. 4 and čl. 10(3)',
                'currency' => 'EUR',
                // Porezna uprava guidance "Povratna naknada" (3.10.2025): collected on
                // behalf of the Fund, a pass-through item outside the base. Guidance,
                // not a statute: the botelix_scheme_taxable filter flips it.
                'taxable' => false,
                'default_label' => __('Deposit (povratna naknada)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,10 EUR */
                'default_notice' => __('Plus %s deposit (povratna naknada), refunded when you return the container.', 'botelix'),
                // Čl. 4(1)-(2): 0.10 per single-use plastic, glass or metal pack up to
                // 3 l; čl. 10(3): until 31.12.2026 only PET, glass or metal of 0.2 l to 3 l.
                // Reusable packaging is not in the scheme.
                'rates' => [
                    'plastic' => ['amount' => 0.10, 'label' => __('PET bottle 0.2 l to 3 l (until 31.12.2026; from 2027 any single-use plastic up to 3 l)', 'botelix')],
                    'can' => ['amount' => 0.10, 'label' => __('Metal can 0.2 l to 3 l (until 31.12.2026; from 2027 up to 3 l)', 'botelix')],
                    'glass' => ['amount' => 0.10, 'label' => __('Single-use glass bottle 0.2 l to 3 l (until 31.12.2026; from 2027 up to 3 l)', 'botelix')],
                    'glass_reusable' => null,
                ],
            ],
            self::SCHEME_MT => [
                'name' => __('Malta (Beverage Container Refund Scheme)', 'botelix'),
                'law' => 'Beverage Containers Recycling Regulations (S.L. 549.134), reg. 15, reg. 21 and Third Schedule',
                'currency' => 'EUR',
                // Reg. 15(1) and 21(1): "The deposit value shall not be subject to VAT".
                'taxable' => false,
                'default_label' => __('Deposit (depożitu)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,10 EUR */
                'default_notice' => __('Plus %s deposit (depożitu), refunded when you return the container.', 'botelix'),
                // Third Schedule: at least 0.10; Second Schedule: steel, aluminium, glass
                // and PET of 0.1 l to 3 l. Reg. 21 covers refillables of any of these.
                'rates' => [
                    'plastic' => ['amount' => 0.10, 'label' => __('PET bottle 0.1 l to 3 l (statutory minimum)', 'botelix')],
                    'can' => ['amount' => 0.10, 'label' => __('Steel or aluminium can 0.1 l to 3 l (statutory minimum)', 'botelix')],
                    'glass' => ['amount' => 0.10, 'label' => __('Single-use glass bottle 0.1 l to 3 l (statutory minimum)', 'botelix')],
                    'glass_reusable' => ['amount' => 0.10, 'label' => __('Refillable container 0.1 l to 3 l (statutory minimum, the producer system sets the value)', 'botelix')],
                ],
            ],
            self::SCHEME_PT => [
                'name' => __('Portugal (Sistema de Depósito e Reembolso)', 'botelix'),
                'law' => 'Decreto-Lei n.º 152-D/2017, art. 30.º-B and 30.º-E; Despacho n.º 432/2026, point 1',
                'currency' => 'EUR',
                // Art. 30.º-E(3): the deposit value "não está sujeito a tributação".
                'taxable' => false,
                'default_label' => __('Deposit (depósito)', 'botelix'),
                /* translators: %s: formatted deposit amount, e.g. 0,10 EUR */
                'default_notice' => __('Plus %s deposit (depósito), refunded when you return the container.', 'botelix'),
                // Despacho 432/2026 point 1: 0.10 whatever the volume or material; art.
                // 30.º-B: single-use plastic, ferrous metal and aluminium under 3 l.
                // Glass is out.
                'rates' => [
                    'plastic' => ['amount' => 0.10, 'label' => __('Single-use plastic bottle under 3 l', 'botelix')],
                    'can' => ['amount' => 0.10, 'label' => __('Metal can under 3 l', 'botelix')],
                    'glass' => null,
                    'glass_reusable' => null,
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
