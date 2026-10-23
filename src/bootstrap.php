<?php
declare(strict_types=1);

require_once __DIR__ . '/InventoryService.php';
require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

function db_connect(array $c, bool $withDatabase = true): PDO
{
    $dsn = "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4";
    if ($withDatabase) {
        $dsn .= ";dbname={$c['name']}";
    }
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

try {
    $db = db_connect($config['db']);
} catch (PDOException $e) {
    http_response_code(500);
    $msg = 'Cannot connect to the database "' . $config['db']['name'] . '". '
         . 'Check config.php and run database/install.php (or import schema.sql + seed.sql). '
         . 'Details: ' . $e->getMessage();
    if (PHP_SAPI === 'cli' || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', 'api.php')) {
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'error' => $msg]));
    }
    exit('<pre style="font:14px system-ui;padding:24px;white-space:pre-wrap">' . htmlspecialchars($msg) . '</pre>');
}

$inventory = new InventoryService($db);
