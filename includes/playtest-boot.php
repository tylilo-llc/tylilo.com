<?php

declare(strict_types=1);

// Shared start for the playtest API files. No HTML headers: these answer in JSON.
if (!defined('TYLILO_SEND_HEADERS')) {
    define('TYLILO_SEND_HEADERS', false);
}
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/playtest.php';
