<?php
/**
 * Self-check for 1.4.0: ten national schemes (DK, SE, FI, EE, LV, NL, IE, HR,
 * MT, PT). Each resolves from its shipping country, carries the currency, VAT
 * flag and default rate per container category read in its act (null where
 * the category is outside the scheme), and charges that rate in a store of its
 * currency. Scheme ids are plain strings so the check reports, not fatals, on
 * a build without them.
 *
 * Run: php tests/eu-schemes-140-check.php
 *
 * @package Botelix
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BOTELIX_DIR', __DIR__ . '/../');

require_once __DIR__ . '/../autoload.php';

$GLOBALS['options']  = [];
$GLOBALS['currency'] = 'EUR';
$GLOBALS['products'] = [];

function __(string $text, string $domain = 'default'): string
{
    return $text;
}
function get_option(string $option, mixed $default = false): mixed
{
    return $GLOBALS['options'][$option] ?? $default;
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

use Botelix\Model\Scheme;
use Botelix\Service\DepositService as DS;
use Botelix\Service\SchemeResolver;

/**
 * Research table: country => [scheme id, currency, taxable, plastic, can, glass, glass_reusable].
 *
 * @var array<string, array{0: string, 1: string, 2: bool, 3: ?float, 4: ?float, 5: ?float, 6: ?float}>
 */
const EXPECTED = [
    'DK' => ['dk', 'DKK', true, 1.50, 1.00, 1.00, 1.00],
    'SE' => ['se', 'SEK', true, 2.00, 2.00, null, null],
    'FI' => ['fi', 'EUR', true, 0.20, 0.15, 0.10, 0.10],
    'EE' => ['ee', 'EUR', false, 0.10, 0.10, 0.10, 0.10],
    'LV' => ['lv', 'EUR', false, 0.10, 0.10, 0.10, 0.10],
    'NL' => ['nl', 'EUR', false, 0.15, 0.15, null, null],
    'IE' => ['ie', 'EUR', false, 0.15, 0.15, null, null],
    'HR' => ['hr', 'EUR', false, 0.10, 0.10, 0.10, null],
    'MT' => ['mt', 'EUR', false, 0.10, 0.10, 0.10, 0.10],
    'PT' => ['pt', 'EUR', false, 0.10, 0.10, null, null],
];

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
/** @param array<string, mixed> $settings */
function service(array $settings, string $currency): DS
{
    $GLOBALS['options']  = [DS::OPTION => $settings + ['enabled' => '1', 'enable_destination_matching' => '1']];
    $GLOBALS['currency'] = $currency;
    return new DS();
}

echo "Botelix 1.4.0 national schemes\n";

$categories = ['plastic', 'can', 'glass', 'glass_reusable'];
$id         = 100;

foreach (EXPECTED as $country => [$schemeId, $currency, $taxable, $plastic, $can, $glass, $reusable]) {
    section("$country resolves to '$schemeId' and matches the research table", function () use ($country, $schemeId, $currency, $taxable, $plastic, $can, $glass, $reusable, $categories, &$id): void {
        $resolver = new SchemeResolver(['default_scheme' => 'pl', 'enable_destination_matching' => '1', 'exempt_unsupported_exports' => '1']);
        check($resolver->resolveScheme($country) === $schemeId, "$country resolved to " . var_export($resolver->resolveScheme($country), true));

        $scheme = Scheme::get($schemeId);
        check($scheme !== null, "scheme '$schemeId' missing");
        check($scheme['currency'] === $currency, "currency {$scheme['currency']}, expected $currency");
        check($scheme['taxable'] === $taxable, 'taxable ' . var_export($scheme['taxable'], true));
        check(trim((string) $scheme['law']) !== '' && trim((string) $scheme['default_label']) !== '', 'law and label must be set');
        check(str_contains((string) $scheme['default_notice'], '%s'), 'notice must carry the amount placeholder');

        $expected = array_combine($categories, [$plastic, $can, $glass, $reusable]);
        check(array_keys($scheme['rates']) === $categories, 'rates must list all four categories');

        $svc = service(['default_scheme' => 'pl'], $currency);
        foreach ($expected as $category => $amount) {
            $rate = $scheme['rates'][$category];
            if ($amount === null) {
                check($rate === null, "$category must be outside the scheme");
                check($svc->amountFor($category, $schemeId) === 0.0, "$category must charge nothing");
                continue;
            }
            check(is_array($rate) && abs((float) $rate['amount'] - $amount) < 1e-9, "$category rate " . json_encode($rate) . ", expected $amount");
            check(trim((string) $rate['label']) !== '', "$category label missing");

            // Shipped to the country from a store in its currency: a 6-pack charges six deposits.
            $product = new WC_Product(++$id, [DS::META_TYPE => $category, DS::META_UNITS => 6]);
            $deposit = $svc->depositFor($product, $resolver->resolveScheme($country));
            check(abs($deposit - 6 * $amount) < 1e-9, "$category 6-pack charged $deposit, expected " . 6 * $amount);
        }

        check($svc->isTaxable($schemeId) === $taxable, 'isTaxable() must follow the scheme flag');
        check(! in_array($schemeId, array_keys($svc->unchargeableSchemes()), true), "'$schemeId' must be chargeable in a $currency store");
    });
}

section('size tiers: the product amount replaces the default size rate under its home scheme', function (): void {
    $svc = service(['default_scheme' => 'dk'], 'DKK');
    $big = new WC_Product(900, [DS::META_TYPE => 'plastic', DS::META_AMOUNT => '3.00']);
    check(abs($svc->depositFor($big) - 3.00) < 1e-9, 'DK 1.5 l bottle must carry 3.00, got ' . $svc->depositFor($big));

    $svc = service(['default_scheme' => 'ie'], 'EUR');
    $can = new WC_Product(901, [DS::META_TYPE => 'can', DS::META_AMOUNT => '0.25']);
    check(abs($svc->depositFor($can) - 0.25) < 1e-9, 'IE 0.75 l can must carry 0.25, got ' . $svc->depositFor($can));
    check(abs($svc->depositFor($can, 'pt') - 0.10) < 1e-9, 'the IE amount must not leak into the PT rate');
});

section('a scheme is charged only in its own currency', function (): void {
    $svc = service(['default_scheme' => 'pl'], 'EUR');
    $can = new WC_Product(910, [DS::META_TYPE => 'can']);
    check($svc->depositFor($can, 'dk') === 0.0, 'DKK scheme must charge nothing in an EUR store');
    check($svc->depositFor($can, 'se') === 0.0, 'SEK scheme must charge nothing in an EUR store');
});

echo $failed === 0 ? "\nALL 1.4.0 CHECKS PASSED\n" : "\n$failed SECTION(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
