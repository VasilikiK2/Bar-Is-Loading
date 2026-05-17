<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$like = "%$q%";
$stmt = db()->prepare(
    "SELECT id, first_name, last_name, email, phone, barcode
     FROM members
     WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR barcode LIKE ?
     ORDER BY first_name LIMIT 15"
);
$stmt->execute([$like, $like, $like, $like, $like]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
