<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/src/bootstrap.php';

$pages = [
    'dashboard'    => ['Inventory overview', 'chart',    'inventory'],
    'inventory'    => ['Stock records',      'box',      'inventory'],
    'reservations' => ['Reservations',       'clock',    'inventory'],
    'movements'    => ['Stock ledger',       'ledger',   'inventory'],
    'orders'       => ['Order simulator',    'settings', 'demo'],
    'catalog'      => ['Catalogue',          'tag',      'demo'],
];
$page = isset($pages[$_GET['page'] ?? '']) ? $_GET['page'] : 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $_POST['back'] ?? "?page=$page";
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        flash('error', 'Your session expired. Please try again.');
        redirect($back);
    }
    try {
        require_once __DIR__ . '/src/actions.php';
        handle_action($inventory, $db, (string) ($_POST['action'] ?? ''), $_POST);
    } catch (InventoryException $e) {
        flash('error', $e->getMessage(), $e->details);
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect($back);
}

require __DIR__ . '/views/layout.php';
