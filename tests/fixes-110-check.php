<?php
/**
 * Self-check for the 1.1.0 fixes: defaults without a save, notice placeholders,
 * VAT-inclusive deposits, cross-border container mapping with the currency rule,
 * the notice on block single-product templates, VAT-exempt customers, legacy
 * container keys, the product editor's scheme override and the settings screen.
 *
 * Each section reports on its own, so a run against older code shows which
 * fixes are missing. Run: php tests/fixes-110-check.php
 *
 * @package Botelix
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BOTELIX_DIR', __DIR__ . '/../');

require_once __DIR__ . '/../autoload.php';

$GLOBALS['options']  = [];
$GLOBALS['currency'] = 'PLN';
$GLOBALS['hooks']    = [];
$GLOBALS['products'] = [];
$GLOBALS['queried']  = 0;

function __(string $text, string $domain = 'default'): string
{
    return $text;
}
function get_option(string $option, mixed $default = false): mixed
{
    return $GLOBALS['options'][$option] ?? $default;
}
function add_action(string $tag, mixed $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['hooks'][] = $tag;
}
function add_filter(string $tag, mixed $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['hooks'][] = $tag;
}
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    return $value;
}
function get_woocommerce_currency(): string
{
    return $GLOBALS['currency'];
}
function wc_get_product(int $id): mixed
{
    return $GLOBALS['products'][$id] ?? null;
}
function wc_price(float $amount): string
{
    return number_format($amount, 2, ',', '') . ' ' . $GLOBALS['currency'];
}
function wp_kses_post(string $html): string
{
    return $html;
}
function is_admin(): bool
{
    return false;
}
function wp_doing_ajax(): bool
{
    return false;
}
function is_product(): bool
{
    return true;
}
function get_queried_object_id(): int
{
    return $GLOBALS['queried'];
}
function wc_get_price_decimals(): int
{
    return 2;
}
function wc_tax_enabled(): bool
{
    return $GLOBALS['tax_enabled'] ?? true;
}
function esc_html__(string $text, string $domain = 'default'): string
{
    return $text;
}
function esc_html(string $text): string
{
    return $text;
}
function esc_attr(string $text): string
{
    return $text;
}
function checked(mixed $checked, mixed $current = true): void
{
}
function selected(mixed $selected, mixed $current = true): void
{
}
function settings_fields(string $group): void
{
}
function submit_button(): void
{
}
function current_user_can(string $cap, mixed ...$args): bool
{
    return true;
}
function wp_nonce_field(string $action, string $name): void
{
}
function get_post_meta(int $id, string $key, bool $single = false): mixed
{
    return wc_get_product($id)?->get_meta($key);
}
/** @param array<string, mixed> $field */
function woocommerce_wp_select(array $field): void
{
    $GLOBALS['fields'][$field['id']] = $field;
}
/** @param array<string, mixed> $field */
function woocommerce_wp_text_input(array $field): void
{
}
function wc_format_localized_decimal(string $value): string
{
    return $value;
}

class WC_Product
{
    /** @param array<string, mixed> $meta */
    public function __construct(private int $id, private array $meta = [], private string $taxClass = '', private int $parentId = 0)
    {
        $GLOBALS['products'][$id] = $this;
    }
    public function get_id(): int
    {
        return $this->id;
    }
    public function get_parent_id(): int
    {
        return $this->parentId;
    }
    public function get_meta(string $key, bool $single = true): mixed
    {
        return $this->meta[$key] ?? '';
    }
    public function get_tax_class(): string
    {
        return $this->taxClass;
    }
    public function is_taxable(): bool
    {
        return true;
    }
}

class WC_Customer
{
    public function __construct(private bool $exempt = false)
    {
    }
    public function get_is_vat_exempt(): bool
    {
        return $this->exempt;
    }
}

class WP_Block
{
    /** @param array<string, mixed> $context */
    public function __construct(public array $context = [])
    {
    }
}

