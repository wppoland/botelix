# Botelix - Deposit Return Scheme (Pfand, SGR, Kaucja, Zaloh)

Multi-country statutory deposit return scheme (DRS / Pfand / SGR / Kaucja / Zaloh / Visszavaltasi dij) compliance for WooCommerce stores.

## Supported Schemes & Legal Basis

- **Poland (System kaucyjny)**: Rozporzadzenie MKiS z 8.07.2024 (Dz.U. 2024 poz. 1046) Section 1: 0.50 PLN plastic bottle up to 3 l, 0.50 PLN can up to 1 l, 1.00 PLN reusable glass up to 1.5 l. Outside the VAT base (VAT Act Article 29a(11a)).
- **Romania (SGR)**: HG nr. 1074/2021 (republished M.Of. 1120/2022, as amended) Article 12(5): 0.50 RON per single-use plastic, metal or glass container. Outside the scope of VAT (Codul fiscal Article 315^5(2)).
- **Austria (Einwegpfand)**: Pfandverordnung fuer Einweggetraenkeverpackungen (BGBl. II Nr. 283/2023) Section 4: 0.25 EUR per single-use plastic bottle or metal can. The regulation states no VAT; added untaxed, `botelix_scheme_taxable` filter flips it.
- **Germany (Pfand / DPG)**: Section 46(1) VerpackDG: at least 0.25 EUR including VAT per single-use plastic, metal or glass container; reusable glass starts at the customary 0.15 EUR (trade practice, not statutory). Added as the net share per tax class so the customer pays exactly the gross amount.
- **Slovakia (Zalohovy system)**: Vyhlaska c. 347/2019 Z. z. Section 2: minimums 0.12 EUR plastic, 0.10 EUR can; the administrator sets the rate (Zakon c. 302/2019 Z. z. Section 7(1)(g)). Outside the VAT base (Zakon c. 222/2004 Z. z. Section 22(3)).
- **Hungary (DRS)**: 450/2023. (X. 4.) Korm. rendelet Section 3(1): 50 HUF per single-use plastic, metal or glass container; the producer sets the reusable fee. Added untaxed.
- **Custom Scheme**: a rate per container category and a label in the store currency for any other jurisdiction, added untaxed.

Each product carries one container category: single-use plastic bottle, metal can, single-use glass bottle or reusable glass bottle. A scheme charges its rate for that category, or nothing where it does not cover it. A scheme is only charged when its currency equals the store currency; Botelix does not convert.

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
