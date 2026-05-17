<?php
/**
 * Μηνιαία αναφορά Excel - χωρίς εξωτερική βιβλιοθήκη.
 * Δημιουργεί αρχείο .xls (HTML internally) που ανοίγει κανονικά στο Excel/LibreOffice.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (php_sapi_name() !== 'cli') {
    require_login();
}

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$save_to_disk = !empty($_GET['save_to_disk']);

$month_start = sprintf('%04d-%02d-01', $year, $month);
$month_end   = date('Y-m-t', strtotime($month_start));
$months_gr   = ['','Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
                   'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];
$month_name  = $months_gr[$month];

$pdo = db();

/* ============== Δεδομένα ============== */
$total_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE registration_date BETWEEN ? AND ?");
$stmt->execute([$month_start, $month_end]);
$new_members = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM checkins WHERE checkin_time BETWEEN ? AND ?");
$stmt->execute([$month_start.' 00:00:00', $month_end.' 23:59:59']);
$checkins_month = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$month_start, $month_end]);
$revenue_month = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(p.amount),0) FROM payments p
     JOIN memberships ms ON ms.id = p.membership_id
     WHERE p.payment_date BETWEEN ? AND ? AND ms.type='open_gym'");
$stmt->execute([$month_start, $month_end]);
$revenue_open = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(p.amount),0) FROM payments p
     JOIN memberships ms ON ms.id = p.membership_id
     WHERE p.payment_date BETWEEN ? AND ? AND ms.type='personal'");
$stmt->execute([$month_start, $month_end]);
$revenue_pers = (float)$stmt->fetchColumn();