class WC_Tax
{
    /** @return array<int, array{rate: float}> */
    public static function get_rates(string $taxClass = '', mixed $customer = null): array
    {
        return $taxClass === 'reduced-rate' ? [2 => ['rate' => 7.0]] : [1 => ['rate' => 19.0]];
    }
    /**
     * @param array<int, array{rate: float}> $rates
     * @return array<int, float>
     */
    public static function calc_tax(float $price, array $rates, bool $inclusive = false): array
    {
        $taxes = [];
        foreach ($rates as $id => $rate) {
            $taxes[$id] = $inclusive ? $price - $price / (1 + $rate['rate'] / 100) : $price * $rate['rate'] / 100;
        }
        return $taxes;
    }
}

/**
 * Records fees from both the fees API and the legacy WC_Cart::add_fee().
 */
class WC_Cart
{
    /** @var array<int, array<string, mixed>> */
    public array $fees = [];

    /** @param array<int, array<string, mixed>> $items */
    public function __construct(private array $items)
    {
    }
    /** @return array<int, array<string, mixed>> */
    public function get_cart(): array
    {
        return $this->items;
    }
    public function get_customer(): mixed
    {
        return $GLOBALS['customer'] ?? null;
    }
    public function fees_api(): self
    {
        return $this;
    }
    public function add_fee(mixed $args, mixed $amount = null, bool $taxable = false): void
    {
        $this->fees[] = is_array($args) ? $args : ['id' => '', 'name' => $args, 'amount' => $amount, 'taxable' => $taxable];
    }
    /** What the customer pays for all deposit fees, fee tax included (none for a VAT-exempt customer, as in WooCommerce). */
    public function gross(): float
    {
        $exempt = $this->get_customer()?->get_is_vat_exempt() ?? false;
        $sum    = 0.0;
        foreach ($this->fees as $fee) {
            $rate = empty($fee['taxable']) || $exempt ? 0.0 : array_sum(array_column(WC_Tax::get_rates((string) ($fee['tax_class'] ?? '')), 'rate'));
            $sum += round((float) $fee['amount'] * (1 + $rate / 100), 2);
        }
        return round($sum, 2);
    }
}

use Botelix\Admin\ProductMetaBox;
use Botelix\Admin\SettingsPage;
use Botelix\Model\Scheme;
use Botelix\Service\DepositService as DS;

/**
 * The container select the product editor renders for a product.
 *
 * @return array<string, mixed>
 */
function editorField(DS $svc, int $productId): array
{
    global $thepostid;
    $thepostid = $productId;
    $GLOBALS['fields'] = [];
    ob_start();
    (new ProductMetaBox($svc))->renderProductFields();
    ob_end_clean();
    return $GLOBALS['fields'][DS::META_TYPE];
}

function settingsHtml(DS $svc): string
{
    ob_start();
    (new SettingsPage($svc))->renderPage();
    return (string) ob_get_clean();
}

/** @param array<string, mixed>|null $settings */
function service(?array $settings, string $currency): DS
{
    $GLOBALS['options']  = $settings === null ? [] : [DS::OPTION => $settings];
    $GLOBALS['currency'] = $currency;
    return new DS();
}

$failed = 0;
function section(string $name, callable $body): void
{
    global $failed;
    try {
        $body();
        echo "  ok    $name\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "  FAIL  $name: " . $e->getMessage() . "\n";
    }
}
function check(bool $cond, string $message): void
{
    if (! $cond) {
        throw new \RuntimeException($message);
    }
}

echo "Botelix 1.1.0 fixes\n";

section('1 fresh install charges without a settings save', function (): void {
    $GLOBALS['hooks'] = [];
    $svc = service(null, 'PLN');
    check($svc->isEnabled(), 'isEnabled() is false with no saved option');
    $svc->registerHooks();
    check(in_array('woocommerce_cart_calculate_fees', $GLOBALS['hooks'], true), 'cart fee hook not registered');
    $pet = new WC_Product(1, [DS::META_TYPE => 'plastic']);
    check(abs($svc->depositFor($pet) - 0.50) < 1e-9, 'fresh PL store does not charge 0.50 for a plastic bottle');
});

