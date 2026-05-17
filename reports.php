<?php
require_once __DIR__ . '/includes/header.php';
?>
<h2 class="mb-4"><i class="bi bi-file-earmark-excel"></i> Μηνιαίες Αναφορές Excel</h2>

<div class="row">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <p>Δημιούργησε αναφορά Excel για συγκεκριμένο μήνα. Το αρχείο περιέχει:</p>
        <ul>
          <li><strong>Σύνοψη</strong> - συνολικά στατιστικά</li>
          <li><strong>Μέλη</strong> - όλα τα μέλη με check-ins και έσοδα μήνα</li>
          <li><strong>Check-ins</strong> - λίστα όλων των εισόδων</li>
          <li><strong>Πληρωμές</strong> - όλες οι πληρωμές μήνα</li>
        </ul>

        <form action="export_excel.php" method="get" target="_blank">
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Έτος</label>
              <select name="year" class="form-select">
                <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                  <option value="<?= $y ?>" <?= $y == date('Y')?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Μήνας</label>
              <select name="month" class="form-select">
              <?php
              $months = ['','Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος',
                         'Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];
              for ($i=1; $i<=12; $i++): ?>
                <option value="<?= $i ?>" <?= $i == date('n')?'selected':'' ?>><?= $months[$i] ?></option>
              <?php endfor; ?>
              </select>
            </div>
          </div>
          <button class="btn btn-primary mt-3 w-100">
            <i class="bi bi-download"></i> Κατέβασμα Excel
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-light"><strong>Αυτοματοποιημένες αναφορές</strong></div>
      <div class="card-body">
        <p>Το σύστημα δημιουργεί αυτόματα Excel αναφορά την 1η κάθε μήνα και την αποθηκεύει στο φάκελο <code>/reports/</code>.</p>

        <?php
        $reports_dir = __DIR__ . '/reports';
        $files = is_dir($reports_dir) ? glob($reports_dir . '/*.{xls,xlsx}', GLOB_BRACE) : [];
        rsort($files);
        ?>
        <?php if ($files): ?>
          <h6 class="mt-3">Αποθηκευμένες αναφορές:</h6>
          <div class="list-group">
          <?php foreach (array_slice($files, 0, 12) as $f): ?>
            <a href="reports/<?= basename($f) ?>" class="list-group-item list-group-item-action">
              <i class="bi bi-file-earmark-excel text-success"></i> <?= basename($f) ?>
              <small class="text-muted float-end"><?= round(filesize($f)/1024) ?> KB</small>
            </a>
          <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted">Δεν υπάρχουν αποθηκευμένες αναφορές ακόμα.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
