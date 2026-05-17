<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();
            header('Location: ' . SITE_URL . '/index.php');
            exit;
        } else {
            $error = 'Λάθος όνομα χρήστη ή κωδικός.';
        }
    } else {
        $error = 'Συμπλήρωσε όλα τα πεδία.';
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Σύνδεση - <?= SITE_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#0d6efd,#0a58ca);min-height:100vh;display:flex;align-items:center}
.card{border:none;border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,.2)}
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card p-4">
        <div class="text-center mb-4">
          <h3 class="mt-2"><?= clean(get_setting('gym_name', 'Bar Is Loading')) ?></h3>
          <p class="text-muted">Σύνδεση στο σύστημα διαχείρισης</p>
        </div>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= clean($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['timeout'])): ?>
          <div class="alert alert-warning">Η σύνδεση έληξε. Παρακαλώ συνδέσου ξανά.</div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Όνομα χρήστη</label>
            <input type="text" name="username" class="form-control form-control-lg" autofocus required>
          </div>
          <div class="mb-3">
            <label class="form-label">Κωδικός</label>
            <input type="password" name="password" class="form-control form-control-lg" required>
          </div>
          <button class="btn btn-primary btn-lg w-100">
            <i class="bi bi-box-arrow-in-right"></i> Σύνδεση
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>
