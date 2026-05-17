<?php
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$type   = $_GET['type']   ?? 'all';

$where = []; $params = [];
if ($search) {
    $where[] = '(m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.barcode LIKE ?)';
    $q = "%$search%";
    array_push($params, $q, $q, $q, $q, $q);
}
if (in_array($status, ['active','inactive','suspended'], true)) {
    $where[] = 'm.status = ?'; $params[] = $status;
}
if (in_array($type, ['open_gym','personal'], true)) {
    $where[] = 'ms.type = ?'; $params[] = $type;
}
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT m.*, ms.type AS m_type, ms.end_date, ms.sessions_used, ms.sessions_total
        FROM members m
        LEFT JOIN memberships ms ON ms.member_id = m.id AND ms.is_active = 1
        $wsql
        ORDER BY m.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();
?>
<h2 class="mb-3"><i class="bi bi-people"></i> Μέλη</h2>

<?php if (!empty($_GET['deleted'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> Το μέλος διαγράφηκε επιτυχώς.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<form method="get" class="card card-body mb-3 shadow-sm">
  <div class="row g-2">
    <div class="col-md-5">
      <input type="text" name="q" class="form-control"
             placeholder="Αναζήτηση (όνομα, email, τηλέφωνο, barcode)..."
             value="<?= clean($search) ?>">
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Όλα τα status</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ενεργά</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Ανενεργά</option>
        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Σε αναστολή</option>
      </select>
    </div>
    <div class="col-md-3">
      <select name="type" class="form-select">
        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Όλοι οι τύποι</option>
        <option value="open_gym" <?= $type === 'open_gym' ? 'selected' : '' ?>>Open Gym</option>
        <option value="personal" <?= $type === 'personal' ? 'selected' : '' ?>>Personal</option>
      </select>
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>Barcode</th><th>Όνομα</th><th>Email</th><th>Τηλέφωνο</th>
          <th>Εγγραφή</th><th>Συνδρομή</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$members): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Δεν βρέθηκαν μέλη</td></tr>
        <?php endif; ?>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><code><?= clean($m['barcode']) ?></code></td>
            <td><?= clean($m['first_name'] . ' ' . $m['last_name']) ?></td>
            <td><?= clean($m['email']) ?></td>
            <td><?= clean($m['phone']) ?></td>
            <td><?= fmt_date($m['registration_date']) ?></td>
            <td>
              <?php if ($m['m_type'] === 'open_gym'): ?>
                <span class="badge bg-info">Open Gym</span>
                <small class="text-muted d-block">έως <?= fmt_date($m['end_date']) ?></small>
              <?php elseif ($m['m_type'] === 'personal'): ?>
                <?php $left = (int)$m['sessions_total'] - (int)$m['sessions_used']; ?>
                <span class="badge bg-warning text-dark">Personal</span>
                <small class="text-muted d-block"><?= $left ?>/<?= (int)$m['sessions_total'] ?> προπονήσεις</small>
              <?php else: ?>
                <span class="badge bg-secondary">Καμία</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
              $badge = ['active'=>'success','inactive'=>'secondary','suspended'=>'danger'][$m['status']];
              $label = ['active'=>'Ενεργό','inactive'=>'Ανενεργό','suspended'=>'Αναστολή'][$m['status']];
              ?>
              <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
            </td>
            <td>
              <a href="member_view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary" title="Προβολή">
                <i class="bi bi-eye"></i>
              </a>
              <a href="print_card.php?id=<?= $m['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Εκτύπωση κάρτας">
                <i class="bi bi-printer"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted mt-2">Σύνολο: <?= count($members) ?> μέλη</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