// Μέλη
$stmt = $pdo->prepare("
    SELECT m.*, ms.type, ms.start_date, ms.end_date, ms.sessions_used, ms.sessions_total,
        (SELECT COUNT(*) FROM checkins c
         WHERE c.member_id = m.id AND c.checkin_time BETWEEN ? AND ?) AS month_checkins,
        (SELECT COALESCE(SUM(amount),0) FROM payments p
         WHERE p.member_id = m.id AND p.payment_date BETWEEN ? AND ?) AS month_revenue
    FROM members m
    LEFT JOIN memberships ms ON ms.member_id = m.id AND ms.is_active = 1
    ORDER BY m.last_name, m.first_name");
$stmt->execute([$month_start.' 00:00:00', $month_end.' 23:59:59', $month_start, $month_end]);
$members = $stmt->fetchAll();

// Check-ins
$stmt = $pdo->prepare("
    SELECT c.checkin_time, m.barcode, m.first_name, m.last_name, ms.type
    FROM checkins c
    JOIN members m ON m.id = c.member_id
    LEFT JOIN memberships ms ON ms.id = c.membership_id
    WHERE c.checkin_time BETWEEN ? AND ?
    ORDER BY c.checkin_time DESC");
$stmt->execute([$month_start.' 00:00:00', $month_end.' 23:59:59']);
$checkins = $stmt->fetchAll();

// Πληρωμές
$stmt = $pdo->prepare("
    SELECT p.*, m.barcode, m.first_name, m.last_name, ms.type
    FROM payments p
    JOIN members m ON m.id = p.member_id
    LEFT JOIN memberships ms ON ms.id = p.membership_id
    WHERE p.payment_date BETWEEN ? AND ?
    ORDER BY p.payment_date DESC");
$stmt->execute([$month_start, $month_end]);
$payments = $stmt->fetchAll();

/* ============== HTML Output (Excel format) ============== */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$filename = sprintf('gym_report_%04d_%02d.xls', $year, $month);

if ($save_to_disk) {
    $dir = __DIR__ . '/reports';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    ob_start();
} else {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header("Content-Disposition: attachment;filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM για Greek
}
?>
<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml>
<x:ExcelWorkbook>
  <x:ExcelWorksheets>
    <x:ExcelWorksheet><x:Name>Σύνοψη</x:Name>     <x:WorksheetOptions><x:Selected/></x:WorksheetOptions></x:ExcelWorksheet>
    <x:ExcelWorksheet><x:Name>Μέλη</x:Name>       <x:WorksheetOptions></x:WorksheetOptions></x:ExcelWorksheet>
    <x:ExcelWorksheet><x:Name>Check-ins</x:Name>  <x:WorksheetOptions></x:WorksheetOptions></x:ExcelWorksheet>
    <x:ExcelWorksheet><x:Name>Πληρωμές</x:Name>   <x:WorksheetOptions></x:WorksheetOptions></x:ExcelWorksheet>
  </x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  table { border-collapse: collapse; margin-bottom: 30px; }
  td, th { padding: 6px 10px; border: 1px solid #888; vertical-align: middle; }
  th { background: #0d6efd; color: #fff; font-weight: bold; text-align: center; }
  .title { font-size: 18pt; font-weight: bold; }
  .subtitle { font-size: 13pt; color: #666; }
  .label { background: #f0f0f0; font-weight: bold; }
  .num { text-align: right; }
  .center { text-align: center; }
  .green { background: #d1e7dd; }
  .yellow { background: #fff3cd; }
</style>
</head>
<body>

<!-- ======================= SHEET 1: ΣΥΝΟΨΗ ======================= -->
<h1 class="title">Μηνιαία Αναφορά — <?= h(get_setting('gym_name','Bar Is Loading')) ?></h1>
<p class="subtitle"><?= h($month_name) ?> <?= h((string)$year) ?></p>

<table>
  <tr><td class="label">Σύνολο ενεργών μελών</td><td class="num"><?= $total_members ?></td></tr>
  <tr><td class="label">Νέες εγγραφές μήνα</td><td class="num"><?= $new_members ?></td></tr>
  <tr><td class="label">Συνολικά check-ins μήνα</td><td class="num"><?= $checkins_month ?></td></tr>
  <tr><td class="label">Έσοδα Open Gym</td><td class="num"><?= number_format($revenue_open, 2, ',', '.') ?> €</td></tr>
  <tr><td class="label">Έσοδα Personal Training</td><td class="num"><?= number_format($revenue_pers, 2, ',', '.') ?> €</td></tr>
  <tr><td class="label green">Συνολικά έσοδα μήνα</td><td class="num green"><strong><?= number_format($revenue_month, 2, ',', '.') ?> €</strong></td></tr>
</table>

<!-- Sheet break - Excel ignores -->
<br clear="all" style="mso-special-character:line-break;page-break-before:always;">

<!-- ======================= SHEET 2: ΜΕΛΗ ======================= -->
<h2>Μέλη</h2>
<table>
  <tr>
    <th>Barcode</th><th>Όνομα</th><th>Επώνυμο</th><th>Email</th><th>Τηλέφωνο</th>
    <th>Εγγραφή</th><th>Status</th><th>Τύπος</th><th>Έναρξη</th><th>Λήξη</th>
    <th>Προπονήσεις</th><th>Check-ins μήνα</th><th>Έσοδα μήνα</th>
  </tr>
  <?php foreach ($members as $m):
    $type_label = ['open_gym'=>'Open Gym','personal'=>'Personal'][$m['type']] ?? '-';
    $status_label = ['active'=>'Ενεργό','inactive'=>'Ανενεργό','suspended'=>'Αναστολή'][$m['status']];
    $sessions = ($m['type']==='personal') ? "{$m['sessions_used']}/{$m['sessions_total']}" : '';
  ?>
  <tr>
    <td><?= h($m['barcode']) ?></td>
    <td><?= h($m['first_name']) ?></td>
    <td><?= h($m['last_name']) ?></td>
    <td><?= h($m['email']) ?></td>
    <td><?= h($m['phone']) ?></td>
    <td><?= fmt_date($m['registration_date']) ?></td>
    <td><?= h($status_label) ?></td>
    <td><?= h($type_label) ?></td>
    <td><?= $m['start_date'] ? fmt_date($m['start_date']) : '' ?></td>
    <td><?= $m['end_date']   ? fmt_date($m['end_date'])   : '' ?></td>
    <td class="center"><?= h($sessions) ?></td>
    <td class="num"><?= (int)$m['month_checkins'] ?></td>
    <td class="num"><?= number_format((float)$m['month_revenue'], 2, ',', '.') ?> €</td>
  </tr>
  <?php endforeach; ?>
</table>

<br clear="all" style="mso-special-character:line-break;page-break-before:always;">

<!-- ======================= SHEET 3: CHECK-INS ======================= -->
<h2>Check-ins</h2>
<table>
  <tr><th>Ημερομηνία</th><th>Ώρα</th><th>Barcode</th><th>Μέλος</th><th>Τύπος Συνδρομής</th></tr>
  <?php foreach ($checkins as $c): ?>
  <tr>
    <td><?= date('d/m/Y', strtotime($c['checkin_time'])) ?></td>
    <td class="center"><?= date('H:i', strtotime($c['checkin_time'])) ?></td>
    <td><?= h($c['barcode']) ?></td>
    <td><?= h($c['first_name'] . ' ' . $c['last_name']) ?></td>
    <td><?= h(['open_gym'=>'Open Gym','personal'=>'Personal'][$c['type']] ?? '-') ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<br clear="all" style="mso-special-character:line-break;page-break-before:always;">

<!-- ======================= SHEET 4: ΠΛΗΡΩΜΕΣ ======================= -->
<h2>Πληρωμές</h2>
<table>
  <tr><th>Ημερομηνία</th><th>Barcode</th><th>Μέλος</th><th>Τύπος</th><th>Μέθοδος</th><th>Ποσό</th><th>Σημειώσεις</th></tr>
  <?php foreach ($payments as $p): ?>
  <tr>
    <td><?= fmt_date($p['payment_date']) ?></td>
    <td><?= h($p['barcode']) ?></td>
    <td><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
    <td><?= h(['open_gym'=>'Open Gym','personal'=>'Personal'][$p['type']] ?? '-') ?></td>
    <td><?= h($p['payment_method']) ?></td>
    <td class="num"><?= number_format((float)$p['amount'], 2, ',', '.') ?> €</td>
    <td><?= h($p['notes']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
<?php
if ($save_to_disk) {
    $content = ob_get_clean();
    $filepath = sprintf('%s/reports/%04d_%02d.xls', __DIR__, $year, $month);
    file_put_contents($filepath, "\xEF\xBB\xBF" . $content);
    echo "Saved: $filepath\n";
}
