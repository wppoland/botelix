# Botelix - Deposit Return Scheme (Pfand, SGR, Kaucja, Zaloh)

Multi-country statutory deposit return scheme (DRS / Pfand / SGR / Kaucja / Zaloh / Visszavaltasi dij) compliance for WooCommerce stores.

## Supported Schemes & Legal Basis

- **Poland (System kaucyjny)**: Rozporzadzenie MKiS z 8.07.2024 (Dz.U. 2024 poz. 1046) Section 1: 0.50 PLN plastic bottle up to 3 l, 0.50 PLN can up to 1 l, 1.00 PLN reusable glass up to 1.5 l. Outside the VAT base (VAT Act Article 29a(11a)).
- **Romania (SGR)**: HG nr. 1074/2021 (republished M.Of. 1120/2022, as amended) Article 12(5): 0.50 RON per single-use plastic, metal or glass container. Outside the scope of VAT (Codul fiscal Article 315^5(2)).
- **Austria (Einwegpfand)**: Pfandverordnung fuer Einweggetraenkeverpackungen (BGBl. II Nr. 283/2023) Section 4: 0.25 EUR per single-use plastic bottle or metal can. The regulation states no VAT; added untaxed, `botelix_scheme_taxable` filter flips it.
- **Germany (Pfand / DPG)**: Section 46(1) VerpackDG: at least 0.25 EUR including VAT per single-use plastic, metal or glass container; reusable glass starts at the customary 0.15 EUR (trade practice, not statutory). Added as the net share per tax class so the customer pays exactly the gross amount.
- **Slovakia (Zalohovy system)**: Vyhlaska c. 347/2019 Z. z. Section 2: minimums 0.12 EUR plastic, 0.10 EUR can; the administrator sets the rate (Zakon c. 302/2019 Z. z. Section 7(1)(g)). Outside the VAT base (Zakon c. 222/2004 Z. z. Section 22(3)).
- **Hungary (DRS)**: 450/2023. (X. 4.) Korm. rendelet Section 3(1): 50 HUF per single-use plastic, metal or glass container; the producer sets the reusable fee. Added untaxed.
- **Denmark (Pant)**: BEK nr. 853 of 28.09.2026 Section 3(2): 1.50 DKK plastic up to 99 cl, 1.00 DKK other containers up to 99 cl (refillable glass up to 50 cl), 3.00 DKK above. Includes VAT; added as the net share per tax class.
- **Sweden (Pant)**: Forordning (2022:1274) chapter 4 Section 3 leaves the amount to the operator: 2 SEK can and plastic bottle under 100 cl, 3 SEK over 100 cl (Returpack Bilaga 3, 1 April 2026). In the VAT base at the drink's rate.
- **Finland (Pantti)**: VNa 526/2013 Section 2 minimums: 0.15 EUR can, 0.20/0.40 EUR plastic (over 0.35 l and under 1 l / 1 l and up), 0.10 EUR other. Taxed (ALV Section 85 b at 25.5 percent; Section 139 a allows the drink's rate where the two cannot be separated without considerable difficulty, which is what Botelix applies).
- **Estonia (Pandipakend)**: Maarus nr 19 Section 1(1): 0.10 EUR every category. Outside the VAT base (KMS Section 12(12)).
- **Latvia (Depozita sistema)**: MK Regulation No. 519 point 7 and Annex 1: 0.10 EUR PET, can, single-use and reusable glass. Untaxed (PVN art. 39(3)(3) for reusable, VID guidance for single-use).
- **Netherlands (Statiegeld)**: Regeling beheer verpakkingen Article 6: 0.15 EUR plastic under 1 l (0.25 EUR 1 l to 3 l), 0.15 EUR can. Untaxed by administrative approval.
- **Ireland (DRS)**: S.I. 33/2024 First Schedule: 0.15 EUR up to 0.5 l, 0.25 EUR above, PET and cans. Untaxed (VATCA 2010 Section 92A(2)).
- **Croatia (Povratna naknada)**: Uredba NN 137/2024 Article 4: 0.10 EUR single-use plastic, metal, glass. Untaxed per tax authority guidance.
- **Malta (BCRS)**: S.L. 549.134 Third Schedule: at least 0.10 EUR per container, single-use and refillable. Not subject to VAT (regulation 15(1)).
- **Portugal (SDR)**: Despacho 432/2026: 0.10 EUR per plastic bottle or can. Not taxed (DL 152-D/2017 Article 30-E(3)).
- **Custom Scheme**: a rate per container category and a label in the store currency for any other jurisdiction, added untaxed.

Each product carries one container category: single-use plastic bottle, metal can, single-use glass bottle or reusable glass bottle. A scheme charges its rate for that category, or nothing where it does not cover it. A deposit per container entered on the product or variation replaces that rate under the product's own scheme (its override, else the store default). A scheme is only charged when its currency equals the store currency; Botelix does not convert.

## Requirements

- PHP >= 8.1
- WordPress >= 6.5
- WooCommerce >= 8.0

## Testing

Run self-contained test suite:
```bash
for t in tests/*.php; do php "$t"; done
```

## License

GPL-2.0-or-later
