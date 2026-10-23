<?php
// Sets up the database named in config.php.
//
//   php database/install.php            Standalone: loads the shared System 1 & 2 tables
//                                       (reference/System1_and_2.sql), Inventory, and sample data.
//                                       Drops and recreates those tables.
//   php database/install.php --shared   Shared mini_erp database that System 2 already set up:
//                                       creates only Inventory's tables. Never touches Catalogue or Sales data.
//                                       Refuses if Inventory's tables already exist, unless --reset is added.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line: php database/install.php');
}

$c      = (require __DIR__ . '/../config.php')['db'];
$shared = in_array('--shared', $argv, true);

$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$c['name']}`");

/** Splits a .sql file into statements, honouring DELIMITER lines like the mysql client does. */
function sql_statements(string $file): array
{
    $statements = [];
    $delimiter  = ';';
    $buffer     = '';
    foreach (file($file) as $line) {
        $trim = trim($line);
        if ($buffer === '' && ($trim === '' || str_starts_with($trim, '--'))) {
            continue;
        }
        if (preg_match('/^(DELIMITER\s+(\S+)|CREATE DATABASE|USE\s)/i', $trim, $m)) {
            $delimiter = $m[2] ?? $delimiter;   // CREATE DATABASE / USE are skipped: config.php picks the database
            continue;
        }
        $buffer .= $line;
        if (str_ends_with(rtrim($buffer), $delimiter)) {
            $statements[] = substr(rtrim($buffer), 0, -strlen($delimiter));
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }
    return $statements;
}

function run_file(PDO $pdo, string $file): void
{
    foreach (sql_statements($file) as $sql) {
        $pdo->exec($sql);
    }
    echo 'Loaded ' . basename($file) . PHP_EOL;
}

$baseline  = ['category', 'product', 'seller', 'seller_offer', 'customer', 'sales_order', 'order_item', 'order_status_history'];
$inventory = ['inventory', 'stock_reservation', 'stock_movement'];
$tables    = $pdo->query("SELECT table_name FROM information_schema.tables
                           WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

if ($shared) {
    $missing = array_diff(['seller', 'product', 'seller_offer', 'sales_order', 'order_item', 'category'], $tables);
    if ($missing) {
        exit('The shared System 1 & 2 tables are missing (' . implode(', ', $missing) . '). Set up System 2 first.' . PHP_EOL);
    }
    if (array_intersect($inventory, $tables) && !in_array('--reset', $argv, true)) {
        exit("Inventory tables already exist in `{$c['name']}`, so nothing was changed. "
           . 'Add --reset to drop and recreate them (this deletes all stock records and history).' . PHP_EOL);
    }
    run_file($pdo, __DIR__ . '/schema.sql');
    echo "Inventory tables are ready in `{$c['name']}`. No sample data was added." . PHP_EOL;
    exit;
}

// Standalone reset. Refuse if the database holds anything else, e.g. System 2's own sales_* tables.
$foreign = array_diff($tables, $baseline, $inventory);
if ($foreign) {
    exit("`{$c['name']}` contains other tables (" . implode(', ', array_slice($foreign, 0, 5)) . '…). '
       . 'It looks like a shared database, so nothing was dropped. Use --shared, or point config.php at an empty database.' . PHP_EOL);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DROP VIEW IF EXISTS v_inventory_stock, v_orderable_offer');
foreach (array_merge($inventory, $baseline) as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

run_file($pdo, __DIR__ . '/reference/System1_and_2.sql');
run_file($pdo, __DIR__ . '/schema.sql');
if (!in_array('--no-seed', $argv, true)) {
    run_file($pdo, __DIR__ . '/seed.sql');
}
echo "Database `{$c['name']}` is ready." . PHP_EOL;
