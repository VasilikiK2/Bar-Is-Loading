<?php
// SCANNER PAGE - Κύρια σελίδα για σκανάρισμα barcode στην είσοδο
require_once __DIR__ . '/includes/header.php';
$occupancy = current_occupancy();
$capacity  = max(1, (int)get_setting('gym_capacity', '30'));
?>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <!-- Τρέχουσα παρουσία -->
    <div class="text-center mb-3">
      <div class="occupancy-pill">
        <i class="bi bi-people-fill text-primary" style="font-size:28px"></i>
        <div>
          <div class="num"><span id="current-occupancy"><?= $occupancy ?></span> / <?= $capacity ?></div>
          <small class="text-muted">άτομα στον χώρο τώρα</small>
        </div>
        <a href="live.php" target="_blank" class="btn btn-sm btn-outline-primary ms-2"
           title="Δημόσια σελίδα">
          <i class="bi bi-box-arrow-up-right"></i>
        </a>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-5">
        <div class="text-center mb-4">
          <i class="bi bi-upc-scan text-primary" style="font-size:80px"></i>
          <h2 class="mt-3">Σκανάρισμα Barcode</h2>
          <p class="text-muted">Σκάναρε για είσοδο. Σκάναρε ξανά για έξοδο.</p>
        </div>

        <form id="scan-form" autocomplete="off">
          <div class="input-group input-group-lg mb-3">
            <span class="input-group-text"><i class="bi bi-upc"></i></span>
            <input type="text" id="barcode-input" class="form-control form-control-lg text-center"
                   placeholder="Σκανάρισμα ή πληκτρολόγηση..." autofocus required
                   style="font-size:22px;letter-spacing:2px">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-arrow-right-circle"></i> Check-in
            </button>
          </div>
        </form>

        <div id="result-area" class="mt-4"></div>
      </div>
    </div>

    <!-- Πρόσφατα check-ins/outs -->
    <div class="card mt-4 shadow-sm">
      <div class="card-header bg-light">
        <i class="bi bi-clock-history"></i> Πρόσφατη κίνηση σήμερα
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead class="table-light">
            <tr><th>Είσοδος</th><th>Έξοδος</th><th>Μέλος</th><th>Τύπος</th><th>Διάρκεια</th></tr>
          </thead>
          <tbody>
          <?php
          $rows = db()->query(
              "SELECT c.checkin_time, c.checkout_time, c.duration_minutes,
                      m.first_name, m.last_name, ms.type
               FROM checkins c
               JOIN members m ON m.id = c.member_id
               LEFT JOIN memberships ms ON ms.id = c.membership_id
               WHERE DATE(c.checkin_time) = CURDATE()
               ORDER BY c.checkin_time DESC LIMIT 20"
          )->fetchAll();
          if (!$rows) {
              echo '<tr><td colspan="5" class="text-center text-muted py-4">Δεν υπάρχει κίνηση σήμερα</td></tr>';
          }
          foreach ($rows as $r):
              $still_inside = $r['checkout_time'] === null;
              $duration = '';
              if ($r['duration_minutes']) {
                  $h = intdiv($r['duration_minutes'], 60);
                  $m = $r['duration_minutes'] % 60;
                  $duration = $h > 0 ? "{$h}ω {$m}'" : "{$m}'";
              }
          ?>
            <tr>
              <td><?= date('H:i', strtotime($r['checkin_time'])) ?></td>
              <td>
                <?php if ($still_inside): ?>
                  <span class="badge bg-success">Στον χώρο</span>
                <?php else: ?>
                  <?= date('H:i', strtotime($r['checkout_time'])) ?>
                <?php endif; ?>
              </td>
              <td><?= clean($r['first_name'] . ' ' . $r['last_name']) ?></td>
              <td>
                <?php if ($r['type'] === 'open_gym'): ?>
                  <span class="badge bg-info">Open Gym</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Personal</span>
                <?php endif; ?>
              </td>
              <td><?= $duration ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Audio για επιτυχία/αποτυχία (προαιρετικό) -->
<audio id="snd-ok"  src="data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA="></audio>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
