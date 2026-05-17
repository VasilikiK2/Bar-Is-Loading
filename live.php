<?php
/**
 * ΔΗΜΟΣΙΑ σελίδα — δείχνει τρέχουσα παρουσία στο γυμναστήριο.
 * Δεν χρειάζεται login. Auto-refresh κάθε 30 δευτερόλεπτα.
 *
 * Πρόσβαση: http://localhost/gym-system/live.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Έλεγξε αν είναι ενεργοποιημένη η δημόσια σελίδα
if (get_setting('public_page_enabled', '1') !== '1') {
    http_response_code(403);
    exit('Η δημόσια σελίδα είναι απενεργοποιημένη.');
}

$gym_name  = get_setting('gym_name', 'My Gym');
$capacity  = max(1, (int)get_setting('gym_capacity', '30'));
$current   = current_occupancy();
$percent   = min(100, (int)round($current / $capacity * 100));

// Status
if ($current === 0) {
    $status_label = 'Άδειο';
    $status_color = '#198754';
    $status_emoji = '🟢';
} elseif ($percent < 40) {
    $status_label = 'Ήσυχα';
    $status_color = '#198754';
    $status_emoji = '🟢';
} elseif ($percent < 75) {
    $status_label = 'Μέτρια κίνηση';
    $status_color = '#fd7e14';
    $status_emoji = '🟡';
} else {
    $status_label = 'Πολύς κόσμος';
    $status_color = '#dc3545';
    $status_emoji = '🔴';
}

$pattern = hourly_traffic_pattern();
$current_hour = (int)date('H');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="30">
<title>Live - <?= htmlspecialchars($gym_name) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --status: <?= $status_color ?>;
}
body {
  background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
  color: #fff;
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.gym-name {
  font-size: 22px;
  font-weight: 300;
  letter-spacing: 3px;
  text-transform: uppercase;
  opacity: .8;
  margin-top: 30px;
}
.big-number {
  font-size: 180px;
  font-weight: 200;
  line-height: 1;
  letter-spacing: -8px;
  text-shadow: 0 4px 30px rgba(0,0,0,.3);
}
@media (max-width: 576px) {
  .big-number { font-size: 130px; }
}
.label {
  font-size: 24px;
  font-weight: 300;
  opacity: .9;
  margin-top: 10px;
}
.status-pill {
  display: inline-block;
  background: var(--status);
  color: #fff;
  padding: 12px 32px;
  border-radius: 50px;
  font-size: 22px;
  font-weight: 500;
  margin-top: 20px;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.capacity-bar {
  background: rgba(255,255,255,.15);
  height: 14px;
  border-radius: 10px;
  overflow: hidden;
  margin: 30px auto;
  max-width: 400px;
}
.capacity-fill {
  background: var(--status);
  height: 100%;
  width: <?= $percent ?>%;
  transition: width .5s;
  border-radius: 10px;
}
.capacity-text {
  font-size: 14px;
  opacity: .7;
}
.chart-card {
  background: rgba(255,255,255,.08);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  padding: 25px;
  margin-top: 40px;
}
.chart-title {
  text-align: center;
  font-size: 18px;
  font-weight: 400;
  opacity: .85;
  margin-bottom: 20px;
}
.hours-chart {
  display: grid;
  grid-template-columns: repeat(24, 1fr);
  gap: 3px;
  align-items: end;
  height: 120px;
}
.hour-bar {
  background: rgba(255,255,255,.3);
  border-radius: 3px 3px 0 0;
  position: relative;
  min-height: 4px;
}
.hour-bar.current {
  background: #ffc107;
  box-shadow: 0 0 12px #ffc107;
}
.hour-bar.busy { background: rgba(220,53,69,.7); }
.hour-bar.busy.current { background: #ffc107; }
.hour-labels {
  display: grid;
  grid-template-columns: repeat(24, 1fr);
  gap: 3px;
  font-size: 10px;
  opacity: .6;
  margin-top: 5px;
  text-align: center;
}
.refresh-info {
  text-align: center;
  opacity: .5;
  font-size: 12px;
  margin: 30px 0 15px;
}
.tip {
  background: rgba(255,255,255,.1);
  padding: 12px 20px;
  border-radius: 12px;
  margin-top: 20px;
  font-size: 14px;
  text-align: center;
  opacity: .85;
}
</style>
</head>
<body>
<div class="container py-3">
  <div class="text-center">
    <div class="gym-name"><?= htmlspecialchars($gym_name) ?></div>

    <div class="big-number"><?= $current ?></div>
    <div class="label">άτομα αυτή τη στιγμή</div>

    <div class="capacity-bar"><div class="capacity-fill"></div></div>
    <div class="capacity-text"><?= $current ?> / <?= $capacity ?> χωρητικότητα (<?= $percent ?>%)</div>

    <div class="status-pill"><?= $status_emoji ?> <?= $status_label ?></div>

    <?php
    // Πρόταση καλύτερης ώρας
    $max_pattern = max($pattern);
    $quiet_hours = [];
    foreach ($pattern as $h => $n) {
        if ($h >= 6 && $h <= 23 && $n > 0 && $n < $max_pattern * 0.4) {
            $quiet_hours[] = $h;
        }
    }
    if ($quiet_hours && $current > 0) {
        sort($quiet_hours);
        $hour_str = implode(', ', array_map(fn($h) => sprintf('%02d:00', $h), array_slice($quiet_hours, 0, 4)));
        echo "<div class='tip'>💡 Πιο ήσυχες ώρες: $hour_str</div>";
    }
    ?>

    <div class="chart-card">
      <div class="chart-title">Συνηθισμένη κίνηση ανά ώρα</div>
      <div class="hours-chart">
        <?php
        $max = max($pattern) ?: 1;
        $threshold_busy = $max * 0.7;
        for ($h = 0; $h < 24; $h++):
            $val   = $pattern[$h];
            $pctH  = max(3, (int)round($val / $max * 100));
            $cls   = 'hour-bar';
            if ($val >= $threshold_busy && $val > 0) $cls .= ' busy';
            if ($h === $current_hour) $cls .= ' current';
        ?>
          <div class="<?= $cls ?>" style="height: <?= $pctH ?>%"
               title="<?= sprintf('%02d:00', $h) ?> — μέσος όρος <?= $val ?>"></div>
        <?php endfor; ?>
      </div>
      <div class="hour-labels">
        <?php for ($h = 0; $h < 24; $h++): ?>
          <div><?= $h % 3 === 0 ? $h : '' ?></div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="refresh-info">
      🔄 Αυτόματη ανανέωση κάθε 30 δευτερόλεπτα
      &middot; Τελευταία ενημέρωση: <?= date('H:i:s') ?>
    </div>
  </div>
</div>
</body>
</html>
