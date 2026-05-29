<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/email.php';

$error = $success = '';
$prices = [
    'open_gym' => (float)get_setting('open_gym_price', '45'),
    'personal' => (float)get_setting('personal_price', '120'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $type       = $_POST['type'] ?? '';
    $price      = (float)($_POST['price'] ?? 0);
    $with_payment = isset($_POST['with_payment']);
    $payment_method = $_POST['payment_method'] ?? 'cash';

    if (!$first_name || !$last_name || !$email || !$phone) {
        $error = 'Συμπλήρωσε όλα τα υποχρεωτικά πεδία.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Μη έγκυρο email.';
    } elseif (!in_array($type, ['open_gym', 'personal'], true)) {
        $error = 'Επίλεξε τύπο συνδρομής.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // Έλεγχος αν υπάρχει ήδη
            $check = $pdo->prepare("SELECT id FROM members WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                throw new Exception("Υπάρχει ήδη μέλος με αυτό το email.");
            }

            $barcode = generate_unique_barcode();
            $today   = date('Y-m-d');

            $stmt = $pdo->prepare(
                "INSERT INTO members (barcode, first_name, last_name, email, phone, registration_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([$barcode, $first_name, $last_name, $email, $phone, $today]);
            $member_id = (int)$pdo->lastInsertId();

            // Δημιουργία συνδρομής
            $membership_id = create_membership($member_id, $type, $price);

            // Καταγραφή πληρωμής (αν επιλέχθηκε)
            if ($with_payment) {
                $stmt = $pdo->prepare(
                    "INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$member_id, $membership_id, $price, $today, $payment_method, $_SESSION['user_id']]);
            }

            $pdo->commit();

            // Στείλε welcome email
            $member = [
                'id'              => $member_id,
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'email'           => $email,
                'barcode'         => $barcode,
                'membership_type' => $type,
            ];
            $barcode_url = SITE_URL . '/barcode.php?code=' . urlencode($barcode);
            send_welcome_email($member, $barcode_url);

            // Redirect στην εκτύπωση κάρτας
            header("Location: print_card.php?id=$member_id&new=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<h2 class="mb-4"><i class="bi bi-person-plus"></i> Εγγραφή Νέου Μέλους</h2>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-light"><strong>Στοιχεία Μέλους</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Όνομα *</label>
            <input type="text" name="first_name" class="form-control" required
                   value="<?= clean($_POST['first_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Επώνυμο *</label>
            <input type="text" name="last_name" class="form-control" required
                   value="<?= clean($_POST['last_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required
                   value="<?= clean($_POST['email'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Τηλέφωνο *</label>
            <input type="tel" name="phone" class="form-control" required
                   value="<?= clean($_POST['phone'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-light"><strong>Συνδρομή</strong></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Τύπος *</label>
          <select name="type" id="type-select" class="form-select" required onchange="updatePrice()">
            <option value="">Επίλεξε...</option>
            <option value="open_gym">Open Gym (30 ημέρες)</option>
            <option value="personal">Personal Training (12 προπονήσεις)</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Τιμή (€) *</label>
          <input type="number" name="price" id="price-input" class="form-control" step="0.01" min="0" required>
        </div>
        <hr>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="with_payment" id="with_payment" checked>
          <label class="form-check-label" for="with_payment">
            Καταγραφή πληρωμής τώρα
          </label>
        </div>
        <div class="mb-3">
          <label class="form-label">Τρόπος πληρωμής</label>
          <select name="payment_method" class="form-select">
            <option value="cash">Μετρητά</option>
            <option value="card">Κάρτα</option>
            <option value="bank_transfer">Τραπεζική μεταφορά</option>
            <option value="other">Άλλο</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-check-circle"></i> Εγγραφή & Δημιουργία Κάρτας
        </button>
      </div>
    </div>
  </div>
</form>

<script>
const prices = <?= json_encode($prices) ?>;
function updatePrice() {
    const type = document.getElementById('type-select').value;
    if (prices[type]) document.getElementById('price-input').value = prices[type];
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
