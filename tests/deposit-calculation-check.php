<?php
/**
 * Self-check for Botelix deposit calculations across schemes.
 * Run: php tests/deposit-calculation-check.php
 *
 * @package Botelix
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BOTELIX_DIR', __DIR__ . '/../');

require_once __DIR__ . '/../autoload.php';

// Minimal stubs for WordPress and WooCommerce functions used in testing.
function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return $text;
}

function sanitize_key(string $key): string
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key));
}

function add_action(string $tag, $callback, int $priority = 10, int $accepted_args = 1): void
{
}

$GLOBALS['options'] = [];

function get_option(string $option, $default = false)
{
    return $GLOBALS['options'][$option] ?? $default;
}

function update_option(string $option, $value): bool
{
    $GLOBALS['options'][$option] = $value;
    return true;
}

// Test Stub for WC_Product
class WC_Product
{
    /**
     * @var array<string, mixed>
     */
    private array $meta = [];

    public function __construct(private int $id, private int $parentId = 0)
    {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_parent_id(): int
    {
        return $this->parentId;
    }

    public function get_meta(string $key, bool $single = true)
    {
        return $this->meta[$key] ?? '';
    }

    public function set_meta(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }
}

$GLOBALS['products'] = [];

function wc_get_product(int $id)
{
    return $GLOBALS['products'][$id] ?? null;
}

// ------------------- TEST SUITE -------------------
echo "Running Botelix Deposit Calculation Tests...\n";

// 1. Test Scheme Resolver
$settings = [
    'default_scheme' => 'pl',
    'enable_destination_matching' => '1',
    'exempt_unsupported_exports' => '1',
];

$resolver = new \Botelix\Service\SchemeResolver($settings);

assert($resolver->resolveScheme('PL') === 'pl', 'PL country resolves to pl');
assert($resolver->resolveScheme('RO') === 'ro', 'RO country resolves to ro');
assert($resolver->resolveScheme('AT') === 'at', 'AT country resolves to at');
assert($resolver->resolveScheme('DE') === 'de', 'DE country resolves to de');
assert($resolver->resolveScheme('SK') === 'sk', 'SK country resolves to sk');
assert($resolver->resolveScheme('HU') === 'hu', 'HU country resolves to hu');
assert($resolver->resolveScheme('US') === null, 'US country is exempt from DRS');
assert($resolver->resolveScheme('') === 'pl', 'Empty country falls back to default scheme');

echo "  ok  SchemeResolver handles all 6 countries and export exemption\n";

// 2. Test Scheme Statutory Data & Taxability
$pl = \Botelix\Model\Scheme::get('pl');
assert($pl['taxable'] === false, 'Poland deposit is non-taxable (outside VAT base)');
assert($pl['types']['pet']['amount'] === 0.50, 'Poland PET deposit is 0.50 PLN');
assert($pl['types']['glass_reusable']['amount'] === 1.00, 'Poland reusable glass is 1.00 PLN');

$ro = \Botelix\Model\Scheme::get('ro');
assert($ro['taxable'] === false, 'Romania SGR is non-taxable');
assert($ro['types']['sgr_single_use']['amount'] === 0.50, 'Romania SGR is 0.50 RON');

$at = \Botelix\Model\Scheme::get('at');
assert($at['taxable'] === true, 'Austria Einwegpfand is taxable (inclusive of VAT)');
assert($at['types']['einweg']['amount'] === 0.25, 'Austria Einwegpfand is 0.25 EUR');

$de = \Botelix\Model\Scheme::get('de');
assert($de['taxable'] === true, 'Germany Pfand is taxable');
assert($de['types']['einweg']['amount'] === 0.25, 'Germany Einwegpfand is 0.25 EUR');

$sk = \Botelix\Model\Scheme::get('sk');
assert($sk['taxable'] === false, 'Slovakia Zaloh is non-taxable');
assert($sk['types']['zaloh_single_use']['amount'] === 0.15, 'Slovakia Zaloh is 0.15 EUR');

$hu = \Botelix\Model\Scheme::get('hu');
assert($hu['taxable'] === false, 'Hungary DRS is non-taxable');
assert($hu['types']['drs_single_use']['amount'] === 50.0, 'Hungary DRS is 50 HUF');

echo "  ok  Scheme model verifies statutory amounts and VAT taxability flags\n";

