<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_login();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= SITE_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= SITE_URL ?>/dashboard.php">
      <?= clean(get_setting('gym_name', 'Gym')) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/index.php"><i class="bi bi-upc-scan"></i> Scanner</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'members.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/members.php"><i class="bi bi-people"></i> Μέλη</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'register.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/register.php"><i class="bi bi-person-plus"></i> Εγγραφή</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'payments.php' ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/payments.php"><i class="bi bi-cash-coin"></i> Πληρωμές</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= in_array($current_page, ['reports.php','export_excel.php']) ? 'active' : '' ?>"
             href="<?= SITE_URL ?>/reports.php"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/live.php" target="_blank">
            <i class="bi bi-broadcast"></i> Live Page
          </a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <span class="navbar-text me-3">
            <i class="bi bi-person-circle"></i> <?= clean($_SESSION['full_name'] ?? '') ?>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-right"></i> Έξοδος
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container-fluid px-4">
