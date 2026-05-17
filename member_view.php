<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/email.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
if (!$member) { echo '<div class="alert alert-danger">Δεν βρέθηκε το μέλος.</div>'; require __DIR__.'/includes/footer.php'; exit; }

// POST actions
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        $stmt = db()->prepare("UPDATE members SET first_name=?, last_name=?, email=?, phone=?, status=?, notes=? WHERE id=?");
        $stmt->execute([
            trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['email']),
            trim($_POST['phone']), $_POST['status'], trim($_POST['notes'] ?? ''), $id
        ]);
        $flash = 'Τα στοιχεία ενημερώθηκαν.';
        $stmt = db()->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]); $member = $stmt->fetch();
    }
    elseif ($action === 'new_membership') {
        $type  = $_POST['type'];
        $price = (float)$_POST['price'];
        create_membership($id, $type, $price);
        $flash = 'Νέα συνδρομή ενεργοποιήθηκε.';
    }
    elseif ($action === 'send_reminder') {
        $ms = get_active_membership($id);
        if (send_payment_reminder_email($member, $ms)) {
            $flash = 'Στάλθηκε υπενθύμιση πληρωμής.';
        } else {
            $flash = 'Αποτυχία αποστολής email.';
        }
    }
    elseif ($action === 'delete') {
        // Διαγραφή μέλους (επιβεβαιωμένη από τον χρήστη μέσω modal)
        $confirm = trim($_POST['confirm_name'] ?? '');
        $expected = $member['first_name'] . ' ' . $member['last_name'];

        if (strcasecmp($confirm, $expected) !== 0) {
            $flash = 'Η επιβεβαίωση δεν ταιριάζει με το όνομα του μέλους.';
        } else {
            // Διαγραφή — οι πίνακες memberships, checkins, payments, email_log
            // έχουν ON DELETE CASCADE, οπότε διαγράφονται αυτόματα
            $stmt = db()->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: members.php?deleted=1');
            exit;
        }
    }
}

$membership = get_active_membership($id);

