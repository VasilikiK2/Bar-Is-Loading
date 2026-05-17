<?php
require_once __DIR__ . '/includes/header.php';
$stats = get_dashboard_stats();

// Check-ins ανά μέρα (τελευταίες 14)
$daily = db()->query(
    "SELECT DATE(checkin_time) d, COUNT(*) n
     FROM checkins
     WHERE checkin_time >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(checkin_time)
     ORDER BY d"
)->fetchAll();

$daily_map = array_column($daily, 'n', 'd');
$labels = $values = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($d));
    $values[] = (int)($daily_map[$d] ?? 0);
}

// Έσοδα ανά τύπο τρέχοντος μήνα
$rev_type = db()->query(
    "SELECT ms.type, COALESCE(SUM(p.amount),0) total
     FROM payments p
     JOIN memberships ms ON ms.id = p.membership_id
     WHERE YEAR(p.payment_date) = YEAR(CURDATE())
       AND MONTH(p.payment_date) = MONTH(CURDATE())
     GROUP BY ms.type"
)->fetchAll();

$rev_open_gym = 0; $rev_personal = 0;
foreach ($rev_type as $r) {
    if ($r['type'] === 'open_gym') $rev_open_gym = (float)$r['total'];
    else $rev_personal = (float)$r['total'];
}

// Μέλη που λήγει η συνδρομή
$expiring = db()->query(
    "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, ms.type,
            ms.end_date, ms.sessions_used, ms.sessions_total
     FROM memberships ms
     JOIN members m ON m.id = ms.member_id
     WHERE ms.is_active = 1
       AND (
         (ms.type = 'open_gym' AND ms.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
         OR (ms.type = 'personal' AND (ms.sessions_total - ms.sessions_used) <= " . LOW_SESSIONS_THRESHOLD . "
             AND (ms.sessions_total - ms.sessions_used) > 0)
       )
     ORDER BY ms.end_date ASC LIMIT 10"
)->fetchAll();
?>
<h2 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard</h2>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-bg-primary"><div class="card-body">
      <div class="d-flex justify-content-between"><div>
        <h6 class="text-uppercase opacity-75">Check-ins σήμερα</h6>
        <h2 class="mb-0"><?= $stats['checkins_today'] ?></h2>
      </div><i class="bi bi-door-open" style="font-size:42px;opacity:.5"></i></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-bg-success"><div class="card-body">
      <div class="d-flex justify-content-between"><div>
        <h6 class="text-uppercase opacity-75">Ενεργά μέλη</h6>
        <h2 class="mb-0"><?= $stats['active_members'] ?></h2>
      </div><i class="bi bi-people-fill" style="font-size:42px;opacity:.5"></i></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-bg-warning"><div class="card-body">
      <div class="d-flex justify-content-between"><div>
        <h6 class="text-uppercase opacity-75">Έσοδα μήνα</h6>
        <h2 class="mb-0"><?= fmt_eur($stats['revenue_month']) ?></h2>
      </div><i class="bi bi-currency-euro" style="font-size:42px;opacity:.5"></i></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-bg-danger"><div class="card-body">
      <div class="d-flex justify-content-between"><div>
        <h6 class="text-uppercase opacity-75">Λήγουν σύντομα</h6>
        <h2 class="mb-0"><?= $stats['expiring_soon'] + $stats['low_sessions'] ?></h2>
      </div><i class="bi bi-exclamation-triangle" style="font-size:42px;opacity:.5"></i></div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <small class="text-muted">Check-ins εβδομάδας</small>
      <h4><?= $stats['checkins_week'] ?></h4>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <small class="text-muted">Check-ins μήνα</small>
      <h4><?= $stats['checkins_month'] ?></h4>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <small class="text-muted">Open Gym ενεργά</small>
      <h4><?= $stats['active_open_gym'] ?></h4>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card"><div class="card-body">
      <small class="text-muted">Personal ενεργά</small>
      <h4><?= $stats['active_personal'] ?></h4>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Check-ins τελευταίων 14 ημερών</strong></div>
      <div class="card-body"><canvas id="chartDaily" height="100"></canvas></div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-header bg-white"><strong>Έσοδα τρέχοντος μήνα ανά τύπο</strong></div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-6"><h4 class="text-info">Open Gym</h4><h3><?= fmt_eur($rev_open_gym) ?></h3></div>
          <div class="col-6"><h4 class="text-warning">Personal</h4><h3><?= fmt_eur($rev_personal) ?></h3></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-exclamation-triangle text-danger"></i> Λήγουν σύντομα</strong>
        <span class="badge bg-danger"><?= count($expiring) ?></span>
      </div>
      <div class="list-group list-group-flush">
        <?php if (!$expiring): ?>
          <div class="list-group-item text-muted text-center py-3">Καμία ειδοποίηση</div>
        <?php endif; ?>
        <?php foreach ($expiring as $e): ?>
          <a href="member_view.php?id=<?= $e['id'] ?>" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between">
              <div>
                <strong><?= clean($e['first_name'] . ' ' . $e['last_name']) ?></strong><br>
                <?php if ($e['type'] === 'open_gym'): ?>
                  <small class="text-info">Open Gym - λήγει <?= fmt_date($e['end_date']) ?></small>
                <?php else: ?>
                  <?php $left = (int)$e['sessions_total'] - (int)$e['sessions_used']; ?>
                  <small class="text-warning">Personal - <?= $left ?> προπονήσεις</small>
                <?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chartDaily'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Check-ins',
            data: <?= json_encode($values) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,.15)',
            fill: true,
            tension: 0.35
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
