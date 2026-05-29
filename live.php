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

$gym_name  = get_setting('gym_name', 'Bar Is Loading');
$capacity  = max(1, (int)get_setting('gym_capacity', '25'));
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --status: <?= $status_color ?>;
  --status-glow: <?= $status_color ?>66;
}

* { box-sizing: border-box; }

body {
  background: #0a0e1a;
  color: #fff;
  min-height: 100vh;
  font-family: 'Inter', -apple-system, sans-serif;
  overflow-x: hidden;
}

/* Animated mesh background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 10%, #1a1f6e55 0%, transparent 60%),
    radial-gradient(ellipse 60% 50% at 80% 80%, #6b21a822 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 50% 50%, #0c4a6e33 0%, transparent 70%);
  animation: bgPulse 12s ease-in-out infinite alternate;
  pointer-events: none;
  z-index: 0;
}

@keyframes bgPulse {
  0%   { opacity: .7; transform: scale(1); }
  100% { opacity: 1;  transform: scale(1.05); }
}

.container { position: relative; z-index: 1; }

/* Header */
.gym-name {
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 5px;
  text-transform: uppercase;
  color: rgba(255,255,255,.45);
  margin-top: 40px;
}

/* Hero number */
.hero-card {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
  backdrop-filter: blur(24px);
  border-radius: 32px;
  padding: 48px 32px 40px;
  margin-top: 24px;
  position: relative;
  overflow: hidden;
}

.hero-card::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 32px;
  background: radial-gradient(ellipse 70% 50% at 50% 0%, var(--status-glow), transparent 70%);
  pointer-events: none;
}

.big-number {
  font-size: 160px;
  font-weight: 200;
  line-height: 1;
  letter-spacing: -6px;
  background: linear-gradient(180deg, #fff 40%, rgba(255,255,255,.5) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

@media (max-width: 576px) {
  .big-number { font-size: 110px; }
}

.label {
  font-size: 16px;
  font-weight: 300;
  color: rgba(255,255,255,.55);
  margin-top: 6px;
  letter-spacing: 1px;
}

/* Status pill */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: var(--status);
  color: #fff;
  padding: 10px 28px;
  border-radius: 50px;
  font-size: 18px;
  font-weight: 600;
  margin-top: 24px;
  box-shadow: 0 0 32px var(--status-glow), 0 4px 20px rgba(0,0,0,.4);
  animation: pillPulse 3s ease-in-out infinite;
}

@keyframes pillPulse {
  0%, 100% { box-shadow: 0 0 20px var(--status-glow), 0 4px 20px rgba(0,0,0,.4); }
  50%       { box-shadow: 0 0 45px var(--status-glow), 0 4px 20px rgba(0,0,0,.4); }
}

/* Progress bar */
.capacity-wrap {
  margin-top: 28px;
}
.capacity-bar {
  background: rgba(255,255,255,.1);
  height: 8px;
  border-radius: 10px;
  overflow: hidden;
  max-width: 360px;
  margin: 0 auto;
}
.capacity-fill {
  background: linear-gradient(90deg, var(--status) 0%, color-mix(in srgb, var(--status) 70%, #fff) 100%);
  height: 100%;
  width: <?= $percent ?>%;
  border-radius: 10px;
  transition: width .6s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 0 12px var(--status-glow);
}
.capacity-text {
  font-size: 13px;
  color: rgba(255,255,255,.45);
  margin-top: 10px;
  letter-spacing: .5px;
}

/* Tip */
.tip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,200,0,.08);
  border: 1px solid rgba(255,200,0,.2);
  padding: 10px 22px;
  border-radius: 14px;
  margin-top: 18px;
  font-size: 13px;
  color: rgba(255,255,255,.8);
}

/* Chart card */
.chart-card {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.07);
  backdrop-filter: blur(16px);
  border-radius: 24px;
  padding: 28px 24px 20px;
  margin-top: 20px;
}

.chart-title {
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: rgba(255,255,255,.4);
  margin-bottom: 20px;
}

.hours-chart {
  display: grid;
  grid-template-columns: repeat(24, 1fr);
  gap: 3px;
  align-items: end;
  height: 100px;
}

.hour-bar {
  background: rgba(255,255,255,.18);
  border-radius: 4px 4px 0 0;
  min-height: 4px;
  transition: background .3s;
}

.hour-bar:hover { background: rgba(255,255,255,.45); }

.hour-bar.current {
  background: #fbbf24;
  box-shadow: 0 0 14px #fbbf2466;
}

.hour-bar.busy { background: rgba(239,68,68,.65); }
.hour-bar.busy.current { background: #fbbf24; box-shadow: 0 0 14px #fbbf2466; }

.hour-labels {
  display: grid;
  grid-template-columns: repeat(24, 1fr);
  gap: 3px;
  font-size: 9px;
  color: rgba(255,255,255,.3);
  margin-top: 6px;
  text-align: center;
}

/* Footer */
.refresh-info {
  color: rgba(255,255,255,.3);
  font-size: 11px;
  letter-spacing: .5px;
  margin: 28px 0 20px;
}
</style>
</head>
<body>
<div class="container py-3" style="max-width:560px">
  <div class="text-center">
    <div class="gym-name"><?= htmlspecialchars($gym_name) ?></div>

    <div class="hero-card">
      <div class="big-number"><?= $current ?></div>
      <div class="label">άτομα αυτή τη στιγμή</div>

      <div class="status-pill"><?= $status_emoji ?> <?= $status_label ?></div>

      <div class="capacity-wrap">
        <div class="capacity-bar"><div class="capacity-fill"></div></div>
        <div class="capacity-text"><?= $current ?> / <?= $capacity ?> · <?= $percent ?>% πληρότητα</div>
      </div>

      <?php
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
    </div>

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
      🔄 Αυτόματη ανανέωση κάθε 30&Prime;
      &nbsp;·&nbsp; <?= date('H:i:s') ?>
    </div>
  </div>
</div>
</body>
</html>
