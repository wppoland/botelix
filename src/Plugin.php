<?php

declare(strict_types=1);

namespace Botelix;

use Botelix\Admin\ProductMetaBox;
use Botelix\Admin\SettingsPage;
use Botelix\Service\DepositService;

defined('ABSPATH') || exit;

/**
 * Main plugin bootstrap and service coordinator.
 */
final class Plugin
{
    private static ?self $instance = null;

    private DepositService $depositService;
    private ProductMetaBox $productMetaBox;
    private SettingsPage $settingsPage;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->depositService = new DepositService();
        $this->productMetaBox = new ProductMetaBox($this->depositService);
        $this->settingsPage   = new SettingsPage();
    }

    public function boot(): void
    {
        $this->depositService->registerHooks();
        $this->productMetaBox->registerHooks();
        $this->settingsPage->registerHooks();
    }

    public function getDepositService(): DepositService
    {
        return $this->depositService;
    }
}
