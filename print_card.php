<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$is_new = !empty($_GET['new']);

$stmt = db()->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
if (!$member) { http_response_code(404); echo 'Δεν βρέθηκε.'; exit; }

$gym_name = get_setting('gym_name', 'Bar Is Loading');
$gym_phone = get_setting('gym_phone', '');
$gym_addr  = get_setting('gym_address', '');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Κάρτα Μέλους - <?= clean($member['first_name'].' '.$member['last_name']) ?></title>
<style>
  body{font-family:Arial,sans-serif;background:#f0f0f0;margin:0;padding:30px;}
  .toolbar{max-width:420px;margin:0 auto 20px;text-align:center}
  .toolbar button{padding:10px 20px;background:#0d6efd;color:#fff;border:0;border-radius:6px;cursor:pointer;margin:0 5px}
  .toolbar a{padding:10px 20px;background:#6c757d;color:#fff;border:0;border-radius:6px;text-decoration:none;display:inline-block}

  .card{
    width:340px;height:220px;background:linear-gradient(135deg,#0d6efd,#0a58ca);
    color:#fff;border-radius:14px;padding:20px;margin:0 auto;
    box-shadow:0 4px 20px rgba(0,0,0,.25);position:relative;overflow:hidden;
  }
  .card::before{
    content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;
    background:rgba(255,255,255,.1);border-radius:50%;
  }
  .gym-name{font-size:14px;letter-spacing:2px;text-transform:uppercase;opacity:.85}
  .member-name{font-size:20px;font-weight:bold;margin-top:10px;line-height:1.2}
  .barcode-wrap{background:#fff;color:#000;padding:10px;border-radius:6px;margin-top:20px;text-align:center}
  .barcode-num{font-family:monospace;font-size:14px;letter-spacing:3px;margin-top:6px;color:#0d6efd}
  .footer{position:absolute;bottom:12px;left:20px;right:20px;font-size:10px;opacity:.7}

  @media print {
    body{background:#fff;padding:0;}
    .toolbar{display:none}
    .card{box-shadow:none;page-break-inside:avoid}
  }
</style>
</head>
<body>
<div class="toolbar">
  <?php if ($is_new): ?>
    <div style="background:#d1e7dd;color:#0f5132;padding:10px;border-radius:6px;margin-bottom:15px">
      ✓ Η εγγραφή ολοκληρώθηκε! Έχει σταλεί email καλωσορίσματος.
    </div>
  <?php endif; ?>
  <button onclick="window.print()">🖨️ Εκτύπωση</button>
  <a href="member_view.php?id=<?= $id ?>">← Πίσω</a>
</div>

<div class="card">
  <div class="gym-name"><?= clean($gym_name) ?></div>
  <div class="member-name"><?= clean($member['first_name'].' '.$member['last_name']) ?></div>

  <div class="barcode-wrap">
    <img src="barcode.php?code=<?= urlencode($member['barcode']) ?>" alt="Barcode" style="max-width:100%;height:50px">
    <div class="barcode-num"><?= clean($member['barcode']) ?></div>
  </div>

  <div class="footer">
    Μέλος από <?= fmt_date($member['registration_date']) ?>
    <?php if ($gym_phone): ?> &middot; <?= clean($gym_phone) ?><?php endif; ?>
  </div>
</div>
</body>
</html>
