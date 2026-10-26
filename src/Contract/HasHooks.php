<?php

declare(strict_types=1);

namespace Botelix\Contract;

defined('ABSPATH') || exit;

interface HasHooks
{
    public function registerHooks(): void;
}