// 3. Test DepositService Calculations & Packaging Multipliers
$GLOBALS['options'][\Botelix\Service\DepositService::OPTION] = [
    'enabled' => '1',
    'default_scheme' => 'pl',
    'enable_destination_matching' => '1',
];

$depositService = new \Botelix\Service\DepositService();

// Single bottle (PL PET = 0.50 PLN)
$p1 = new WC_Product(1);
$p1->set_meta(\Botelix\Service\DepositService::META_TYPE, 'pet');
$p1->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][1] = $p1;

$depositP1 = $depositService->depositFor($p1, 'pl');
assert($depositP1 === 0.50, "Expected 0.50 PLN deposit, got $depositP1");

// 6-pack of cans (PL Can = 0.50 PLN * 6 = 3.00 PLN)
$p2 = new WC_Product(2);
$p2->set_meta(\Botelix\Service\DepositService::META_TYPE, 'can');
$p2->set_meta(\Botelix\Service\DepositService::META_UNITS, 6);
$GLOBALS['products'][2] = $p2;

$depositP2 = $depositService->depositFor($p2, 'pl');
assert($depositP2 === 3.00, "Expected 3.00 PLN deposit, got $depositP2");

// Reusable glass (PL Glass = 1.00 PLN)
$p3 = new WC_Product(3);
$p3->set_meta(\Botelix\Service\DepositService::META_TYPE, 'glass_reusable');
$p3->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][3] = $p3;

$depositP3 = $depositService->depositFor($p3, 'pl');
assert($depositP3 === 1.00, "Expected 1.00 PLN deposit, got $depositP3");

echo "  ok  Single and multipack deposit multipliers calculate correctly\n";

// 4. Test Variation Parent Inheritance
$parent = new WC_Product(10);
$parent->set_meta(\Botelix\Service\DepositService::META_TYPE, 'pet');
$parent->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][10] = $parent;

// Child variation inherits parent PET
$variation1 = new WC_Product(11, 10);
$GLOBALS['products'][11] = $variation1;

$varDeposit1 = $depositService->depositFor($variation1, 'pl');
assert($varDeposit1 === 0.50, "Variation should inherit parent deposit (0.50), got $varDeposit1");

// Child variation overrides to 4-pack
$variation2 = new WC_Product(12, 10);
$variation2->set_meta(\Botelix\Service\DepositService::META_TYPE, 'pet');
$variation2->set_meta(\Botelix\Service\DepositService::META_UNITS, 4);
$GLOBALS['products'][12] = $variation2;

$varDeposit2 = $depositService->depositFor($variation2, 'pl');
assert($varDeposit2 === 2.00, "Variation override should be 2.00 PLN, got $varDeposit2");

echo "  ok  Variation parent inheritance and override logic verified\n";

// 5. Test Cross-Border Destinations
$deCan = new WC_Product(20);
$deCan->set_meta(\Botelix\Service\DepositService::META_TYPE, 'can');
$deCan->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][20] = $deCan;

$deDeposit = $depositService->depositFor($deCan, 'de');
assert($deDeposit === 0.25, "German can deposit must be 0.25 EUR");

$roBottle = new WC_Product(30);
$roBottle->set_meta(\Botelix\Service\DepositService::META_TYPE, 'sgr_single_use');
$roBottle->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][30] = $roBottle;

$roDeposit = $depositService->depositFor($roBottle, 'ro');
assert($roDeposit === 0.50, "Romanian SGR deposit must be 0.50 RON");

$skBottle = new WC_Product(40);
$skBottle->set_meta(\Botelix\Service\DepositService::META_TYPE, 'zaloh_single_use');
$skBottle->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][40] = $skBottle;

$skDeposit = $depositService->depositFor($skBottle, 'sk');
assert($skDeposit === 0.15, "Slovak Zaloh deposit must be 0.15 EUR");

$huBottle = new WC_Product(50);
$huBottle->set_meta(\Botelix\Service\DepositService::META_TYPE, 'drs_single_use');
$huBottle->set_meta(\Botelix\Service\DepositService::META_UNITS, 1);
$GLOBALS['products'][50] = $huBottle;

$huDeposit = $depositService->depositFor($huBottle, 'hu');
assert($huDeposit === 50.0, "Hungarian DRS deposit must be 50.0 HUF");

echo "  ok  Cross-border calculations match statutory rates for DE, RO, SK, HU\n";

echo "\nALL BOTELIX CHECKS PASSED!\n";
exit(0);
