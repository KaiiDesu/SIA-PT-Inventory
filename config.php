<?php
// Database connection defaults. Do NOT put your password here — this file is committed to Git.
// Put your own settings in config.local.php (ignored by Git), e.g.:
//
//     <?php return ['db' => ['pass' => 'your-mysql-password']];
//
// Environment variables (INV_DB_HOST, INV_DB_PORT, ...) override both when set.
$local = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];
$db    = array_replace(
    ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'sia_inventory', 'user' => 'root', 'pass' => ''],
    $local['db'] ?? []
);

return [
    'db' => [
        'host' => getenv('INV_DB_HOST') ?: $db['host'],
        'port' => getenv('INV_DB_PORT') ?: $db['port'],
        'name' => getenv('INV_DB_NAME') ?: $db['name'],
        'user' => getenv('INV_DB_USER') ?: $db['user'],
        'pass' => getenv('INV_DB_PASS') !== false ? getenv('INV_DB_PASS') : $db['pass'],
    ],
    'timezone' => $local['timezone'] ?? 'Asia/Manila',
];