section('2 notice template with %s or {amount} renders the amount', function (): void {
    foreach (['Plus %s kaucji.', 'Plus {amount} kaucji.'] as $template) {
        $svc = service(['enabled' => '1', 'default_scheme' => 'pl', 'product_notice_pl' => $template], 'PLN');
        $GLOBALS['product'] = new WC_Product(2, [DS::META_TYPE => 'pet']);
        ob_start();
        $svc->renderProductNotice();
        $html = (string) ob_get_clean();
        check(str_contains($html, '0,50 PLN') && ! str_contains($html, '%s') && ! str_contains($html, '{amount}'), "template '$template' rendered as: $html");
    }
});

section('3 VAT: DE gross deposit stays the statutory amount, PL stays untaxed', function (): void {
    $svc  = service(['enabled' => '1', 'default_scheme' => 'de', 'enable_destination_matching' => '0'], 'EUR');
    $std  = new WC_Product(3, [DS::META_TYPE => 'can', DS::META_UNITS => 6]);
    $red  = new WC_Product(4, [DS::META_TYPE => 'einweg'], 'reduced-rate');
    $cart = new WC_Cart([['data' => $std, 'quantity' => 1], ['data' => $red, 'quantity' => 2]]);
    $svc->applyCartFee($cart);
    check(abs($cart->gross() - 2.00) < 0.011, 'DE customer pays ' . $cart->gross() . ' instead of 6x0.25 + 2x0.25 = 2.00');
    $ids = array_column($cart->fees, 'id');
    check(count($ids) === 2 && count(array_unique($ids)) === 2, 'expected two fees with distinct ids, got ' . json_encode($ids));

    $svc  = service(['enabled' => '1', 'default_scheme' => 'pl', 'enable_destination_matching' => '0'], 'PLN');
    $cart = new WC_Cart([['data' => new WC_Product(5, [DS::META_TYPE => 'pet']), 'quantity' => 3]]);
    $svc->applyCartFee($cart);
    check(count($cart->fees) === 1 && empty($cart->fees[0]['taxable']) && abs($cart->gross() - 1.50) < 1e-9, 'PL fee must be 1.50 untaxed');
});

section('3 rates and VAT flags equal the verified acts', function (): void {
    check(Scheme::get('de')['taxable'] === true, 'DE must be stated including VAT');
    foreach (['pl', 'ro', 'sk', 'hu', 'at'] as $id) {
        check(Scheme::get($id)['taxable'] === false, "$id must be untaxed");
    }

    $rates = [
        'pl' => ['pet' => 0.50, 'can' => 0.50, 'glass_reusable' => 1.00],
        'de' => ['einweg' => 0.25, 'can' => 0.25],
        'at' => ['einweg' => 0.25],
        'sk' => ['zaloh_single_use' => 0.12, 'zaloh_can' => 0.10],
        'ro' => ['sgr_single_use' => 0.50],
        'hu' => ['drs_single_use' => 50.0, 'drs_reusable' => 0.0],
    ];
    foreach ($rates as $id => $types) {
        foreach ($types as $key => $amount) {
            check((Scheme::get($id)['types'][$key]['amount'] ?? null) === $amount, "$id/$key must be $amount");
        }
    }
});

