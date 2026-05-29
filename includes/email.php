<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// PHPMailer (εγκατάσταση μέσω Composer: composer require phpmailer/phpmailer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Picqer\Barcode\BarcodeGeneratorPNG;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Παραγωγή binary PNG δεδομένων για embed στο email
 */
function generate_barcode_png(string $code): ?string {
    try {
        $generator = new BarcodeGeneratorPNG();
        return $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 80);
    } catch (\Throwable $e) {
        error_log('Barcode generation failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Γενική συνάρτηση αποστολής email
 *
 * @param array $embedded_images Πίνακας με embedded images:
 *   [ ['cid' => 'barcode', 'data' => binary, 'name' => 'barcode.png', 'type' => 'image/png'], ... ]
 */
function send_email(string $to, string $to_name, string $subject, string $html_body,
                    int $member_id, string $type = 'custom', array $embedded_images = []): bool
{
    $mail = new PHPMailer(true);
    try {
        // SMTP config
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Παράκαμψη SSL verification (για περιπτώσεις παλιού CA bundle στο XAMPP)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $to_name);

        // Embedded images (inline attachments με cid:)
        foreach ($embedded_images as $img) {
            $mail->addStringEmbeddedImage(
                $img['data'],
                $img['cid'],
                $img['name'] ?? 'image.png',
                PHPMailer::ENCODING_BASE64,
                $img['type'] ?? 'image/png'
            );
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags($html_body);

        $mail->send();
        log_email($member_id, $type, $to, $subject, 'sent');
        return true;

    } catch (Exception $e) {
        log_email($member_id, $type, $to, $subject, 'failed', $mail->ErrorInfo);
        error_log("Email send failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Καταγραφή στο email_log
 */
function log_email(int $member_id, string $type, string $to, string $subject,
                   string $status, ?string $error = null): void
{
    $stmt = db()->prepare(
        "INSERT INTO email_log (member_id, email_type, recipient, subject, status, error_message)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$member_id, $type, $to, $subject, $status, $error]);
}

/**
 * Email καλωσορίσματος (στέλνεται κατά την εγγραφή)
 * Το barcode ενσωματώνεται ως inline εικόνα μέσα στο email.
 */
function send_welcome_email(array $member, string $barcode_url = ''): bool {
    $gym_name = get_setting('gym_name', SMTP_FROM_NAME);
    $subject  = "Καλώς ήρθες στο $gym_name!";

    // Παρήγαγε την εικόνα barcode σε binary
    $barcode_png = generate_barcode_png($member['barcode']);

    $embedded = [];
    if ($barcode_png !== null) {
        $embedded[] = [
            'cid'  => 'barcode_img',
            'data' => $barcode_png,
            'name' => 'barcode.png',
            'type' => 'image/png',
        ];
    }

    $body = email_template_welcome($member, $gym_name, $barcode_png !== null);

    return send_email(
        $member['email'],
        $member['first_name'] . ' ' . $member['last_name'],
        $subject,
        $body,
        (int)$member['id'],
        'welcome',
        $embedded
    );
}

/**
 * Email υπενθύμισης πληρωμής
 */
function send_payment_reminder_email(array $member, ?array $membership = null): bool {
    $gym_name = get_setting('gym_name', SMTP_FROM_NAME);
    $subject  = "Υπενθύμιση ανανέωσης συνδρομής - $gym_name";

    $body = email_template_payment_reminder($member, $membership, $gym_name);

    return send_email(
        $member['email'],
        $member['first_name'] . ' ' . $member['last_name'],
        $subject,
        $body,
        (int)$member['id'],
        'payment_reminder'
    );
}

/**
 * Email για χαμηλό υπόλοιπο προπονήσεων
 */
function send_low_sessions_email(array $member, array $membership): bool {
    $gym_name  = get_setting('gym_name', SMTP_FROM_NAME);
    $remaining = (int)$membership['sessions_total'] - (int)$membership['sessions_used'];
    $subject   = "Σου απομένουν $remaining προπονήσεις - $gym_name";

    $body = email_template_low_sessions($member, $membership, $gym_name, $remaining);

    return send_email(
        $member['email'],
        $member['first_name'] . ' ' . $member['last_name'],
        $subject,
        $body,
        (int)$member['id'],
        'low_sessions'
    );
}

/* =====================================================
   HTML TEMPLATES
   ===================================================== */

function email_template_base(string $title, string $content, string $gym_name): string {
    $bg     = '#f4f6f8';
    $brand  = '#0d6efd';
    return <<<HTML
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>$title</title>
</head>
<body style="margin:0;padding:0;background:$bg;font-family:Arial,Helvetica,sans-serif;color:#222">
<table width="100%" cellpadding="0" cellspacing="0" style="background:$bg;padding:24px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.08)">
      <tr><td style="background:$brand;padding:24px;color:#fff;text-align:center">
        <h1 style="margin:0;font-size:24px">$gym_name</h1>
      </td></tr>
      <tr><td style="padding:32px">
        $content
      </td></tr>
      <tr><td style="background:#f8f9fa;padding:16px;text-align:center;color:#666;font-size:12px">
        © $gym_name — Αυτό είναι αυτοματοποιημένο μήνυμα, παρακαλούμε μην απαντήσετε.
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
}

function email_template_welcome(array $member, string $gym_name, bool $include_barcode = true): string {
    $first = clean($member['first_name']);
    $barcode = clean($member['barcode']);
    $membership_type = $member['membership_type'] ?? '';

    $barcode_img = $include_barcode
        ? '<div style="text-align:center;margin:24px 0;background:#fff;padding:16px;border-radius:8px;border:1px solid #ddd"><img src="cid:barcode_img" alt="Barcode" style="max-width:320px;height:auto"></div>'
        : '';

    $action_block = '';
    if ($membership_type === 'personal') {
        $action_block = <<<BOOKING
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;padding:16px;margin:24px 0;border-radius:4px">
  <p style="margin:0 0 8px 0">📅 <strong>Κλείσε την ώρα σου</strong></p>
  <p style="margin:0 0 12px 0">Ως μέλος Personal Training, μπορείς να κλείνεις τις ώρες σου online από το παρακάτω link:</p>
  <a href="https://booking-bar-is-loading.pages.dev/" style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:bold">Κλείσε την ώρα σου →</a>
</div>
BOOKING;
    } elseif ($membership_type === 'open_gym') {
        $action_block = <<<LIVE
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;padding:16px;margin:24px 0;border-radius:4px">
  <p style="margin:0 0 8px 0">🏋️ <strong>Live εικόνα γυμναστηρίου</strong></p>
  <p style="margin:0 0 12px 0">Στο παρακάτω link θα μπορείς να βλέπεις πόσα άτομα είναι στο γυμναστήριο εκείνη τη στιγμή ώστε να ξέρεις πότε είναι η καλύτερη ώρα να έρθεις και να ευχαριστηθείς την προπόνησή σου!</p>
  <a href="https://live.barisloading.com/gym-system/live.php" style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:bold">Δες live την κίνηση στο γυμναστήριο →</a>
</div>
LIVE;
    }

    $content = <<<HTML
<h2 style="color:#0d6efd;margin-top:0">Καλώς ήρθες, $first! 🎉</h2>
<p>Είμαστε χαρούμενοι που έγινες μέλος της οικογένειάς μας.</p>
<p>Στο μήνυμα αυτό θα βρεις το προσωπικό σου <strong>barcode</strong> που θα χρησιμοποιείς για να μπαίνεις στο γυμναστήριο.</p>
<div style="background:#f8f9fa;border-left:4px solid #0d6efd;padding:16px;margin:24px 0;border-radius:4px">
  <p style="margin:0"><strong>Το barcode σου:</strong></p>
  <p style="font-size:22px;font-family:monospace;letter-spacing:2px;margin:8px 0;color:#0d6efd">$barcode</p>
</div>
$barcode_img
<h3>Τι ακολουθεί;</h3>
<ul>
  <li>Φέρε αυτό το email (ή την εκτυπωμένη κάρτα) στην επόμενη επίσκεψη.</li>
  <li>Σκάναρε το barcode στην είσοδο και στην έξοδο κάθε φορά που έρχεσαι για προπόνηση.</li>
  <li>Σε ένα μήνα θα λάβεις υπενθύμιση για ανανέωση συνδρομής.</li>
</ul>
$action_block
<p>Καλές προπονήσεις! 💪</p>
HTML;

    return email_template_base("Καλώς ήρθες στο $gym_name", $content, $gym_name);
}

function email_template_payment_reminder(array $member, ?array $membership, string $gym_name): string {
    $first = clean($member['first_name']);
    $info  = '';

    if ($membership) {
        if ($membership['type'] === 'open_gym') {
            $end = fmt_date($membership['end_date']);
            $info = "<p>Η συνδρομή σου <strong>Open Gym</strong> λήγει στις <strong>$end</strong>.</p>";
        } else {
            $remaining = (int)$membership['sessions_total'] - (int)$membership['sessions_used'];
            $info = "<p>Σου απομένουν <strong>$remaining</strong> προπονήσεις από το πακέτο <strong>Personal Training</strong>.</p>";
        }
    }

    $content = <<<HTML
<h2 style="color:#0d6efd;margin-top:0">Γεια σου $first 👋</h2>
<p>Έχει περάσει ένας μήνας από την εγγραφή σου. Είναι ώρα να ανανεώσεις τη συνδρομή σου για να συνεχίσεις να απολαμβάνεις τις προπονήσεις σου.</p>
$info
<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:16px;margin:24px 0;border-radius:4px">
  <p style="margin:0"><strong>💳 Πέρασε από τη γραμματεία για ανανέωση</strong></p>
</div>
<p>Αν έχεις ήδη ανανεώσει, αγνόησε αυτό το μήνυμα.</p>
<p>Σε περιμένουμε για νέες προπονήσεις! 💪</p>
HTML;

    return email_template_base("Υπενθύμιση Ανανέωσης - $gym_name", $content, $gym_name);
}

function email_template_low_sessions(array $member, array $membership, string $gym_name, int $remaining): string {
    $first = clean($member['first_name']);
    $content = <<<HTML
<h2 style="color:#0d6efd;margin-top:0">Γεια σου $first ⏰</h2>
<p>Σε ενημερώνουμε ότι σου απομένουν μόλις <strong style="color:#dc3545;font-size:20px">$remaining προπονήσεις</strong> από το πακέτο Personal Training.</p>
<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:16px;margin:24px 0;border-radius:4px">
  <p style="margin:0">Πέρασε από τη γραμματεία για να ανανεώσεις το πακέτο σου πριν τελειώσει.</p>
</div>
<p>Συνέχισε δυνατά! 💪</p>
HTML;
    return email_template_base("Λίγες προπονήσεις απομένουν - $gym_name", $content, $gym_name);
}
