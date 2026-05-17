<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Καθαρισμός input (αποτροπή XSS)
 */
function clean(?string $value): string {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

/**
 * Έλεγχος αν ο χρήστης είναι συνδεδεμένος
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    // Session timeout
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Δημιουργία μοναδικού barcode (12 ψηφία)
 */
function generate_unique_barcode(): string {
    $pdo = db();
    do {
        // Format: GYM + YY + 7 τυχαία ψηφία
        $barcode = 'GYM' . date('y') . str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE barcode = ?");
        $stmt->execute([$barcode]);
    } while ($stmt->fetchColumn() > 0);

    return $barcode;
}

/**
 * Επιστρέφει την ενεργή συνδρομή ενός μέλους
 */
function get_active_membership(int $member_id): ?array {
    $stmt = db()->prepare(
        "SELECT * FROM memberships
         WHERE member_id = ? AND is_active = 1
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$member_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Δημιουργία νέας συνδρομής για ένα μέλος
 */
function create_membership(int $member_id, string $type, float $price): int {
    $pdo = db();

    // Απενεργοποίησε προηγούμενες συνδρομές
    $pdo->prepare("UPDATE memberships SET is_active = 0 WHERE member_id = ?")
        ->execute([$member_id]);

    $start_date = date('Y-m-d');

    if ($type === 'open_gym') {
        $end_date       = date('Y-m-d', strtotime("+" . OPEN_GYM_DURATION_DAYS . " days"));
        $sessions_total = null;
    } else {
        $end_date       = null;
        $sessions_total = PERSONAL_SESSIONS;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO memberships
            (member_id, type, start_date, end_date, sessions_total, sessions_used, price, is_active)
         VALUES (?, ?, ?, ?, ?, 0, ?, 1)"
    );
    $stmt->execute([$member_id, $type, $start_date, $end_date, $sessions_total, $price]);

    return (int)$pdo->lastInsertId();
}

/**
 * Auto-checkout των ξεχασμένων check-ins (πάνω από 6 ώρες)
 */
function auto_checkout_stale(): void {
    db()->exec(
        "UPDATE checkins
         SET checkout_time = DATE_ADD(checkin_time, INTERVAL 6 HOUR),
             duration_minutes = 360
         WHERE checkout_time IS NULL
           AND checkin_time < DATE_SUB(NOW(), INTERVAL 6 HOUR)"
    );
}

/**
 * Κύρια συνάρτηση scan: κάνει toggle check-in / check-out.
 * Αν το μέλος είναι ήδη μέσα → check-out.
 * Αλλιώς → check-in.
 */
function process_scan(string $barcode): array {
    $pdo = db();

    // Auto-cleanup ξεχασμένων check-ins
    auto_checkout_stale();

    // Βρες το μέλος
    $stmt = $pdo->prepare("SELECT * FROM members WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $member = $stmt->fetch();

    if (!$member) {
        return ['success' => false, 'action' => 'error', 'message' => 'Άγνωστο barcode!'];
    }

    if ($member['status'] !== 'active') {
        return ['success' => false, 'action' => 'error',
                'message' => 'Το μέλος είναι ανενεργό.', 'member' => $member];
    }

    // Έλεγξε αν υπάρχει ανοιχτό check-in (μέσα στις τελευταίες 6 ώρες)
    $stmt = $pdo->prepare(
        "SELECT * FROM checkins
         WHERE member_id = ?
           AND checkout_time IS NULL
           AND checkin_time > DATE_SUB(NOW(), INTERVAL 6 HOUR)
         ORDER BY checkin_time DESC LIMIT 1"
    );
    $stmt->execute([$member['id']]);
    $open = $stmt->fetch();

    // ----- CHECK-OUT διαδρομή -----
    if ($open) {
        $secs_in = time() - strtotime($open['checkin_time']);

        // Anti-double-scan: αν μόλις έκανε check-in τα τελευταία 30", αγνόησέ το
        if ($secs_in < 30) {
            return [
                'success' => true,
                'duplicate' => true,
                'action' => 'duplicate',
                'message' => 'Μόλις έκανες check-in. Δοκίμασε ξανά σε λίγα δευτερόλεπτα.',
                'member' => $member,
            ];
        }

        $duration_min = max(1, (int)round($secs_in / 60));
        $pdo->prepare("UPDATE checkins SET checkout_time = NOW(), duration_minutes = ? WHERE id = ?")
            ->execute([$duration_min, $open['id']]);

        return [
            'success' => true,
            'action' => 'checkout',
            'message' => 'Καλό σου βράδυ, ' . $member['first_name'] . '!',
            'member' => $member,
            'duration_minutes' => $duration_min,
        ];
    }

    // ----- CHECK-IN διαδρομή -----
    $membership = get_active_membership((int)$member['id']);
    if (!$membership) {
        return [
            'success' => false,
            'action' => 'error',
            'message' => 'Δεν υπάρχει ενεργή συνδρομή. Παρακαλώ ανανέωση.',
            'member' => $member,
        ];
    }

    if ($membership['type'] === 'open_gym') {
        if (strtotime($membership['end_date']) < strtotime(date('Y-m-d'))) {
            return [
                'success' => false,
                'action' => 'error',
                'message' => 'Η συνδρομή Open Gym έληξε ('
                             . date('d/m/Y', strtotime($membership['end_date'])) . ').',
                'member' => $member,
                'membership' => $membership,
            ];
        }
    } else { // personal
        if ((int)$membership['sessions_used'] >= (int)$membership['sessions_total']) {
            return [
                'success' => false,
                'action' => 'error',
                'message' => 'Έχουν εξαντληθεί οι προπονήσεις Personal Training.',
                'member' => $member,
                'membership' => $membership,
            ];
        }
    }

    // Καταγραφή check-in
    $pdo->prepare(
        "INSERT INTO checkins (member_id, membership_id, checkin_time)
         VALUES (?, ?, NOW())"
    )->execute([$member['id'], $membership['id']]);

    // Αν είναι personal: +1 προπόνηση
    if ($membership['type'] === 'personal') {
        $pdo->prepare("UPDATE memberships SET sessions_used = sessions_used + 1 WHERE id = ?")
            ->execute([$membership['id']]);
        $membership['sessions_used']++;
    }

    $membership = get_active_membership((int)$member['id']);

    // Warning για χαμηλό υπόλοιπο
    if ($membership['type'] === 'personal') {
        $remaining = (int)$membership['sessions_total'] - (int)$membership['sessions_used'];
        if ($remaining === LOW_SESSIONS_THRESHOLD) {
            require_once __DIR__ . '/email.php';
            send_low_sessions_email($member, $membership);
        }
    }

    return [
        'success' => true,
        'action' => 'checkin',
        'message' => 'Καλώς ήρθες, ' . $member['first_name'] . '!',
        'member' => $member,
        'membership' => $membership,
    ];
}

/**
 * Backward compat alias
 */
function process_checkin(string $barcode): array {
    return process_scan($barcode);
}

/**
 * Τρέχουσα παρουσία (πόσοι είναι μέσα τώρα)
 */
function current_occupancy(): int {
    auto_checkout_stale();
    return (int)db()->query(
        "SELECT COUNT(*) FROM checkins
         WHERE checkout_time IS NULL
           AND checkin_time > DATE_SUB(NOW(), INTERVAL 6 HOUR)"
    )->fetchColumn();
}

/**
 * Συνηθισμένη κίνηση ανά ώρα (μέσος όρος των τελευταίων 30 ημερών).
 * Επιστρέφει array[ώρα 0-23] => μέσος αριθμός παρουσιών
 */
function hourly_traffic_pattern(): array {
    $rows = db()->query(
        "SELECT HOUR(checkin_time) AS h, COUNT(*) AS n,
                COUNT(DISTINCT DATE(checkin_time)) AS days
         FROM checkins
         WHERE checkin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY HOUR(checkin_time)"
    )->fetchAll();

    $pattern = array_fill(0, 24, 0.0);
    foreach ($rows as $r) {
        $days = max(1, (int)$r['days']);
        $pattern[(int)$r['h']] = round((int)$r['n'] / $days, 1);
    }
    return $pattern;
}

/**
 * Στατιστικά για το dashboard
 */
function get_dashboard_stats(): array {
    $pdo = db();

    $stats = [];

    $stats['total_members']  = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $stats['active_members'] = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();

    $stats['checkins_today'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM checkins WHERE DATE(checkin_time) = CURDATE()"
    )->fetchColumn();

    $stats['checkins_week'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM checkins WHERE checkin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();

    $stats['checkins_month'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM checkins WHERE checkin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )->fetchColumn();

    $stats['revenue_month'] = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM payments
         WHERE YEAR(payment_date) = YEAR(CURDATE())
           AND MONTH(payment_date) = MONTH(CURDATE())"
    )->fetchColumn();

    $stats['revenue_year'] = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM payments
         WHERE YEAR(payment_date) = YEAR(CURDATE())"
    )->fetchColumn();

    $stats['expiring_soon'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM memberships
         WHERE is_active = 1 AND type = 'open_gym'
           AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    )->fetchColumn();

    $stats['low_sessions'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM memberships
         WHERE is_active = 1 AND type = 'personal'
           AND (sessions_total - sessions_used) <= " . LOW_SESSIONS_THRESHOLD . "
           AND (sessions_total - sessions_used) > 0"
    )->fetchColumn();

    $stats['active_open_gym'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM memberships
         WHERE is_active = 1 AND type = 'open_gym'
           AND end_date >= CURDATE()"
    )->fetchColumn();

    $stats['active_personal'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM memberships
         WHERE is_active = 1 AND type = 'personal'
           AND sessions_used < sessions_total"
    )->fetchColumn();

    return $stats;
}

/**
 * Format ποσό σε EUR
 */
function fmt_eur(float $amount): string {
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Format ημερομηνίας
 */
function fmt_date(?string $date): string {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

function fmt_datetime(?string $datetime): string {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Get/Set setting
 */
function get_setting(string $key, ?string $default = null): ?string {
    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? $default : $v;
}
