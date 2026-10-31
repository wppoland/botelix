<?php
/**
 * Self-check for 1.5.0: crate / outer packaging deposit and shortcodes.
 *
 * Each section reports on its own. Run: php tests/competitor-gaps-150-check.php
 *
 * @package Botelix
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BOTELIX_DIR', __DIR__ . '/../');

require_once __DIR__ . '/../autoload.php';

$GLOBALS['options']    = [];
$GLOBALS['currency']   = 'EUR';
$GLOBALS['hooks']      = [];
$GLOBALS['shortcodes'] = [];
$GLOBALS['products']   = [];
$GLOBALS['queried']    = 0;
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
function add_shortcode(string $tag, mixed $callback): void
{
    $GLOBALS['shortcodes'][$tag] = $callback;
}
function shortcode_atts(array $pairs, array $atts, string $shortcode = ''): array
{
    $out = [];
    foreach ($pairs as $name => $default) {
        $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
    }
    return $out;
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
    return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key));
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

use Botelix\Service\DepositService as DS;
use Botelix\Admin\ProductMetaBox;

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

echo "Botelix 1.5.0 competitor gaps (crates and shortcodes)\n";

section('1 crate deposit adds to container deposits', function (): void {
    check(defined(DS::class . '::META_CRATE_AMOUNT'), 'DepositService::META_CRATE_AMOUNT missing');
    $svc = service(['default_scheme' => 'de']);

    // 20 bottles of beer (0.08 EUR each) + 1.50 EUR crate deposit = 3.10 EUR total
    $beerCrate = new WC_Product(101, [
        DS::META_TYPE         => 'glass_reusable',
        DS::META_UNITS        => 20,
        DS::META_AMOUNT       => '0.08',
        DS::META_CRATE_AMOUNT => '1.50',
    ]);
    check(abs($svc->depositFor($beerCrate) - 3.10) < 1e-9, 'crate of 20 beer bottles must carry 3.10, got ' . $svc->depositFor($beerCrate));

    // 24 bottles of beer (0.08 EUR each) + 1.50 EUR crate deposit = 3.42 EUR total
    $crate24 = new WC_Product(102, [
        DS::META_TYPE         => 'glass_reusable',
        DS::META_UNITS        => 24,
        DS::META_AMOUNT       => '0.08',
        DS::META_CRATE_AMOUNT => '1.50',
    ]);
    check(abs($svc->depositFor($crate24) - 3.42) < 1e-9, 'crate of 24 beer bottles must carry 3.42, got ' . $svc->depositFor($crate24));

    // Standalone empty crate (no bottles) = 1.50 EUR
    $emptyCrate = new WC_Product(103, [
        DS::META_CRATE_AMOUNT => '1.50',
    ]);
    check(abs($svc->depositFor($emptyCrate) - 1.50) < 1e-9, 'standalone crate must carry 1.50, got ' . $svc->depositFor($emptyCrate));

    // Cart row line item data includes total
    $rows = $svc->cartItemData([], ['data' => $beerCrate, 'quantity' => 1]);
    check(isset($rows[0]) && str_contains($rows[0]['value'], '3,10'), 'cart row must reflect the crate + bottles deposit: ' . json_encode($rows));
});

section('2 variation inherits parent crate deposit when not set', function (): void {
    $svc = service(['default_scheme' => 'de']);

    $parent = new WC_Product(110, [
        DS::META_TYPE         => 'glass_reusable',
        DS::META_UNITS        => 20,
        DS::META_AMOUNT       => '0.08',
        DS::META_CRATE_AMOUNT => '1.50',
    ]);
    $variation = new WC_Product(111, [], 110);

    check(abs($svc->depositFor($variation) - 3.10) < 1e-9, 'variation inherits parent crate deposit, got ' . $svc->depositFor($variation));

    // Variation with own crate amount overrides parent
    $varOwn = new WC_Product(112, [
        DS::META_TYPE         => 'glass_reusable',
        DS::META_UNITS        => 20,
        DS::META_AMOUNT       => '0.08',
        DS::META_CRATE_AMOUNT => '3.00',
    ], 110);
    check(abs($svc->depositFor($varOwn) - 4.60) < 1e-9, 'variation with own crate deposit carries 4.60, got ' . $svc->depositFor($varOwn));
});

section('3 shortcode [botelix_deposit] renders notice', function (): void {
    $svc = service(['default_scheme' => 'de']);
    $svc->registerHooks();

    check(isset($GLOBALS['shortcodes']['botelix_deposit']), 'botelix_deposit shortcode not registered');

    $product = new WC_Product(120, [
        DS::META_TYPE => 'glass_reusable',
        DS::META_UNITS => 1,
    ]);

    // Test with explicit id attribute
    $out = $svc->renderShortcode(['id' => 120]);
    check(str_contains($out, 'botelix-deposit-notice') && str_contains($out, '0,15'), 'shortcode with id must render notice, got: ' . $out);

    // Test with global product
    $GLOBALS['product'] = $product;
    $outGlobal = $svc->renderShortcode([]);
    check(str_contains($outGlobal, 'botelix-deposit-notice') && str_contains($outGlobal, '0,15'), 'shortcode with global product must render notice, got: ' . $outGlobal);

    // Product with 0 deposit gives empty string
    $noDep = new WC_Product(121, []);
    $outNone = $svc->renderShortcode(['id' => 121]);
    check($outNone === '', 'product with no deposit returns empty string');
});

section('4 shortcode [botelix_packaging_type] renders EINWEG / MEHRWEG', function (): void {
    $svc = service(['default_scheme' => 'de']);
    $svc->registerHooks();

    check(isset($GLOBALS['shortcodes']['botelix_packaging_type']), 'botelix_packaging_type shortcode not registered');

    $mehrweg = new WC_Product(130, [DS::META_TYPE => 'glass_reusable']);
    $einweg  = new WC_Product(131, [DS::META_TYPE => 'can']);
    $noType  = new WC_Product(132, []);

    $outM = $svc->renderPackagingShortcode(['id' => 130]);
    check(str_contains($outM, 'MEHRWEG'), 'expected MEHRWEG, got: ' . $outM);

    $outE = $svc->renderPackagingShortcode(['id' => 131]);
    check(str_contains($outE, 'EINWEG'), 'expected EINWEG, got: ' . $outE);

    $outNone = $svc->renderPackagingShortcode(['id' => 132]);
    check($outNone === '', 'expected empty for unflagged product, got: ' . $outNone);
});

section('5 ProductMetaBox saves crate deposit on product and variation', function (): void {
    $svc = service(['default_scheme' => 'de']);
    $box = new ProductMetaBox($svc);

    $product = new WC_Product(140);
    $_POST = [
        'botelix_product_nonce'           => 'ok',
        DS::META_SCHEME                   => 'de',
        DS::META_TYPE                     => 'glass_reusable',
        DS::META_UNITS                    => '20',
        DS::META_AMOUNT                   => '0.08',
        DS::META_CRATE_AMOUNT             => '1.50',
    ];
    $box->saveProductFields(140);
    check($product->get_meta(DS::META_CRATE_AMOUNT) === '1.5', 'product crate amount must be 1.5, got ' . var_export($product->get_meta(DS::META_CRATE_AMOUNT), true));

    $variation = new WC_Product(141, [], 140);
    $_POST = [
        'security'                        => 'ok',
        DS::META_TYPE                     => [0 => 'glass_reusable'],
        DS::META_UNITS                    => [0 => '24'],
        DS::META_AMOUNT                   => [0 => '0.08'],
        DS::META_CRATE_AMOUNT             => [0 => '1.50'],
    ];
    $box->saveVariationFields(141, 0);
    check($variation->get_meta(DS::META_CRATE_AMOUNT) === '1.5', 'variation crate amount must be 1.5, got ' . var_export($variation->get_meta(DS::META_CRATE_AMOUNT), true));
});

echo "\nResult: " . ($failed === 0 ? "ALL GREEN\n" : "$failed FAILED\n");
if ($failed > 0) {
    exit(1);
}
