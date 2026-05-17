<?php
require_once __DIR__ . '/includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$method = $_GET['method'] ?? 'all';

$where = ['p.payment_date BETWEEN ? AND ?'];
$params = [$from, $to];
if (in_array($method, ['cash','card','bank_transfer','other'], true)) {
    $where[] = 'p.payment_method = ?'; $params[] = $method;
}
$wsql = 'WHERE ' . implode(' AND ', $where);

$stmt = db()->prepare(
    "SELECT p.*, m.first_name, m.last_name, m.barcode, ms.type
     FROM payments p
     JOIN members m ON m.id = p.member_id
     LEFT JOIN memberships ms ON ms.id = p.membership_id
     $wsql
     ORDER BY p.payment_date DESC, p.id DESC"
);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$total = array_sum(array_column($payments, 'amount'));
$count = count($payments);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><i class="bi bi-cash-coin"></i> Πληρωμές</h2>
  <a href="add_payment.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Νέα Πληρωμή</a>
</div>

<form method="get" class="card card-body mb-3 shadow-sm">
  <div class="row g-2">
    <div class="col-md-3">
      <label class="form-label small">Από</label>
      <input type="date" name="from" class="form-control" value="<?= clean($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Έως</label>
      <input type="date" name="to" class="form-control" value="<?= clean($to) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Μέθοδος</label>
      <select name="method" class="form-select">
        <option value="all">Όλες</option>
        <option value="cash" <?= $method==='cash'?'selected':'' ?>>Μετρητά</option>
        <option value="card" <?= $method==='card'?'selected':'' ?>>Κάρτα</option>
        <option value="bank_transfer" <?= $method==='bank_transfer'?'selected':'' ?>>Τραπεζική</option>
        <option value="other" <?= $method==='other'?'selected':'' ?>>Άλλο</option>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Φιλτράρισμα</button>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="card text-bg-success"><div class="card-body">
    <h6 class="opacity-75">Σύνολο εισπράξεων</h6>
    <h2><?= fmt_eur($total) ?></h2></div></div></div>
  <div class="col-md-6"><div class="card text-bg-info"><div class="card-body">
    <h6 class="opacity-75">Αριθμός πληρωμών</h6>
    <h2><?= $count ?></h2></div></div></div>
</div>

<div class="card shadow-sm"><div class="card-body p-0">
  <table class="table table-hover mb-0">
    <thead class="table-light">
      <tr><th>Ημερομηνία</th><th>Μέλος</th><th>Barcode</th><th>Τύπος</th><th>Μέθοδος</th><th class="text-end">Ποσό</th><th>Σημειώσεις</th></tr>
    </thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= fmt_date($p['payment_date']) ?></td>
        <td><a href="member_view.php?id=<?= $p['member_id'] ?>"><?= clean($p['first_name'].' '.$p['last_name']) ?></a></td>
        <td><code><?= clean($p['barcode']) ?></code></td>
        <td>
          <?php if ($p['type']==='open_gym'): ?><span class="badge bg-info">Open Gym</span>
          <?php elseif ($p['type']==='personal'): ?><span class="badge bg-warning text-dark">Personal</span><?php endif; ?>
        </td>
        <td><?= clean($p['payment_method']) ?></td>
        <td class="text-end"><strong><?= fmt_eur((float)$p['amount']) ?></strong></td>
        <td><small class="text-muted"><?= clean($p['notes']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-muted py-4">Δεν υπάρχουν πληρωμές για την επιλεγμένη περίοδο</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
