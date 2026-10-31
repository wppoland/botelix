<?php

/**
 * The settings screen is shown to manage_woocommerce, so saving it through
 * options.php must ask for the same capability, or a shop manager's every
 * save dies with "Sorry, you are not allowed to manage options for this site."
 *
 * Run: php tests/settings-capability-check.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

require_once __DIR__ . '/../autoload.php';

$filters = [];

function add_action(string $tag, mixed $callback, int $priority = 10, int $args = 1): void
{
}
function add_filter(string $tag, mixed $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['filters'][$tag] = $callback;
}

$page = (new ReflectionClass(Botelix\Admin\SettingsPage::class))->newInstanceWithoutConstructor();
$page->registerHooks();

// options.php: apply_filters("option_page_capability_{$option_page}", 'manage_options').
$hook = 'option_page_capability_' . Botelix\Admin\SettingsPage::PAGE_SLUG;
$cap  = isset($filters[$hook]) ? ($filters[$hook])('manage_options') : 'manage_options';

if ($cap !== 'manage_woocommerce') {
    echo "FAIL: options.php requires {$cap} to save the settings\n";
    exit(1);
}

echo "OK: a shop manager can save the settings\n";
