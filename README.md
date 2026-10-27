# Botelix - Deposit Return Scheme (Pfand, SGR, Kaucja, Zaloh)

Multi-country statutory deposit return scheme (DRS / Pfand / SGR / Kaucja / Zaloh / Visszavaltasi dij) compliance for WooCommerce stores.

## Supported Schemes & Legal Basis

- **Poland (System kaucyjny)**: Ustawa z dnia 13 lipca 2023 r. (Dz.U. 2023 poz. 1852); Rozporzadzenie MKiS (Dz.U. 2024 poz. 1045) Section 1. Non-taxable (Ustawa o VAT Article 29a(11)).
- **Romania (SGR)**: Hotararea Guvernului nr. 1074/2021 Article 3(1)(a). Non-taxable (Codul Fiscal Article 286(4)(c)).
- **Austria (Einwegpfand)**: AWG 2002; Einwegpfand-Verordnung (BGBl. II Nr. 283/2023) Section 4. Taxable / VAT inclusive.
- **Germany (Pfand / DPG)**: Section 46 VerpackDG (formerly Section 31 VerpackG). Taxable / VAT inclusive.
- **Slovakia (Zaloh)**: Zakon c. 302/2019 Z. z.; Vyhlaska c. 347/2019 Z. z. Section 1. Non-taxable (Zakon c. 222/2004 Z. z. Section 22(3)).
- **Hungary (DRS)**: 2023. evi LXXX. torveny; 450/2023. (X. 4.) Korm. rendelet Section 4(1). Non-taxable (Afa tv. Section 71(1)(b)).
- **Custom Scheme**: Configurable rates, labels, and taxability for any other jurisdiction.

## Requirements

- PHP >= 8.1
- WordPress >= 6.5
- WooCommerce >= 8.0

## Testing

Run self-contained test suite:
```bash
php tests/deposit-calculation-check.php
```

## License

GPL-2.0-or-later
