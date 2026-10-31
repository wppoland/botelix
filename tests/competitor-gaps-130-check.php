<?php
/**
 * Self-check for 1.3.0: a per-product deposit amount that replaces the scheme
 * rate under the product's home scheme, for products and variations.
 *
 * Each section reports on its own. Run: php tests/competitor-gaps-130-check.php
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
    public function update_meta_data(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }
    public function save_meta_data(): void
    {
    }
}

function sanitize_key(string $key): string
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key));
}
function wp_unslash(mixed $value): mixed
{
    return $value;
}
function wp_verify_nonce(string $nonce, string $action): bool
{
    return $nonce === 'ok';
}
function current_user_can(string $cap, int $id = 0): bool
{
    return true;
}
function absint(mixed $value): int
{
    return abs((int) $value);
}
function sanitize_text_field(string $text): string
{
    return trim(strip_tags($text));
}
function wc_format_decimal(string $value): string
{
    return str_replace(',', '.', $value);
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

use Botelix\Admin\ProductMetaBox;

echo "Botelix 1.3.0 competitor gaps\n";

section('1 own amount replaces the scheme rate under the home scheme', function (): void {
    check(defined(DS::class . '::META_AMOUNT'), 'DepositService::META_AMOUNT missing');
    $svc  = service(['default_scheme' => 'de']);
    $beer = new WC_Product(60, [DS::META_TYPE => 'glass_reusable', DS::META_AMOUNT => '0.08']);
    $water = new WC_Product(61, [DS::META_TYPE => 'glass_reusable']);
    check(abs($svc->depositFor($beer) - 0.08) < 1e-9, 'beer bottle must carry 0.08, got ' . $svc->depositFor($beer));
    check(abs($svc->depositFor($water) - 0.15) < 1e-9, 'water bottle keeps the 0.15 rate, got ' . $svc->depositFor($water));
    $crate = new WC_Product(62, [DS::META_TYPE => 'glass_reusable', DS::META_UNITS => 20, DS::META_AMOUNT => '0.08']);
    check(abs($svc->depositFor($crate) - 1.60) < 1e-9, 'crate of 20 must carry 1.60, got ' . $svc->depositFor($crate));
    $free = new WC_Product(63, [DS::META_TYPE => 'glass_reusable', DS::META_AMOUNT => '0']);
    check($svc->depositFor($free) === 0.0, 'explicit 0 must charge nothing');
    $rows = $svc->cartItemData([], ['data' => $beer, 'quantity' => 2]);
    check(isset($rows[0]) && str_contains($rows[0]['value'], 'MEHRWEG') && str_contains($rows[0]['value'], '0,16'), 'cart row must use the own amount: ' . json_encode($rows));
});

section('2 own amount does not leak into another scheme or an uncovered category', function (): void {
    $svc = service(['default_scheme' => 'de']);
    $can = new WC_Product(70, [DS::META_TYPE => 'can', DS::META_AMOUNT => '0.40']);
    check(abs($svc->depositFor($can, 'at') - 0.25) < 1e-9, 'Austrian destination must charge the AT rate, got ' . $svc->depositFor($can, 'at'));
    $locked = new WC_Product(71, [DS::META_TYPE => 'can', DS::META_SCHEME => 'at', DS::META_AMOUNT => '0.30']);
    check(abs($svc->depositFor($locked) - 0.30) < 1e-9, 'scheme override is the home scheme, got ' . $svc->depositFor($locked));
    $glass = new WC_Product(72, [DS::META_TYPE => 'glass', DS::META_SCHEME => 'at', DS::META_AMOUNT => '0.30']);
    check($svc->depositFor($glass) === 0.0, 'category the scheme does not cover stays at 0');
});

section('3 variations: inherit the parent amount, or use their own', function (): void {
    $svc = service(['default_scheme' => 'de']);
    new WC_Product(80, [DS::META_TYPE => 'glass_reusable', DS::META_AMOUNT => '0.08']);
    check(abs($svc->depositFor(new WC_Product(81, [], 80)) - 0.08) < 1e-9, 'variation without a container inherits the parent amount');
    $own = new WC_Product(82, [DS::META_TYPE => 'glass_reusable', DS::META_AMOUNT => '0.15'], 80);
    check(abs($svc->depositFor($own) - 0.15) < 1e-9, 'variation with its own container uses its own amount');
});

section('4 product editor saves the amount, blank stays blank', function (): void {
    check(defined(DS::class . '::META_AMOUNT'), 'DepositService::META_AMOUNT missing');
    $box = new ProductMetaBox(service(['default_scheme' => 'de']));
    $p   = new WC_Product(90);
    $_POST = ['botelix_product_nonce' => 'ok', DS::META_TYPE => 'glass_reusable', DS::META_AMOUNT => '0,08'];
    $box->saveProductFields(90);
    check(($p->get_meta(DS::META_AMOUNT)) === '0.08', 'amount must save as 0.08, got ' . var_export($p->get_meta(DS::META_AMOUNT), true));
    $_POST[DS::META_AMOUNT] = '';
    $box->saveProductFields(90);
    check($p->get_meta(DS::META_AMOUNT) === '', 'blank must stay blank so the scheme rate applies');
    $_POST[DS::META_AMOUNT] = '-1';
    $box->saveProductFields(90);
    check($p->get_meta(DS::META_AMOUNT) === '0', 'negative must clamp to 0');

    $v = new WC_Product(91, [], 90);
    $_POST = ['security' => 'ok', DS::META_TYPE => [3 => 'glass_reusable'], DS::META_AMOUNT => [3 => '0.15']];
    $box->saveVariationFields(91, 3);
    check($v->get_meta(DS::META_AMOUNT) === '0.15', 'variation amount must save');
    $_POST = [];
});

echo $failed === 0 ? "\nALL 1.3.0 CHECKS PASSED\n" : "\n$failed SECTION(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
