<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$barcode = trim($_POST['barcode'] ?? $_GET['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['success' => false, 'message' => 'Δεν δόθηκε barcode.']);
    exit;
}

$result = process_scan($barcode);

// Πληροφορίες για display στο UI
if (isset($result['member'])) {
    $m = $result['member'];
    $result['display'] = [
        'name'    => $m['first_name'] . ' ' . $m['last_name'],
        'email'   => $m['email'],
        'barcode' => $m['barcode'],
    ];
}
if (isset($result['membership'])) {
    $ms = $result['membership'];
    if ($ms['type'] === 'open_gym') {
        $days = (int)ceil((strtotime($ms['end_date']) - time()) / 86400);
        $result['membership_info'] = [
            'type' => 'Open Gym',
            'detail' => "Λήγει " . date('d/m/Y', strtotime($ms['end_date'])) . " ($days ημέρες)",
        ];
    } else {
        $left = (int)$ms['sessions_total'] - (int)$ms['sessions_used'];
        $result['membership_info'] = [
            'type' => 'Personal Training',
            'detail' => "Προπονήσεις: {$ms['sessions_used']}/{$ms['sessions_total']} (απομένουν: $left)",
            'remaining' => $left,
        ];
    }
}

// Τρέχουσα παρουσία (μετά το scan)
$result['occupancy'] = current_occupancy();

echo json_encode($result, JSON_UNESCAPED_UNICODE);
