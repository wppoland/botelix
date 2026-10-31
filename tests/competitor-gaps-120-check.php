<?php
/**
 * Self-check for 1.2.0: EINWEG / MEHRWEG inside the price (VerpackDG s. 47),
 * the deposit next to listing prices (PAngV s. 7), and the deposit per cart
 * line with a snapshot on the order item.
 *
 * Each section reports on its own. Run: php tests/competitor-gaps-120-check.php
 *
 * @package Botelix
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BOTELIX_DIR', __DIR__ . '/../');

require_once __DIR__ . '/../autoload.php';

$GLOBALS['options']  = [];
$GLOBALS['currency'] = 'EUR';
$GLOBALS['hooks']    = [];
$GLOBALS['products'] = [];
$GLOBALS['queried']  = 0;
$GLOBALS['is_product'] = false;

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
    $GLOBALS['hooks'][$tag] = $args;
}
function add_filter(string $tag, mixed $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['hooks'][$tag] = $args;
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
/** @param array<string, string> $args */
function wc_price(float $amount, array $args = []): string
{
    return '<span class="amount">' . number_format($amount, 2, ',', '') . '&nbsp;' . ($args['currency'] ?? $GLOBALS['currency']) . '</span>';
}
function wp_kses_post(string $html): string
{
    return $html;
}
function esc_html(string $text): string
{
    return htmlspecialchars($text);
}
function wp_strip_all_tags(string $html): string
{
    return strip_tags($html);
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
    return $GLOBALS['is_product'];
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

class WC_Product
{
    /** @param array<string, mixed> $meta */
    public function __construct(private int $id, private array $meta = [], private int $parentId = 0)
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
}

class WC_Order
{
    public function get_currency(): string
    {
        return 'EUR';
    }
}

class WC_Order_Item_Product
{
    /** @var array<string, mixed> */
    public array $meta = [];
    public function add_meta_data(string $key, mixed $value, bool $unique = false): void
    {
        $this->meta[$key] = $value;
    }
}

use Botelix\Service\DepositService as DS;

/** @param array<string, mixed> $settings */
function service(array $settings, string $currency = 'EUR'): DS
{
    $GLOBALS['options']  = [DS::OPTION => $settings + ['enabled' => '1', 'enable_destination_matching' => '0']];
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

echo "Botelix 1.2.0 competitor gaps\n";

section('1 hooks for listing prices, cart lines and order items are registered', function (): void {
    $GLOBALS['hooks'] = [];
    service(['default_scheme' => 'de'])->registerHooks();
    check(($GLOBALS['hooks']['woocommerce_get_price_html'] ?? 0) === 2, 'price html filter missing');
    check(($GLOBALS['hooks']['woocommerce_get_item_data'] ?? 0) === 2, 'cart item data filter missing');
    check(($GLOBALS['hooks']['woocommerce_checkout_create_order_line_item'] ?? 0) === 4, 'order line item action missing');
});

section('2 EINWEG / MEHRWEG inside the price under the German scheme only', function (): void {
    $svc = service(['default_scheme' => 'de']);
    $GLOBALS['is_product'] = true;
    $GLOBALS['queried']    = 20;
    $can   = new WC_Product(20, [DS::META_TYPE => 'can']);
    $glass = new WC_Product(21, [DS::META_TYPE => 'glass_reusable']);
    $none  = new WC_Product(22);

    $html = $svc->filterPriceHtml('<span>1,99</span>', $can);
    check(str_contains($html, '>EINWEG<'), 'single-use can must show EINWEG: ' . $html);
    check(! str_contains($html, 'botelix-deposit-notice'), 'main product must not get the notice twice: ' . $html);
    check(str_contains($svc->filterPriceHtml('<span>1,99</span>', $glass), '>MEHRWEG<'), 'reusable glass must show MEHRWEG');
    check($svc->filterPriceHtml('<span>1,99</span>', $none) === '<span>1,99</span>', 'product without a container must stay untouched');

    $at = service(['default_scheme' => 'at']);
    check(! str_contains($at->filterPriceHtml('<span>1,99</span>', $can), 'EINWEG'), 'Austrian scheme must not show the German label');
    $GLOBALS['is_product'] = false;
});

section('3 deposit next to the price on listings, not on the main product', function (): void {
    $svc = service(['default_scheme' => 'pl'], 'PLN');
    $pack = new WC_Product(30, [DS::META_TYPE => 'can', DS::META_UNITS => 6]);

    $GLOBALS['is_product'] = false;
    $GLOBALS['queried']    = 30; // a category term id may equal a product id
    $html = $svc->filterPriceHtml('<span>9,99</span>', $pack);
    check(str_contains($html, 'botelix-deposit-notice') && str_contains($html, '3,00&nbsp;PLN'), 'shop listing must show the 3,00 PLN deposit: ' . $html);

    $GLOBALS['is_product'] = true;
    $GLOBALS['queried']    = 99;
    check(str_contains($svc->filterPriceHtml('<span>9,99</span>', $pack), '3,00&nbsp;PLN'), 'related product on another product page must show the deposit');
    $GLOBALS['queried'] = 30;
    check(! str_contains($svc->filterPriceHtml('<span>9,99</span>', new WC_Product(31, [], 30)), 'botelix-deposit-notice'), 'variation of the main product must not repeat the notice');
    $GLOBALS['is_product'] = false;
    check(! str_contains($svc->filterPriceHtml('<span>9,99</span>', new WC_Product(32, [], 30)), 'botelix-deposit-notice'), 'variation loaded over AJAX (is_product false) must not repeat the notice');
    check($svc->filterPriceHtml('', $pack) === '', 'an empty price stays empty');
    check($svc->filterPriceHtml(null, $pack) === null, 'a non-string price from another plugin passes through');
});

section('4 deposit row per cart line', function (): void {
    $svc  = service(['default_scheme' => 'de']);
    $rows = $svc->cartItemData([], ['data' => new WC_Product(40, [DS::META_TYPE => 'plastic']), 'quantity' => 3]);
    check(count($rows) === 1 && $rows[0]['key'] === 'Pfand', 'one Pfand row expected: ' . json_encode($rows));
    check(str_contains($rows[0]['value'], 'EINWEG') && str_contains($rows[0]['value'], '0,75') && str_contains($rows[0]['value'], '0,25'), 'row must carry EINWEG, total and per item: ' . $rows[0]['value']);
    check(! str_contains($rows[0]['value'], '<'), 'row value must be plain text: ' . $rows[0]['value']);
    check($svc->cartItemData([], ['data' => new WC_Product(41), 'quantity' => 1]) === [], 'no row for a product without a deposit');
});

section('5 order item keeps a deposit snapshot and a visible row', function (): void {
    $svc  = service(['default_scheme' => 'de']);
    $item = new WC_Order_Item_Product();
    $svc->snapshotOrderItem($item, 'k', ['data' => new WC_Product(50, [DS::META_TYPE => 'can', DS::META_UNITS => 4]), 'quantity' => 2], new WC_Order());
    $snap = $item->meta[DS::ITEM_META] ?? null;
    check(is_array($snap), 'snapshot meta missing: ' . json_encode($item->meta));
    check($snap['scheme'] === 'de' && $snap['category'] === 'can' && $snap['packaging'] === 'EINWEG', 'snapshot identity wrong: ' . json_encode($snap));
    $glass = $svc->cartItemData([], ['data' => new WC_Product(52, [DS::META_TYPE => 'glass_reusable'], 0), 'quantity' => 3]);
    check(str_contains($glass[0]['value'], 'MEHRWEG') && str_contains($glass[0]['value'], '0,45'), 'reusable line must carry MEHRWEG and 0,45: ' . json_encode($glass));
    check(abs($snap['unit'] - 1.0) < 1e-9 && $snap['quantity'] === 2 && abs($snap['total'] - 2.0) < 1e-9, 'snapshot amounts wrong: ' . json_encode($snap));
    check(isset($item->meta['Pfand']) && str_contains((string) $item->meta['Pfand'], '2,00'), 'visible Pfand row missing: ' . json_encode($item->meta));

    $none = new WC_Order_Item_Product();
    $svc->snapshotOrderItem($none, 'k', ['data' => new WC_Product(51), 'quantity' => 1], new WC_Order());
    check($none->meta === [], 'no meta for a product without a deposit');
});

echo $failed === 0 ? "\nALL 1.2.0 CHECKS PASSED\n" : "\n$failed SECTION(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
