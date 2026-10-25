<?php
// get_sale_items.php
require_once __DIR__ . '/config/app.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$saleId = (int)($_GET['sale_id'] ?? 0);
if (!$saleId) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit;
}

$items = dbFetchAll("SELECT si.*, p.title, p.product_code, p.unit
                     FROM sale_items si
                     JOIN products p ON p.id = si.product_id
                     WHERE si.sale_id = ?", [$saleId]);

echo json_encode(['success' => true, 'items' => $items]);
exit;