section('4 cross-border: one container maps to each scheme, same currency only', function (): void {
    $svc     = service(['enabled' => '1', 'default_scheme' => 'de'], 'EUR');
    $plastic = new WC_Product(6, [DS::META_TYPE => 'plastic']);
    $legacy  = new WC_Product(7, [DS::META_TYPE => 'pet']);
    $glass   = new WC_Product(8, [DS::META_TYPE => 'glass']);
    check(abs($svc->depositFor($plastic, 'at') - 0.25) < 1e-9, 'plastic to AT must be 0.25');
    check(abs($svc->depositFor($legacy, 'at') - 0.25) < 1e-9, 'pre-1.1.0 "pet" product to AT must be 0.25');
    check(abs($svc->depositFor($plastic, 'sk') - 0.12) < 1e-9, 'plastic to SK must be 0.12');
    check($svc->depositFor($glass, 'at') === 0.0, 'single-use glass is not covered in AT');
    check($svc->depositFor($plastic, 'pl') === 0.0, 'PLN scheme must not be charged in an EUR store');

    $svc = service(['enabled' => '1', 'default_scheme' => 'pl', 'enable_destination_matching' => '1'], 'PLN');
    check(abs($svc->depositFor($legacy, 'pl') - 0.50) < 1e-9, 'pre-1.1.0 "pet" product in PL keeps 0.50');
    check($svc->depositFor($plastic, 'de') === 0.0, 'EUR scheme must not be charged in a PLN store');
    check(array_keys($svc->unchargeableSchemes()) === ['ro', 'at', 'de', 'sk', 'hu'], 'settings warning must list ro, at, de, sk, hu');

    $svc = service(['enabled' => '1', 'default_scheme' => 'pl'], 'EUR');
    check($svc->depositFor($legacy, 'pl') === 0.0, 'PL rate must not be charged as EUR');
});

section('5 notice on block single-product templates, printed once', function (): void {
    $GLOBALS['hooks'] = [];
    $svc = service(['enabled' => '1', 'default_scheme' => 'pl'], 'PLN');
    $svc->registerHooks();
    check(in_array('render_block_woocommerce/product-price', $GLOBALS['hooks'], true), 'price block filter not registered');
    $GLOBALS['queried'] = 9;
    $GLOBALS['product'] = new WC_Product(9, [DS::META_TYPE => 'can', DS::META_UNITS => 6]);
    new WC_Product(10, [DS::META_TYPE => 'can']);
    $grid = $svc->appendNoticeToPriceBlock('<div>price</div>', [], new WP_Block(['postId' => 10, 'queryId' => 1]));
    check($grid === '<div>price</div>', 'notice must not appear on another product\'s price block');
    $main = $svc->appendNoticeToPriceBlock('<div>price</div>', [], new WP_Block(['postId' => 9]));
    check(str_contains($main, '3,00 PLN'), 'notice missing after the main price block');
    ob_start();
    $svc->renderProductNotice();
    check((string) ob_get_clean() === '', 'summary hook printed the notice a second time');
});

section('6 VAT-exempt customer on DE pays the full 0.25 EUR, untaxed', function (): void {
    $svc  = service(['enabled' => '1', 'default_scheme' => 'de', 'enable_destination_matching' => '0'], 'EUR');
    $GLOBALS['customer'] = new WC_Customer(true);
    $cart = new WC_Cart([['data' => new WC_Product(60, [DS::META_TYPE => 'can']), 'quantity' => 1]]);
    $svc->applyCartFee($cart);
    $paid = $cart->gross();
    $GLOBALS['customer'] = new WC_Customer(false);
    check(abs($paid - 0.25) < 1e-9, 'exempt customer pays ' . $paid . ' instead of 0.25');
    check(empty($cart->fees[0]['taxable']), 'exempt customer fee must be untaxed');

    $cart = new WC_Cart([['data' => new WC_Product(61, [DS::META_TYPE => 'can']), 'quantity' => 1]]);
    $svc->applyCartFee($cart);
    unset($GLOBALS['customer']);
    check(abs($cart->gross() - 0.25) < 1e-9 && ! empty($cart->fees[0]['taxable']), 'taxed customer must still pay 0.25 with VAT inside');
});

