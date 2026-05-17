<?php
require_once __DIR__ . '/includes/header.php';

$error = '';
$member_id = (int)($_GET['member_id'] ?? $_POST['member_id'] ?? 0);

$member = null;
if ($member_id) {
    $stmt = db()->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $date   = $_POST['payment_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'cash';
    $notes  = trim($_POST['notes'] ?? '');
    $create_new = isset($_POST['create_new_membership']);
    $type   = $_POST['type'] ?? null;

    if (!$member) {
        $error = 'Επίλεξε μέλος.';
    } elseif ($amount <= 0) {
        $error = 'Λάθος ποσό.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            if ($create_new && $type) {
                $membership_id = create_membership($member_id, $type, $amount);
            } else {
                $ms = get_active_membership($member_id);
                if (!$ms) throw new Exception('Δεν υπάρχει ενεργή συνδρομή. Δημιούργησε νέα.');
                $membership_id = (int)$ms['id'];
            }

            $stmt = $pdo->prepare(
                "INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$member_id, $membership_id, $amount, $date, $method, $notes, $_SESSION['user_id']]);

            $pdo->commit();
            header("Location: member_view.php?id=$member_id");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$current_ms = $member ? get_active_membership($member_id) : null;
?>
<h2 class="mb-3"><i class="bi bi-cash-coin"></i> Νέα Πληρωμή</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= clean($error) ?></div><?php endif; ?>

<?php if (!$member): ?>
  <!-- Επιλογή μέλους -->
  <div class="card shadow-sm"><div class="card-body">
    <p>Επίλεξε μέλος για την πληρωμή:</p>
    <input type="text" id="search-member" class="form-control mb-2" placeholder="Πληκτρολόγησε όνομα ή barcode...">
    <div id="search-results"></div>
  </div></div>
  <script>
    document.getElementById('search-member').addEventListener('input', function() {
        const q = this.value;
        if (q.length < 2) { document.getElementById('search-results').innerHTML = ''; return; }
        fetch('api/search_members.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                const div = document.getElementById('search-results');
                if (!data.length) { div.innerHTML = '<p class="text-muted">Κανένα αποτέλεσμα</p>'; return; }
                div.innerHTML = '<div class="list-group">' + data.map(m =>
                    `<a href="add_payment.php?member_id=${m.id}" class="list-group-item list-group-item-action">
                       <strong>${m.first_name} ${m.last_name}</strong> &middot; ${m.email} &middot; <code>${m.barcode}</code>
                     </a>`).join('') + '</div>';
            });
    });
  </script>
<?php else: ?>
  <form method="post" class="row g-3">
    <input type="hidden" name="member_id" value="<?= $member_id ?>">

    <div class="col-lg-6">
      <div class="card shadow-sm"><div class="card-header bg-light"><strong>Μέλος</strong></div>
      <div class="card-body">
        <p class="mb-1"><strong><?= clean($member['first_name'].' '.$member['last_name']) ?></strong></p>
        <p class="mb-1 text-muted"><?= clean($member['email']) ?></p>
        <p class="mb-0"><code><?= clean($member['barcode']) ?></code></p>
        <hr>
        <?php if ($current_ms): ?>
          <p class="mb-0"><strong>Τρέχουσα συνδρομή:</strong>
            <?php if ($current_ms['type']==='open_gym'): ?>
              Open Gym (λήγει <?= fmt_date($current_ms['end_date']) ?>)
            <?php else: ?>
              Personal (<?= (int)$current_ms['sessions_used'] ?>/<?= (int)$current_ms['sessions_total'] ?>)
            <?php endif; ?>
          </p>
        <?php else: ?>
          <p class="text-muted mb-0">Δεν υπάρχει ενεργή συνδρομή</p>
        <?php endif; ?>
      </div></div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm"><div class="card-header bg-light"><strong>Στοιχεία πληρωμής</strong></div>
      <div class="card-body">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="create_new_membership" id="newms"
                 <?= $current_ms ? '' : 'checked' ?>>
          <label class="form-check-label" for="newms">
            Δημιουργία νέας συνδρομής (ανανέωση)
          </label>
        </div>

        <div class="mb-3">
          <label class="form-label">Τύπος συνδρομής</label>
          <select name="type" class="form-select">
            <option value="open_gym">Open Gym (30 ημέρες)</option>
            <option value="personal">Personal (12 προπονήσεις)</option>
          </select>
        </div>

        <div class="row g-2">
          <div class="col-6"><label class="form-label">Ημερομηνία</label>
            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-6"><label class="form-label">Ποσό (€)</label>
            <input type="number" step="0.01" name="amount" class="form-control" required></div>
        </div>

        <div class="mb-3 mt-2"><label class="form-label">Μέθοδος</label>
          <select name="payment_method" class="form-select">
            <option value="cash">Μετρητά</option>
            <option value="card">Κάρτα</option>
            <option value="bank_transfer">Τραπεζική</option>
            <option value="other">Άλλο</option>
          </select></div>

        <div class="mb-3"><label class="form-label">Σημειώσεις</label>
          <textarea name="notes" class="form-control" rows="2"></textarea></div>

        <button type="submit" class="btn btn-success w-100">
          <i class="bi bi-check-circle"></i> Καταγραφή Πληρωμής
        </button>
      </div></div>
    </div>
  </form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
