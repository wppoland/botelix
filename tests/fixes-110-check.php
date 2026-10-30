<?php
/**
 * Self-check for the 1.1.0 fixes: defaults without a save, notice placeholders,
 * VAT-inclusive deposits, cross-border container mapping with the currency rule,
 * and the notice on block single-product templates.
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
    return true;
}
function wc_format_localized_decimal(string $value): string
{
    return $value;
}

class WC_Product
{
    /** @param array<string, mixed> $meta */
    public function __construct(private int $id, private array $meta = [], private string $taxClass = '')
    {
        $GLOBALS['products'][$id] = $this;
    }
    public function get_id(): int
    {
        return $this->id;
    }
    public function get_parent_id(): int
    {
        return 0;
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
        return null;
    }
    public function fees_api(): self
    {
        return $this;
    }
    public function add_fee(mixed $args, mixed $amount = null, bool $taxable = false): void
    {
        $this->fees[] = is_array($args) ? $args : ['id' => '', 'name' => $args, 'amount' => $amount, 'taxable' => $taxable];
    }
    /** What the customer pays for all deposit fees, fee tax included. */
    public function gross(): float
    {
        $sum = 0.0;
        foreach ($this->fees as $fee) {
            $rate = empty($fee['taxable']) ? 0.0 : array_sum(array_column(WC_Tax::get_rates((string) ($fee['tax_class'] ?? '')), 'rate'));
            $sum += round((float) $fee['amount'] * (1 + $rate / 100), 2);
        }
        return round($sum, 2);
    }
}

use Botelix\Model\Scheme;
use Botelix\Service\DepositService as DS;

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

echo $failed === 0 ? "\nALL 1.1.0 CHECKS PASSED\n" : "\n$failed SECTION(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