section('7 ambiguous legacy key keeps its own scheme and invents no material', function (): void {
    $svc    = service(['enabled' => '1', 'default_scheme' => 'de'], 'EUR');
    $einweg = new WC_Product(70, [DS::META_TYPE => 'einweg']);
    check(abs($svc->depositFor($einweg, 'de') - 0.25) < 1e-9, 'legacy einweg must keep 0.25 in DE');
    check(abs($svc->depositFor($einweg, 'at') - 0.25) < 1e-9, 'legacy einweg must keep 0.25 in AT');
    check($svc->depositFor($einweg, 'sk') === 0.0, 'legacy einweg (maybe glass) must not be charged as SK plastic');
    $field = editorField($svc, 70);
    check($field['value'] === 'einweg', 'editor preselects ' . $field['value'] . ' instead of the stored einweg');
    check(str_contains((string) ($field['options']['einweg'] ?? ''), 'legacy'), 'editor must list einweg as the current legacy setting');

    // Every scheme reads the custom scheme's custom_1 the same way: no single material.
    $svc = service(['enabled' => '1', 'default_scheme' => 'custom'], 'EUR');
    check($svc->depositFor(new WC_Product(71, [DS::META_TYPE => 'custom_1']), 'sk') === 0.0, 'legacy custom_1 must not be charged as SK plastic');
    $svc = service(['enabled' => '1', 'default_scheme' => 'hu'], 'HUF');
    check($svc->depositFor(new WC_Product(72, [DS::META_TYPE => 'drs_reusable']), 'custom') === 0.0, 'legacy drs_reusable (material unknown) must not be read as reusable glass');

    // An unambiguous key still preselects its category, which charges the same everywhere.
    $svc = service(['enabled' => '1', 'default_scheme' => 'pl'], 'PLN');
    new WC_Product(73, [DS::META_TYPE => 'pet']);
    check(editorField($svc, 73)['value'] === 'plastic', 'legacy pet must preselect the plastic category');
});

section('8 product editor follows the product scheme override', function (): void {
    $svc = service(['enabled' => '1', 'default_scheme' => 'at', 'enable_destination_matching' => '0'], 'EUR');
    new WC_Product(80, [DS::META_TYPE => 'mehrweg_bier', DS::META_SCHEME => 'de']);
    $field = editorField($svc, 80);
    check($field['value'] === 'mehrweg_bier', 'editor preselects ' . $field['value'] . ' instead of mehrweg_bier');
    check(! str_contains((string) ($field['options']['mehrweg_bier'] ?? ''), 'legacy') && isset($field['options']['mehrweg_bier']), 'DE override must list mehrweg_bier as a DE type');
    $saved = new WC_Product(81, [DS::META_TYPE => $field['value'], DS::META_SCHEME => 'de']);
    check(abs($svc->depositFor($saved) - 0.08) < 1e-9, 'a plain save drops the DE Mehrweg deposit to ' . $svc->depositFor($saved));

    // A variation with its own container is charged under the parent's override.
    new WC_Product(82, [DS::META_TYPE => 'can', DS::META_SCHEME => 'de']);
    $variation = new WC_Product(83, [DS::META_TYPE => 'mehrweg_mineral'], '', 82);
    check(abs($svc->depositFor($variation) - 0.15) < 1e-9, 'variation ignores the parent DE override');
});

section('9 settings: VAT label needs WooCommerce taxes, currency warning needs the plugin on', function (): void {
    $GLOBALS['tax_enabled'] = false;
    $html = settingsHtml(service(['enabled' => '1', 'default_scheme' => 'de'], 'EUR'));
    $GLOBALS['tax_enabled'] = true;
    $taxBack = 'WooCommerce adds the tax back';
    check(! str_contains($html, $taxBack) && str_contains($html, 'WooCommerce taxes are off'), 'net-share VAT label shown with WooCommerce taxes off');
    check(str_contains(settingsHtml(service(['enabled' => '1', 'default_scheme' => 'de'], 'EUR')), $taxBack), 'VAT label missing with taxes on');

    $warning = 'These schemes charge nothing in this store';
    check(str_contains(settingsHtml(service(['enabled' => '1', 'default_scheme' => 'pl'], 'PLN')), $warning), 'currency warning missing while enabled');
    check(! str_contains(settingsHtml(service(['enabled' => '0', 'default_scheme' => 'pl'], 'PLN')), $warning), 'currency warning shown while the plugin is disabled');
});

echo $failed === 0 ? "\nALL 1.1.0 CHECKS PASSED\n" : "\n$failed SECTION(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
