<?php
/**
 * Autoloading: prefer Composer classmap when present. Fall back to a
 * minimal PSR-4 autoloader so the plugin still boots if vendor/ is absent.
 *
 * @package Botelix
 */

declare(strict_types=1);

namespace Botelix;

defined('ABSPATH') || exit;

$botelix_composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($botelix_composer)) {
    require_once $botelix_composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'Botelix\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
