<?php
require_once __DIR__ . '/config/config.php';
session_destroy();
header('Location: ' . SITE_URL . '/login.php');
exit;
