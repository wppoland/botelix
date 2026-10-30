<?php
/**
 * Self-check for 1.1.0: defaults without a save, notice placeholders, the
 * scheme x container category matrix, VAT-inclusive deposits, the currency
 * rule, the notice on block single-product templates, VAT-exempt customers,
 * the product editor's categories and scheme override, and the settings screen.
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
function wp_verify_nonce(string $nonce, string $action): bool
{
    return true;
}
function sanitize_key(string $key): string
{
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key));
}
function wp_unslash(mixed $value): mixed
{
    return $value;
}
function absint(mixed $value): int
{
    return abs((int) $value);
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
    public function update_meta_data(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }
    public function save_meta_data(): void
    {
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
        $GLOBALS['product'] = new WC_Product(2, [DS::META_TYPE => 'plastic']);
        ob_start();
        $svc->renderProductNotice();
        $html = (string) ob_get_clean();
        check(str_contains($html, '0,50 PLN') && ! str_contains($html, '%s') && ! str_contains($html, '{amount}'), "template '$template' rendered as: $html");
    }
});

section('3 every scheme x category charges the expected default rate', function (): void {
    // Expected values written out here, not read from Scheme.php; null = not covered.
    $matrix = [
        'pl'     => ['PLN', ['plastic' => 0.50, 'can' => 0.50, 'glass' => null, 'glass_reusable' => 1.00]],
        'ro'     => ['RON', ['plastic' => 0.50, 'can' => 0.50, 'glass' => 0.50, 'glass_reusable' => null]],
        'at'     => ['EUR', ['plastic' => 0.25, 'can' => 0.25, 'glass' => null, 'glass_reusable' => null]],
        'de'     => ['EUR', ['plastic' => 0.25, 'can' => 0.25, 'glass' => 0.25, 'glass_reusable' => 0.15]],
        'sk'     => ['EUR', ['plastic' => 0.12, 'can' => 0.10, 'glass' => null, 'glass_reusable' => null]],
        'hu'     => ['HUF', ['plastic' => 50.0, 'can' => 50.0, 'glass' => 50.0, 'glass_reusable' => 0.0]],
        'custom' => ['', ['plastic' => 0.25, 'can' => 0.25, 'glass' => 0.25, 'glass_reusable' => 0.25]],
    ];
    check(array_keys($matrix) === array_keys(Scheme::all()), 'scheme list changed: ' . json_encode(array_keys(Scheme::all())));
    check(array_keys(Scheme::containers()) === ['plastic', 'can', 'glass', 'glass_reusable'], 'container categories changed');

    $id = 300;
    foreach ($matrix as $schemeId => [$currency, $rates]) {
        $scheme = Scheme::get($schemeId);
        check($scheme['currency'] === $currency, "$schemeId currency must be '$currency'");
        check(array_keys($scheme['rates']) === array_keys($rates), "$schemeId must declare every category");
        $svc = service(null, $currency === '' ? 'EUR' : $currency);
        foreach ($rates as $category => $rate) {
            check(($scheme['rates'][$category]['amount'] ?? null) === $rate, "$schemeId/$category must be " . json_encode($rate));
            $charged = $svc->depositFor(new WC_Product(++$id, [DS::META_TYPE => $category, DS::META_UNITS => 2]), $schemeId);
            check(abs($charged - 2 * (float) $rate) < 1e-9, "$schemeId/$category x2 charges $charged instead of " . 2 * (float) $rate);
        }
    }

    check(Scheme::get('de')['taxable'] === true, 'DE must be stated including VAT');
    foreach (['pl', 'ro', 'sk', 'hu', 'at', 'custom'] as $schemeId) {
        check(Scheme::get($schemeId)['taxable'] === false, "$schemeId must be untaxed");
    }
});

section('4 settings rate overrides only covered categories', function (): void {
    $svc = service(['enabled' => '1', 'default_scheme' => 'hu', 'amount_hu_glass_reusable' => '70', 'amount_hu_plastic' => ''], 'HUF');
    check($svc->depositFor(new WC_Product(40, [DS::META_TYPE => 'glass_reusable']), 'hu') === 70.0, 'HU reusable fee entered in settings is not charged');
    check($svc->depositFor(new WC_Product(41, [DS::META_TYPE => 'plastic']), 'hu') === 50.0, 'empty HU plastic override must fall back to 50');
    $svc = service(['enabled' => '1', 'default_scheme' => 'at', 'amount_at_glass' => '5'], 'EUR');
    check($svc->depositFor(new WC_Product(42, [DS::META_TYPE => 'glass']), 'at') === 0.0, 'an amount for a category AT does not cover must not be charged');

    $html = settingsHtml(service(['enabled' => '1', 'default_scheme' => 'pl'], 'PLN'));
    check(str_contains($html, '[amount_hu_glass_reusable]') && str_contains($html, '[amount_de_glass_reusable]'), 'settings must offer the HU and DE reusable rates');
    check(! str_contains($html, '[amount_at_glass]') && ! str_contains($html, '[amount_pl_glass]'), 'settings must not offer a rate for an uncovered category');
    $saved = (new SettingsPage(service(null, 'PLN')))->sanitizeSettings(['amount_at_glass' => '5', 'amount_sk_plastic' => '0.15']);
    check(! isset($saved['amount_at_glass']) && $saved['amount_sk_plastic'] === 0.15, 'sanitize must keep covered rates only: ' . json_encode($saved));
});

section('5 VAT: DE gross deposit stays the statutory amount, PL stays untaxed', function (): void {
    $svc  = service(['enabled' => '1', 'default_scheme' => 'de', 'enable_destination_matching' => '0'], 'EUR');
    $std  = new WC_Product(50, [DS::META_TYPE => 'can', DS::META_UNITS => 6]);
    $red  = new WC_Product(51, [DS::META_TYPE => 'glass'], 'reduced-rate');
    $cart = new WC_Cart([['data' => $std, 'quantity' => 1], ['data' => $red, 'quantity' => 2]]);
    $svc->applyCartFee($cart);
    check(abs($cart->gross() - 2.00) < 0.011, 'DE customer pays ' . $cart->gross() . ' instead of 6x0.25 + 2x0.25 = 2.00');
    $ids = array_column($cart->fees, 'id');
    check(count($ids) === 2 && count(array_unique($ids)) === 2, 'expected two fees with distinct ids, got ' . json_encode($ids));
    check(! empty($cart->fees[0]['taxable']) && abs($cart->fees[0]['amount'] - 1.50 / 1.19) < 1e-9, 'DE fee must be the net share of 1.50 at 19%');

    $svc  = service(['enabled' => '1', 'default_scheme' => 'pl', 'enable_destination_matching' => '0'], 'PLN');
    $cart = new WC_Cart([['data' => new WC_Product(52, [DS::META_TYPE => 'plastic']), 'quantity' => 3]]);
    $svc->applyCartFee($cart);
    check(count($cart->fees) === 1 && empty($cart->fees[0]['taxable']) && abs($cart->gross() - 1.50) < 1e-9, 'PL fee must be 1.50 untaxed');
});

section('6 cross-border: each scheme charges only in its own currency', function (): void {
    $svc     = service(['enabled' => '1', 'default_scheme' => 'de'], 'EUR');
    $plastic = new WC_Product(60, [DS::META_TYPE => 'plastic']);
    $glass   = new WC_Product(61, [DS::META_TYPE => 'glass']);
    check(abs($svc->depositFor($plastic, 'at') - 0.25) < 1e-9, 'EUR store: plastic to AT must be 0.25');
    check(abs($svc->depositFor($plastic, 'sk') - 0.12) < 1e-9, 'EUR store: plastic to SK must be 0.12');
    check(abs($svc->depositFor($glass, 'de') - 0.25) < 1e-9, 'EUR store: single-use glass to DE must be 0.25');
    check($svc->depositFor($glass, 'at') === 0.0, 'single-use glass is not covered in AT');
    check($svc->depositFor($plastic, 'pl') === 0.0, 'PLN scheme must not be charged in an EUR store');
    check($svc->depositFor($plastic, 'ro') === 0.0 && $svc->depositFor($plastic, 'hu') === 0.0, 'RON/HUF schemes must not be charged in an EUR store');
    check(abs($svc->depositFor($plastic, 'custom') - 0.25) < 1e-9, 'custom scheme has no currency and charges in any store');

    $svc = service(['enabled' => '1', 'default_scheme' => 'pl', 'enable_destination_matching' => '1'], 'PLN');
    check(abs($svc->depositFor($plastic, 'pl') - 0.50) < 1e-9, 'PLN store: plastic in PL must be 0.50');
    check($svc->depositFor($plastic, 'de') === 0.0, 'EUR scheme must not be charged in a PLN store');
    check(array_keys($svc->unchargeableSchemes()) === ['ro', 'at', 'de', 'sk', 'hu'], 'settings warning must list ro, at, de, sk, hu');
});

section('7 notice on block single-product templates, printed once', function (): void {
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

section('8 VAT-exempt customer on DE pays the full 0.25 EUR, untaxed', function (): void {
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

section('9 editor offers the four categories and marks the ones the effective scheme does not cover', function (): void {
    $svc = service(['enabled' => '1', 'default_scheme' => 'pl', 'enable_destination_matching' => '0'], 'PLN');
    new WC_Product(90, [DS::META_TYPE => 'can']);
    $field = editorField($svc, 90);
    check(array_keys($field['options']) === ['', 'plastic', 'can', 'glass', 'glass_reusable'], 'editor options: ' . json_encode(array_keys($field['options'])));
    check($field['value'] === 'can', 'editor preselects ' . $field['value'] . ' instead of the stored can');
    check(str_contains($field['options']['glass'], 'not covered by Poland'), 'PL default: single-use glass must be marked not covered');
    check(! str_contains($field['options']['glass_reusable'], 'not covered'), 'PL default: reusable glass is covered');

    // The product's override decides, not the store default.
    new WC_Product(91, [DS::META_TYPE => 'glass_reusable', DS::META_SCHEME => 'at']);
    $options = editorField($svc, 91)['options'];
    check(str_contains($options['glass'], 'not covered by Austria') && str_contains($options['glass_reusable'], 'not covered by Austria'), 'AT override: both glass categories must be marked');
    check(! str_contains($options['plastic'], 'not covered') && ! str_contains($options['can'], 'not covered'), 'AT override: plastic and can are covered');

    // Only a category is saved; an unknown value is cleared.
    $product = new WC_Product(92);
    $_POST = ['botelix_product_nonce' => 'n', DS::META_SCHEME => 'de', DS::META_TYPE => 'einweg', DS::META_UNITS => '6'];
    (new ProductMetaBox($svc))->saveProductFields(92);
    check($product->get_meta(DS::META_TYPE) === '', 'a value outside the four categories must not be saved');
    $_POST[DS::META_TYPE] = 'glass';
    (new ProductMetaBox($svc))->saveProductFields(92);
    $_POST = [];
    check($product->get_meta(DS::META_TYPE) === 'glass', 'a category must be saved');
    check($svc->depositFor($product, 'pl') === 0.0, 'saved DE override with glass in a PLN store must not charge (currency rule)');
});

section('10 scheme override in the editor drives the charge, variations follow the parent', function (): void {
    $svc = service(['enabled' => '1', 'default_scheme' => 'at', 'enable_destination_matching' => '0'], 'EUR');
    $plain = new WC_Product(100, [DS::META_TYPE => 'glass_reusable']);
    check($svc->depositFor($plain) === 0.0, 'AT store must not charge reusable glass');
    new WC_Product(101, [DS::META_TYPE => 'glass_reusable', DS::META_SCHEME => 'de']);
    $field = editorField($svc, 101);
    check($field['value'] === 'glass_reusable' && ! str_contains($field['options']['glass_reusable'], 'not covered'), 'DE override: reusable glass must be offered as covered');
    check(abs($svc->depositFor(wc_get_product(101)) - 0.15) < 1e-9, 'DE override must charge the 0.15 Mehrweg rate');

    new WC_Product(102, [DS::META_TYPE => 'can', DS::META_SCHEME => 'de']);
    $variation = new WC_Product(103, [DS::META_TYPE => 'glass'], '', 102);
    check(abs($svc->depositFor($variation) - 0.25) < 1e-9, 'variation with its own glass must be charged under the parent DE override');
    $inherit = new WC_Product(104, [], '', 102);
    check(abs($svc->depositFor($inherit) - 0.25) < 1e-9, 'variation without a container must inherit the parent can');
});

section('11 settings: VAT label needs WooCommerce taxes, currency warning needs the plugin on', function (): void {
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

section('12 settings: unknown default scheme rejected, fee label not pre-filled', function (): void {
    $saved = (new SettingsPage(service(null, 'PLN')))->sanitizeSettings(['default_scheme' => 'xx']);
    check($saved['default_scheme'] === 'pl', 'unknown default_scheme must fall back to pl: ' . json_encode($saved));
    $saved = (new SettingsPage(service(null, 'PLN')))->sanitizeSettings(['default_scheme' => 'de']);
    check($saved['default_scheme'] === 'de', 'a known scheme must be kept');
    $html = settingsHtml(service(['enabled' => '1', 'default_scheme' => 'pl'], 'PLN'));
    check((bool) preg_match('/\[fee_label_pl\]" value="" placeholder="[^"]+"/', $html), 'fee label must be empty with the default as placeholder');
});

echo $failed === 0 ? "\nALL 1.1.0 CHECKS PASSED\n" : "\n$failed SECTION(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
