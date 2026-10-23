<?php
// Database connection. Edit these to match your XAMPP / MySQL setup.
// Environment variables (INV_DB_HOST, INV_DB_PORT, ...) override them when set.
return [
    'db' => [
        'host' => getenv('INV_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('INV_DB_PORT') ?: '3306',
        'name' => getenv('INV_DB_NAME') ?: 'sia_inventory',
        'user' => getenv('INV_DB_USER') ?: 'root',
        'pass' => getenv('INV_DB_PASS') !== false ? getenv('INV_DB_PASS') : 'advincula071530',
    ],
    'timezone' => 'Asia/Manila',
];
