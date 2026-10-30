=== Botelix - Deposit Return Scheme (Pfand, SGR, Kaucja, Zaloh) ===
Contributors: motylanogha
Tags: woocommerce, deposit return, pfand, sgr, kaucja
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Statutory container deposit scheme (DRS) calculations and checkout fees for European ecommerce (Pfand, SGR, Kaucja, Zaloh, Einwegpfand).

== Description ==

National deposit return schemes (DRS) require retailers selling beverages in covered packaging to collect a container deposit and refund it when the empty packaging comes back.

Botelix gives WooCommerce stores one lightweight tool for the deposit rules of six markets:

* **Germany (Pfand)**: Section 46(1) VerpackDG sets a deposit of at least 0.25 EUR including VAT per single-use beverage container of 0.1 l to 3.0 l (Section 46(4) lists the exempt drinks). Reusable (Mehrweg) beer and water bottles have no statutory amount; Botelix offers the customary 0.08 EUR and 0.15 EUR as editable trade-practice rates.
* **Austria (Einwegpfand)**: the Pfandverordnung fuer Einweggetraenkeverpackungen (BGBl. II Nr. 283/2023) Section 4 sets 0.25 EUR per single-use plastic bottle or metal can of 0.1 l to 3.0 l from 1 January 2025, milk drinks excepted. The regulation says nothing about VAT, so Botelix adds the deposit untaxed; a filter switches that if your tax adviser says otherwise.
* **Poland (System kaucyjny)**: the Regulation of the Minister of Climate and Environment of 8 July 2024 (Dz.U. 2024 poz. 1046) Section 1 sets 0.50 PLN for single-use plastic bottles up to 3 l, 0.50 PLN for metal cans up to 1 l and 1.00 PLN for reusable glass bottles up to 1.5 l. Annex 1a to the packaging act (t.j. Dz.U. 2026 poz. 619) lists these packaging types; single-use glass is not covered. Under VAT Act Article 29a(11a) the deposit is not part of the tax base.
* **Romania (SGR)**: HG nr. 1074/2021 (republished in M.Of. nr. 1120/2022, as amended) Article 12(5) sets a guarantee of 0.50 RON for every single-use glass, plastic or metal container of 0.1 l to 3 l holding a drink listed in Annex 1, mandatory since 30 November 2023 (Article 10(1)). Under Codul fiscal Article 315^5(2) the guarantee is outside the scope of VAT.
* **Slovakia (Zalohovy system)**: Vyhlaska c. 347/2019 Z. z. Section 2 sets minimums of 0.12 EUR per plastic bottle and 0.10 EUR per metal can; the scheme administrator sets the actual rate (Zakon c. 302/2019 Z. z. Section 7(1)(g)). Botelix ships the minimums; enter the administrator's current rate in the settings. Under Zakon c. 222/2004 Z. z. Section 22(3) the deposit is not part of the VAT base.
* **Hungary (DRS)**: 450/2023. (X. 4.) Korm. rendelet Section 3(1) sets a 50 HUF redemption fee per single-use plastic, metal or glass beverage container of 0.1 l to 3 l, from 1 January 2024. The producer sets the fee for reusable containers, so that rate starts at 0 for you to fill in. Botelix adds the fee untaxed.
* **Custom Scheme**: one container rate in your store currency for any other deposit scheme, added untaxed.

Key capabilities:

* **Automatic Cart Fee**: Adds the deposit as a dedicated fee at cart and checkout using native WooCommerce fees.
* **Tax Base Handling**: Where the law states the deposit including VAT (Germany), Botelix adds the net share per tax class, so the customer pays exactly the statutory amount with VAT. Everywhere else the deposit is added untaxed.
* **One Container Type, Every Scheme**: Tag a product once as single-use plastic bottle, metal can, single-use glass bottle or reusable glass bottle; each national scheme charges its own rate for it, or nothing where it is not covered.
* **Multi-Pack Multiplier**: Multiplies deposit by container count (a 6-pack of cans automatically charges 6 container deposits).
* **Cross-Border Destination Matching**: Optionally applies the scheme of the customer's shipping country. A scheme is only charged when its currency matches the store currency, because the rates are set in law in that currency and Botelix does not convert; the settings screen lists every scheme your store cannot charge.
* **Export Exemption**: Option to exclude container deposits when shipping outside covered deposit jurisdictions.
* **Product Page Notice**: Displays the deposit notice under the price (for example, "Plus 0,50 zl statutory deposit, refunded when you return the packaging"), on classic and block single-product templates.
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
* In Poland (VAT Act Article 29a(11a)), Romania (Codul fiscal Article 315^5(2)) and Slovakia (Zakon c. 222/2004 Z. z. Section 22(3)) the deposit is outside the VAT base, and Botelix adds it untaxed. Hungary and Austria are added untaxed as well.
* In Germany, Section 46(1) VerpackDG states the deposit including VAT. Botelix adds the net share in the product's tax class and WooCommerce adds the tax, so the customer pays exactly 0.25 EUR.
Developers can change a scheme's treatment with the `botelix_scheme_taxable` filter.

