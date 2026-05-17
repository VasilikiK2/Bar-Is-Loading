<?php
/**
 * CRON JOB - Μηνιαία αποθήκευση Excel με στατιστικά μελών
 *
 * Δημιουργεί ένα αρχείο Excel για τον προηγούμενο μήνα και το αποθηκεύει
 * στο /reports/{έτος}_{μήνας}.xlsx
 *
 * Συχνότητα εκτέλεσης: την 1η κάθε μήνα στις 02:00
 *   0 2 1 * * /usr/bin/php /path/to/gym-system/cron/monthly_export.php
 */

if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== 'CHANGE_ME_SECRET_TOKEN') { http_response_code(403); exit('Forbidden'); }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Φόρτωσε τη λογική του export
$_GET['year']  = date('Y', strtotime('first day of previous month'));
$_GET['month'] = date('n', strtotime('first day of previous month'));
$_GET['save_to_disk'] = '1';

require_once __DIR__ . '/../export_excel.php';
