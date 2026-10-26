<?php
/**
 * Uninstall routine: clean up plugin options and transient data.
 *
 * @package Botelix
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('botelix_settings');
