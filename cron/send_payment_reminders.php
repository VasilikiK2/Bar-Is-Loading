<?php
/**
 * CRON JOB - Αποστολή email υπενθύμισης πληρωμής
 *
 * Στέλνει email σε μέλη που:
 *  - Έχει περάσει ένας μήνας από την εγγραφή τους
 *    Ή
 *  - Η συνδρομή τους Open Gym λήγει σήμερα/έχει λήξει
 *    Ή
 *  - Δεν έχουν ενεργή συνδρομή και είναι ενεργά μέλη
 *
 * Συχνότητα εκτέλεσης: 1 φορά την ημέρα (π.χ. 09:00)
 *
 * Εγκατάσταση (Linux crontab -e):
 *   0 9 * * * /usr/bin/php /path/to/gym-system/cron/send_payment_reminders.php
 *
 * Εγκατάσταση (Windows Task Scheduler):
 *   php.exe C:\path\to\gym-system\cron\send_payment_reminders.php
 */

// Επιτρέπει εκτέλεση μόνο από CLI ή με secret token
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== 'CHANGE_ME_SECRET_TOKEN') {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

$pdo = db();
$today = date('Y-m-d');
$sent = 0; $failed = 0;

echo "[".date('Y-m-d H:i:s')."] Έναρξη αποστολής υπενθυμίσεων\n";

// --- 1. Μέλη που πέρασαν ΕΞΑΚΤΑ 30 ημέρες από την εγγραφή τους
$stmt = $pdo->prepare("
    SELECT m.*
    FROM members m
    WHERE m.status = 'active'
      AND DATE(m.registration_date) <= DATE_SUB(?, INTERVAL ? DAY)
      AND DATE(m.registration_date) >= DATE_SUB(?, INTERVAL (? + 7) DAY)
      AND NOT EXISTS (
          SELECT 1 FROM email_log el
          WHERE el.member_id = m.id
            AND el.email_type = 'payment_reminder'
            AND DATE(el.sent_at) >= DATE_SUB(?, INTERVAL 7 DAY)
      )
");
$stmt->execute([$today, REMINDER_DAYS_AFTER, $today, REMINDER_DAYS_AFTER, $today]);
$rows = $stmt->fetchAll();

foreach ($rows as $member) {
    $ms = get_active_membership((int)$member['id']);
    echo " > 30ήμερη υπενθύμιση: {$member['email']} ... ";
    if (send_payment_reminder_email($member, $ms)) { $sent++; echo "OK\n"; }
    else { $failed++; echo "FAILED\n"; }
}

// --- 2. Open Gym που λήγει σήμερα ή τις επόμενες 5 ημέρες
$stmt = $pdo->prepare("
    SELECT DISTINCT m.*
    FROM members m
    JOIN memberships ms ON ms.member_id = m.id
    WHERE m.status = 'active'
      AND ms.is_active = 1
      AND ms.type = 'open_gym'
      AND ms.end_date BETWEEN ? AND DATE_ADD(?, INTERVAL 5 DAY)
      AND NOT EXISTS (
          SELECT 1 FROM email_log el
          WHERE el.member_id = m.id
            AND el.email_type = 'payment_reminder'
            AND DATE(el.sent_at) >= DATE_SUB(?, INTERVAL 7 DAY)
      )
");
$stmt->execute([$today, $today, $today]);
$rows = $stmt->fetchAll();

foreach ($rows as $member) {
    $ms = get_active_membership((int)$member['id']);
    echo " > Λήξη Open Gym: {$member['email']} ... ";
    if (send_payment_reminder_email($member, $ms)) { $sent++; echo "OK\n"; }
    else { $failed++; echo "FAILED\n"; }
}

// --- 3. Personal που έχουν εξαντλήσει τις προπονήσεις (0 remaining)
$stmt = $pdo->prepare("
    SELECT DISTINCT m.*
    FROM members m
    JOIN memberships ms ON ms.member_id = m.id
    WHERE m.status = 'active'
      AND ms.is_active = 1
      AND ms.type = 'personal'
      AND ms.sessions_used >= ms.sessions_total
      AND NOT EXISTS (
          SELECT 1 FROM email_log el
          WHERE el.member_id = m.id
            AND el.email_type = 'payment_reminder'
            AND DATE(el.sent_at) >= DATE_SUB(?, INTERVAL 7 DAY)
      )
");
$stmt->execute([$today]);
$rows = $stmt->fetchAll();

foreach ($rows as $member) {
    $ms = get_active_membership((int)$member['id']);
    echo " > Personal exhausted: {$member['email']} ... ";
    if (send_payment_reminder_email($member, $ms)) { $sent++; echo "OK\n"; }
    else { $failed++; echo "FAILED\n"; }
}

echo "[".date('Y-m-d H:i:s')."] Ολοκληρώθηκε. Στάλθηκαν: $sent, Απέτυχαν: $failed\n";
