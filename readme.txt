=== Botelix - Deposit Return Scheme (Pfand, SGR, Kaucja, Zaloh) ===
Contributors: motylanogha
Tags: woocommerce, deposit return, pfand, sgr, kaucja
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Statutory container deposit scheme (DRS) calculations and checkout fees for European ecommerce (Pfand, SGR, Kaucja, Zaloh, Einwegpfand).

== Description ==

Across the European Union, national deposit return schemes (DRS) require retailers selling beverages in covered packaging to collect a statutory container deposit at checkout, display the amount clearly on product pages, and refund it when empty packaging is returned. Under Article 44 of the EU Packaging and Packaging Waste Regulation (PPWR), all Member States must operate deposit systems for single-use plastic bottles and metal cans.

Botelix gives WooCommerce stores a single, lightweight tool to comply with deposit legislation across European markets:

* **Germany (Pfand)**: Section 46 VerpackDG (formerly Section 31 VerpackG) mandates a statutory minimum deposit of 0.25 EUR (including VAT) on single-use beverage packaging (Einweggetraenkeverpackungen 0.1 l to 3.0 l). Customary trade rates for reusable containers (Mehrweg 0.08 EUR beer, 0.15 EUR mineral water) are supported.
* **Romania (SGR)**: Hotararea Guvernului nr. 1074/2021 (amended by HG nr. 1075/2023) Article 3(1)(a) mandates a guarantee of 0.50 RON per container for single-use glass, plastic, or metal packaging (0.1 l to 3.0 l). Under Codul Fiscal Article 286(4)(c), the guarantee is outside the VAT base.
* **Poland (System kaucyjny)**: Ustawa z dnia 13 lipca 2023 r. (Dz.U. 2023 poz. 1852) Annex 1a and Rozporzadzenie Ministra Klimatu i Srodowiska z dnia 8 lipca 2024 r. (Dz.U. 2024 poz. 1045) Section 1 set statutory deposits of 0.50 PLN for single-use PET (up to 3 l) and metal cans (up to 1 l), and 1.00 PLN for reusable glass (up to 1.5 l). Under Polish VAT Act Article 29a(11), deposits are non-taxable at retail sale.
* **Austria (Einwegpfand)**: AWG 2002 and Einwegpfand-Verordnung (BGBl. II Nr. 283/2023) Section 4 mandate a deposit of 0.25 EUR (including VAT) on single-use plastic bottles and cans from 0.1 l to 3.0 l.
* **Slovakia (Zalohovy system)**: Zakon c. 302/2019 Z. z. and Vyhlaska c. 347/2019 Z. z. Section 1 set a statutory deposit of 0.15 EUR (outside VAT base under Zakon c. 222/2004 Z. z. Section 22(3)) for single-use cans and plastic bottles.
* **Hungary (DRS)**: 2023. evi LXXX. torveny and 450/2023. (X. 4.) Korm. rendelet Section 4(1) mandate a 50 HUF statutory fee for single-use beverage packaging (outside VAT base under Afa tv. Section 71(1)(b)).
* **Custom Scheme**: Configure custom container types, rates, and tax flags for any other national or regional deposit scheme.

Key capabilities:

* **Automatic Cart Fee**: Adds the deposit as a dedicated fee at cart and checkout using native WooCommerce fees.
* **Tax Base Handling**: Correctly marks deposits as non-taxable (PL, RO, SK, HU) or taxable / VAT-inclusive (AT, DE) based on statutory tax law.
* **Multi-Pack Multiplier**: Multiplies deposit by container count (a 6-pack of cans automatically charges 6 container deposits).
* **Cross-Border Destination Matching**: Optionally detects customer shipping destination and applies the appropriate national scheme automatically.
* **Export Exemption**: Option to exclude container deposits when shipping outside covered deposit jurisdictions.
* **Product Page Notice**: Automatically displays the required deposit notice under the price (for example, "Plus 0,50 zl deposit, refunded upon return").
* **Variation Inheritance**: Variations automatically inherit parent container type and unit count unless explicitly overridden.
* **Full Compatibility**: Compatible with WooCommerce High-Performance Order Storage (HPOS) and WooCommerce Cart and Checkout Blocks.

= Documentation and links =

* **Documentation** - [plogins.com/botelix/docs/](https://plogins.com/botelix/docs/)
* **Plugin page** - [plogins.com/botelix/](https://plogins.com/botelix/)
* **Source code** - [github.com/wppoland/botelix](https://github.com/wppoland/botelix)
* **Bug reports and feature requests** - [GitHub issues](https://github.com/wppoland/botelix/issues)

Reporting a security issue: email hello@wppoland.com under our coordinated disclosure policy. We confirm within two business days, assess within five, and patch a critical issue within seven days of confirming it.

== Translations ==

Botelix is fully translatable and ships the `botelix.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org.

== Installation ==

1. Ensure WooCommerce 8.0 or higher is installed and active.
2. Install Botelix and activate it.
3. Go to WooCommerce > Deposit Return (DRS) to select your default scheme (such as Poland, Germany, Romania, Austria, Slovakia, or Hungary) and configure cross-border matching.
4. Edit beverage products to select their packaging container type and container units.

== Frequently Asked Questions ==

= Does Botelix support multi-packs? =
Yes. Set the container units field on the product (for example, 6 for a six-pack, 24 for a crate). The statutory deposit is multiplied by the container count.

= Are deposits subject to VAT? =
It depends on national legislation:
* In Poland (Ustawa o VAT Article 29a(11)), Romania (Codul Fiscal Article 286(4)(c)), Slovakia (Zakon c. 222/2004 Z. z. Section 22(3)), and Hungary (Afa tv. Section 71(1)(b)), packaging deposits are strictly outside the retail VAT base and added as non-taxable fees.
* In Germany (Section 46 VerpackDG, formerly Section 31 VerpackG) and Austria (BGBl. II Nr. 283/2023 Section 4), deposit amounts are defined inclusive of VAT and processed as taxable fees.
Botelix automatically configures the correct taxability flag per scheme.

= Does Botelix support cross-border sales? =
Yes. When "Cross-Border Destination Matching" is enabled, Botelix checks the customer shipping country during checkout and applies the corresponding statutory scheme. When "Export Exemption" is enabled, orders shipping outside covered DRS jurisdictions are exempted from container deposits.

= Does it work with Cart and Checkout Blocks? =
Yes. Botelix uses the native WooCommerce cart fees API and declares full compatibility with WooCommerce Cart and Checkout Blocks and High-Performance Order Storage (HPOS).

== Changelog ==

= 1.0.2 =
* Fixed: deleting the plugin on a multisite network now removes its settings from every site, not only the main one.

= 1.0.1 =
* Enhance Plugin Check compliance: add nonce verification and user capability checks on product save.
* Add translators comments for placeholders in admin strings.
* Optimize short description under 150 characters.

= 1.0.0 =
* Initial release: Statutory Deposit Return Scheme (DRS) support for Poland, Romania, Germany, Austria, Slovakia, Hungary, and custom schemes.