= Does Botelix support cross-border sales? =
Yes. When "Cross-Border Destination Matching" is enabled, Botelix checks the customer shipping country during checkout and applies that country's scheme, but only when the scheme's currency equals the store currency (a PLN store charges nothing for a delivery to Germany, an EUR store charges the German, Austrian or Slovak rate). Botelix never converts currencies. When "Export Exemption" is enabled, orders shipping outside covered DRS jurisdictions are exempted from container deposits.

= Does it work with Cart and Checkout Blocks? =
Yes. Botelix uses the native WooCommerce cart fees API and declares full compatibility with WooCommerce Cart and Checkout Blocks and High-Performance Order Storage (HPOS).

== Screenshots ==

1. Cart with a PET bottle, a 6-pack of cans and a returnable glass bottle: Botelix adds a separate "Deposit (kaucja)" line of 4,50 zl to the cart totals.
2. Checkout order review with the same deposit line between the subtotal and the total.
3. Product page for a 6-pack of cans with the deposit notice under the price: "Plus 3,00 zl statutory deposit, refunded when you return the packaging."
4. Product editor, General tab: the Deposit Return Scheme fields (scheme override, packaging container, container units).
5. Settings under WooCommerce > Deposit Return (DRS): enable switch, default scheme, cross-border matching, export exemption and the Polish scheme's label, notice and statutory rates.

== Changelog ==

= 1.1.0 =
* Fixed: a fresh install charged nothing until the settings were saved once.
* Fixed: a product notice template with %s printed the placeholder instead of the amount.
* Fixed: the German Pfand was charged with VAT on top; the customer now pays exactly the statutory amount including VAT.
* Fixed: cross-border matching charged nothing for a product shipped to another scheme, and charged rates in the wrong currency.
* Fixed: the deposit notice was missing on block single-product templates.
* Changed: products are tagged with a container type that every scheme maps to its own rate; existing products keep working.
* Changed: a product saved before 1.1.0 with a type that covered several materials (such as the German Einweg) keeps its own scheme's rate, is charged nothing where the material decides the rate, and shows as the current legacy setting in the editor until you pick a container.
* Fixed: the product editor listed the store default scheme's containers instead of the product's scheme override, so saving a German Mehrweg product could drop its deposit; variations now follow the parent's override as well.
* Changed: a scheme is only charged when its currency equals the store currency; the settings screen lists the schemes that cannot be charged.
* Changed: Austria is added without VAT and its notice no longer says "inkl. USt.", as the regulation states no VAT.
* Changed: Slovakia uses the statutory minimums, 0.12 EUR for plastic bottles and 0.10 EUR for cans; the administrator's rate goes in the settings.
* Changed: the Hungarian reusable fee starts at 0, as the producer sets it.
* Changed: legal references corrected (Poland Dz.U. 2024 poz. 1046, Romania M.Of. 1120/2022, Austria's regulation title, Hungary's decree only).
* Changed: Romanian, Slovak and Hungarian notices use proper diacritics; the Romanian notice adds the guarantee instead of saying it is included.

= 1.0.2 =
* Fixed: deleting the plugin on a multisite network now removes its settings from every site, not only the main one.

= 1.0.1 =
* Enhance Plugin Check compliance: add nonce verification and user capability checks on product save.
* Add translators comments for placeholders in admin strings.
* Optimize short description under 150 characters.

= 1.0.0 =
* Initial release: Statutory Deposit Return Scheme (DRS) support for Poland, Romania, Germany, Austria, Slovakia, Hungary, and custom schemes.