// Ιστορικό
$payments = db()->prepare("SELECT p.*, ms.type FROM payments p
    LEFT JOIN memberships ms ON ms.id = p.membership_id
    WHERE p.member_id = ? ORDER BY p.payment_date DESC");
$payments->execute([$id]); $payments = $payments->fetchAll();

$checkins = db()->prepare("SELECT * FROM checkins WHERE member_id = ? ORDER BY checkin_time DESC LIMIT 30");
$checkins->execute([$id]); $checkins = $checkins->fetchAll();

$memberships_hist = db()->prepare("SELECT * FROM memberships WHERE member_id = ? ORDER BY id DESC");
$memberships_hist->execute([$id]); $memberships_hist = $memberships_hist->fetchAll();

$emails = db()->prepare("SELECT * FROM email_log WHERE member_id = ? ORDER BY sent_at DESC LIMIT 10");
$emails->execute([$id]); $emails = $emails->fetchAll();
?>
<?php if ($flash): ?><div class="alert alert-success"><?= clean($flash) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><i class="bi bi-person-circle"></i> <?= clean($member['first_name'] . ' ' . $member['last_name']) ?></h2>
  <div>
    <a href="print_card.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary">
      <i class="bi bi-printer"></i> Εκτύπωση Κάρτας
    </a>
    <a href="members.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Επιστροφή</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <!-- Στοιχεία -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-light"><strong>Στοιχεία</strong></div>
      <div class="card-body">
        <p class="mb-2"><strong>Barcode:</strong><br><code style="font-size:18px"><?= clean($member['barcode']) ?></code></p>
        <p class="mb-2"><i class="bi bi-envelope"></i> <?= clean($member['email']) ?></p>
        <p class="mb-2"><i class="bi bi-telephone"></i> <?= clean($member['phone']) ?></p>
        <p class="mb-2"><i class="bi bi-calendar-event"></i> Εγγραφή: <?= fmt_date($member['registration_date']) ?></p>
        <p class="mb-2">Status:
        <?php
        $badge = ['active'=>'success','inactive'=>'secondary','suspended'=>'danger'][$member['status']];
        $label = ['active'=>'Ενεργό','inactive'=>'Ανενεργό','suspended'=>'Αναστολή'][$member['status']];
        ?>
        <span class="badge bg-<?= $badge ?>"><?= $label ?></span></p>

        <button class="btn btn-sm btn-outline-primary w-100 mt-2"
                data-bs-toggle="modal" data-bs-target="#editModal">
          <i class="bi bi-pencil"></i> Επεξεργασία
        </button>
        <button class="btn btn-sm btn-outline-danger w-100 mt-2"
                data-bs-toggle="modal" data-bs-target="#deleteModal">
          <i class="bi bi-trash"></i> Διαγραφή μέλους
        </button>
      </div>
    </div>

    <!-- Ενεργή συνδρομή -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-light"><strong>Ενεργή Συνδρομή</strong></div>
      <div class="card-body">
        <?php if ($membership): ?>
          <?php if ($membership['type'] === 'open_gym'): ?>
            <span class="badge bg-info mb-2">Open Gym</span>
            <p>Από: <?= fmt_date($membership['start_date']) ?></p>
            <p>Έως: <strong><?= fmt_date($membership['end_date']) ?></strong></p>
            <?php
              $days = (strtotime($membership['end_date']) - time()) / 86400;
              $days = (int)ceil($days);
              if ($days > 0) echo "<p class='text-success'>Απομένουν <strong>$days ημέρες</strong></p>";
              else echo "<p class='text-danger'><strong>Έχει λήξει</strong></p>";
            ?>
          <?php else: ?>
            <span class="badge bg-warning text-dark mb-2">Personal Training</span>
            <p>Από: <?= fmt_date($membership['start_date']) ?></p>
            <p>Προπονήσεις: <strong><?= (int)$membership['sessions_used'] ?>/<?= (int)$membership['sessions_total'] ?></strong></p>
            <?php
              $left = (int)$membership['sessions_total'] - (int)$membership['sessions_used'];
              $cls  = $left <= LOW_SESSIONS_THRESHOLD ? 'danger' : 'success';
              echo "<p class='text-$cls'>Απομένουν <strong>$left προπονήσεις</strong></p>";
              $pct = (int)($membership['sessions_used'] / $membership['sessions_total'] * 100);
            ?>
            <div class="progress"><div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div></div>
          <?php endif; ?>
          <p class="mt-2 text-muted">Τιμή: <?= fmt_eur((float)$membership['price']) ?></p>
        <?php else: ?>
          <p class="text-muted">Δεν υπάρχει ενεργή συνδρομή</p>
        <?php endif; ?>

        <button class="btn btn-success w-100 mt-2" data-bs-toggle="modal" data-bs-target="#renewModal">
          <i class="bi bi-arrow-clockwise"></i> Νέα Συνδρομή / Ανανέωση
        </button>
        <form method="post" class="mt-2">
          <input type="hidden" name="action" value="send_reminder">
          <button class="btn btn-outline-warning w-100" type="submit">
            <i class="bi bi-envelope-paper"></i> Αποστολή υπενθύμισης
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-payments">Πληρωμές (<?= count($payments) ?>)</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-checkins">Check-ins (<?= count($checkins) ?>)</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-history">Ιστορικό Συνδρομών</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-emails">Emails</a></li>
    </ul>
    <div class="tab-content card border-top-0 shadow-sm">
      <!-- Πληρωμές -->
      <div class="tab-pane fade show active p-3" id="tab-payments">
        <a href="add_payment.php?member_id=<?= $id ?>" class="btn btn-sm btn-primary mb-2">
          <i class="bi bi-plus"></i> Νέα πληρωμή
        </a>
        <table class="table table-sm">
          <thead><tr><th>Ημερομηνία</th><th>Τύπος</th><th>Ποσό</th><th>Μέθοδος</th><th>Σημειώσεις</th></tr></thead>
          <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= fmt_date($p['payment_date']) ?></td>
              <td>
                <?php if ($p['type'] === 'open_gym'): ?><span class="badge bg-info">Open Gym</span>
                <?php elseif ($p['type'] === 'personal'): ?><span class="badge bg-warning text-dark">Personal</span>
                <?php endif; ?>
              </td>
              <td><strong><?= fmt_eur((float)$p['amount']) ?></strong></td>
              <td><?= clean($p['payment_method']) ?></td>
              <td><?= clean($p['notes']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$payments): ?><tr><td colspan="5" class="text-muted text-center py-3">Δεν υπάρχουν πληρωμές</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Check-ins -->
      <div class="tab-pane fade p-3" id="tab-checkins">
        <table class="table table-sm">
          <thead><tr><th>Ημ/νία</th><th>Ώρα</th></tr></thead>
          <tbody>
          <?php foreach ($checkins as $c): ?>
            <tr>
              <td><?= fmt_date($c['checkin_time']) ?></td>
              <td><?= date('H:i', strtotime($c['checkin_time'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$checkins): ?><tr><td colspan="2" class="text-muted text-center py-3">Δεν υπάρχουν check-ins</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Ιστορικό Συνδρομών -->
      <div class="tab-pane fade p-3" id="tab-history">
        <table class="table table-sm">
          <thead><tr><th>Από</th><th>Έως</th><th>Τύπος</th><th>Χρήση</th><th>Τιμή</th><th>Active</th></tr></thead>
          <tbody>
          <?php foreach ($memberships_hist as $h): ?>
            <tr>
              <td><?= fmt_date($h['start_date']) ?></td>
              <td><?= $h['type']==='open_gym' ? fmt_date($h['end_date']) : '-' ?></td>
              <td><?= $h['type']==='open_gym' ? 'Open Gym' : 'Personal' ?></td>
              <td><?= $h['type']==='personal' ? "{$h['sessions_used']}/{$h['sessions_total']}" : '-' ?></td>
              <td><?= fmt_eur((float)$h['price']) ?></td>
              <td><?= $h['is_active'] ? '<span class="badge bg-success">Ναι</span>' : '<span class="badge bg-secondary">Όχι</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Emails -->
      <div class="tab-pane fade p-3" id="tab-emails">
        <table class="table table-sm">
          <thead><tr><th>Ημ/νία</th><th>Τύπος</th><th>Subject</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($emails as $em): ?>
            <tr>
              <td><?= fmt_datetime($em['sent_at']) ?></td>
              <td><?= clean($em['email_type']) ?></td>
              <td><?= clean($em['subject']) ?></td>
              <td><?= $em['status']==='sent' ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Failed</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$emails): ?><tr><td colspan="4" class="text-muted text-center py-3">Δεν υπάρχουν emails</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Edit -->
<div class="modal fade" id="editModal">
<div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <div class="modal-header"><h5 class="modal-title">Επεξεργασία Μέλους</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="edit">
      <div class="row g-2">
        <div class="col-6"><label class="form-label">Όνομα</label>
          <input name="first_name" class="form-control" value="<?= clean($member['first_name']) ?>" required></div>
        <div class="col-6"><label class="form-label">Επώνυμο</label>
          <input name="last_name" class="form-control" value="<?= clean($member['last_name']) ?>" required></div>
        <div class="col-12"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= clean($member['email']) ?>" required></div>
        <div class="col-6"><label class="form-label">Τηλέφωνο</label>
          <input name="phone" class="form-control" value="<?= clean($member['phone']) ?>"></div>
        <div class="col-6"><label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active" <?= $member['status']==='active'?'selected':'' ?>>Ενεργό</option>
            <option value="inactive" <?= $member['status']==='inactive'?'selected':'' ?>>Ανενεργό</option>
            <option value="suspended" <?= $member['status']==='suspended'?'selected':'' ?>>Αναστολή</option>
          </select></div>
        <div class="col-12"><label class="form-label">Σημειώσεις</label>
          <textarea name="notes" class="form-control"><?= clean($member['notes']) ?></textarea></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary">Αποθήκευση</button>
    </div>
  </form>
</div></div></div>

<!-- Modal: Delete Member -->
<div class="modal fade" id="deleteModal">
<div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Διαγραφή Μέλους</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" name="action" value="delete">
      <div class="alert alert-danger">
        <strong>⚠️ Προσοχή!</strong><br>
        Η διαγραφή είναι <strong>οριστική</strong> και θα διαγράψει:
        <ul class="mb-0 mt-2">
          <li>Τα στοιχεία του μέλους</li>
          <li>Όλο το ιστορικό συνδρομών (<?= count($memberships_hist) ?>)</li>
          <li>Όλα τα check-ins (<?= count($checkins) ?>)</li>
          <li>Όλο το ιστορικό πληρωμών (<?= count($payments) ?>)</li>
          <li>Το ιστορικό emails</li>
        </ul>
      </div>
      <p>Για επιβεβαίωση, πληκτρολόγησε <strong>ακριβώς</strong> το όνομα του μέλους:</p>
      <p class="text-center text-muted">
        <code style="font-size:16px"><?= clean($member['first_name'] . ' ' . $member['last_name']) ?></code>
      </p>
      <input type="text" name="confirm_name" class="form-control" required
             placeholder="Πληκτρολόγησε το πλήρες όνομα..." autocomplete="off">
      <p class="text-muted small mt-2">
        💡 Εναλλακτικά, αντί για διαγραφή, μπορείς να αλλάξεις το status σε <strong>Ανενεργό</strong> (διατηρείται το ιστορικό).
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
      <button class="btn btn-danger"><i class="bi bi-trash"></i> Οριστική Διαγραφή</button>
    </div>
  </form>
</div></div></div>

<!-- Modal: New membership -->
<div class="modal fade" id="renewModal">
<div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <div class="modal-header"><h5 class="modal-title">Νέα Συνδρομή</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="new_membership">
      <div class="alert alert-info small">Η προηγούμενη συνδρομή θα απενεργοποιηθεί.</div>
      <div class="mb-3"><label class="form-label">Τύπος</label>
        <select name="type" class="form-select" required>
          <option value="open_gym">Open Gym</option>
          <option value="personal">Personal Training</option>
        </select></div>
      <div class="mb-3"><label class="form-label">Τιμή (€)</label>
        <input type="number" step="0.01" name="price" class="form-control" required
               value="<?= clean(get_setting('open_gym_price','40')) ?>"></div>
      <p class="text-muted small">Μετά μην ξεχάσεις να καταγράψεις την πληρωμή.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-success">Δημιουργία</button>
    </div>
  </form>
</div></div></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
