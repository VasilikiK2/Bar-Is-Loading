<?php
/**
 * INSTALLATION SCRIPT
 * Τρέξε αυτό μία φορά μετά τη ρύθμιση της config.php για να:
 *  - δημιουργήσει τη βάση και τους πίνακες
 *  - δημιουργήσει τον πρώτο admin χρήστη
 *
 * ΔΙΑΓΡΑΨΕ ΑΥΤΟ ΤΟ ΑΡΧΕΙΟ μετά την εγκατάσταση!
 */
require_once __DIR__ . '/config/config.php';

$step = $_GET['step'] ?? '1';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? 'admin');
    $password   = $_POST['password'] ?? '';
    $full_name  = trim($_POST['full_name'] ?? 'Administrator');

    if (strlen($password) < 6) {
        $error = 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.';
    } else {
        try {
            // Σύνδεση χωρίς συγκεκριμένη DB
            $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Εκτέλεση SQL schema
            $sql = file_get_contents(__DIR__ . '/database.sql');
            // Αφαίρεση του placeholder admin password line
            $sql = preg_replace("/INSERT INTO users.*?'admin'\);/s", '', $sql);
            $pdo->exec($sql);

            // Επανασύνδεση στη νέα βάση
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                           DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Δημιουργία admin
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')
                                   ON DUPLICATE KEY UPDATE password = VALUES(password), full_name = VALUES(full_name)");
            $stmt->execute([$username, $hash, $full_name]);

            $message = "✓ Η εγκατάσταση ολοκληρώθηκε! Συνδέσου με τα στοιχεία που έδωσες. ΔΙΑΓΡΑΨΕ ΤΟ install.php!";
            $step = 'done';
        } catch (Exception $e) {
            $error = 'Σφάλμα: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Εγκατάσταση - Gym System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow">
        <div class="card-body p-4">
          <h2 class="mb-3">Εγκατάσταση Συστήματος</h2>
          <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <a href="login.php" class="btn btn-primary">Σύνδεση</a>
          <?php else: ?>
            <p class="text-muted">Δημιούργησε τον πρώτο διαχειριστή του συστήματος.</p>
            <div class="alert alert-warning small">
              <strong>Πριν συνεχίσεις:</strong><br>
              - Επιβεβαίωσε ότι έχεις ρυθμίσει την <code>config/config.php</code> (στοιχεία MySQL)<br>
              - Έχει εγκατασταθεί το composer (<code>composer install</code>)
            </div>
            <form method="post">
              <div class="mb-3"><label class="form-label">Πλήρες όνομα</label>
                <input name="full_name" class="form-control" value="Administrator" required></div>
              <div class="mb-3"><label class="form-label">Όνομα χρήστη</label>
                <input name="username" class="form-control" value="admin" required></div>
              <div class="mb-3"><label class="form-label">Κωδικός (min 6 χαρακτήρες)</label>
                <input type="password" name="password" class="form-control" minlength="6" required></div>
              <button class="btn btn-success">Εγκατάσταση</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
