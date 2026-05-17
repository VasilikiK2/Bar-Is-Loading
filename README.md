# Gym Management System

Σύστημα διαχείρισης γυμναστηρίου με check-in/check-out μέσω barcode, εγγραφή μελών, αυτόματα emails υπενθύμισης, ιστορικό πληρωμών και μηνιαίες Excel αναφορές.

## Λειτουργίες

- **Scanner barcode** στην είσοδο του γυμναστηρίου
- **Εγγραφή νέου μέλους** με αυτόματη δημιουργία barcode + αποστολή email καλωσορίσματος
- **2 τύποι συνδρομής:**
  - **Open Gym** – χρέωση ανά 30 ημέρες
  - **Personal Training** – πακέτο 12 προπονήσεων (αυξάνει +1 σε κάθε scan)
- **Αυτόματα emails:**
  - Καλωσόρισμα κατά την εγγραφή
  - Υπενθύμιση πληρωμής 30 μέρες μετά την εγγραφή / στη λήξη συνδρομής
  - Προειδοποίηση όταν τελειώνουν οι προπονήσεις Personal (≤ 3 απομένουν)
- **Admin dashboard** με στατιστικά (check-ins, έσοδα, λήγουν σύντομα)
- **Ιστορικό πληρωμών** με φίλτρα ημερομηνίας
- **Εκτύπωση κάρτας μέλους** με barcode
- **Μηνιαία Excel αναφορά** με μέλη, check-ins, πληρωμές
- **MySQL βάση δεδομένων**

## Απαιτήσεις

- PHP **8.0+**
- MySQL **5.7+** ή MariaDB
- Composer
- GD extension (για barcode)
- Web server (Apache / Nginx / XAMPP / Laragon)
- Gmail account με App Password (για αποστολή email)

## Εγκατάσταση

### 1. Κατέβασε & τοποθέτησε στον web server
Κάνε copy ολόκληρο τον φάκελο `gym-system/` στο htdocs/www του server σου.

### 2. Εγκατάσταση εξαρτήσεων
Άνοιξε terminal στον φάκελο `gym-system/`:
```bash
composer install
```
Αυτό θα εγκαταστήσει PHPMailer, PhpSpreadsheet και τη βιβλιοθήκη παραγωγής barcode.

### 3. Ρύθμιση `config/config.php`
Άνοιξε το αρχείο `config/config.php` και άλλαξε:
- **Database:** DB_HOST, DB_NAME, DB_USER, DB_PASS
- **Gmail SMTP:** SMTP_USERNAME, SMTP_PASSWORD (App Password)
- **SITE_URL:** π.χ. `http://localhost/gym-system`

### 4. Δημιουργία App Password για Gmail
1. Πήγαινε στο https://myaccount.google.com/security
2. Ενεργοποίησε 2-Step Verification
3. Πήγαινε στο https://myaccount.google.com/apppasswords
4. Δημιούργησε App Password για "Mail"
5. Βάλε τον 16-ψήφιο κωδικό στο `SMTP_PASSWORD`

### 5. Εγκατάσταση βάσης
Άνοιξε στο browser:
```
http://localhost/gym-system/install.php
```
Δημιούργησε τον πρώτο admin χρήστη. **Διέγραψε το install.php** μετά την εγκατάσταση!

### 6. Σύνδεση
```
http://localhost/gym-system/login.php
```

## Ρύθμιση Cron Job (αυτόματα emails)

Για να στέλνονται αυτόματα οι υπενθυμίσεις, ρύθμισε ένα cron job:

### Linux
```bash
crontab -e
# Εκτέλεση κάθε μέρα στις 09:00
0 9 * * * /usr/bin/php /path/to/gym-system/cron/send_payment_reminders.php

# Μηνιαία αυτόματη Excel αναφορά - 1η κάθε μήνα στις 02:00
0 2 1 * * /usr/bin/php /path/to/gym-system/cron/monthly_export.php
```

### Windows (Task Scheduler)
1. Άνοιξε Task Scheduler → Create Basic Task
2. Trigger: Daily, 09:00
3. Action: Start a program
4. Program: `C:\xampp\php\php.exe`
5. Arguments: `C:\xampp\htdocs\gym-system\cron\send_payment_reminders.php`

## Δομή Αρχείων

```
gym-system/
├── config/
│   ├── config.php          # Κεντρικές ρυθμίσεις
│   └── database.php        # MySQL connection
├── includes/
│   ├── header.php          # Top navigation
│   ├── footer.php
│   ├── functions.php       # Κύριες συναρτήσεις (check-in, membership, stats)
│   └── email.php           # Αποστολή email & templates
├── api/
│   ├── checkin.php         # AJAX endpoint για barcode scan
│   └── search_members.php  # Search μελών
├── cron/
│   ├── send_payment_reminders.php  # Αυτόματες υπενθυμίσεις
│   └── monthly_export.php          # Μηνιαία Excel
├── assets/
│   ├── css/style.css
│   └── js/scanner.js
├── reports/                # Αποθηκευμένες μηνιαίες Excel
├── logs/                   # PHP error logs
├── database.sql            # Schema MySQL
├── install.php             # Wizard εγκατάστασης
├── login.php / logout.php
├── index.php               # Scanner page
├── dashboard.php           # Στατιστικά
├── members.php             # Λίστα μελών
├── register.php            # Νέο μέλος
├── member_view.php         # Προφίλ μέλους
├── payments.php            # Πληρωμές
├── add_payment.php
├── reports.php             # Επιλογή & download Excel
├── export_excel.php        # Παραγωγή Excel
├── barcode.php             # PNG barcode generator
└── print_card.php          # Κάρτα μέλους εκτύπωσης
```

## Πώς λειτουργεί η ροή

### Νέο μέλος
1. Γραμματεία ανοίγει `register.php`
2. Συμπληρώνει όνομα/τηλέφωνο/email + επιλέγει τύπο (Open Gym / Personal)
3. Σύστημα:
   - Δημιουργεί μοναδικό barcode (π.χ. `GYM261234567`)
   - Αποθηκεύει συνδρομή + πληρωμή
   - Στέλνει email καλωσορίσματος
   - Ανοίγει σελίδα εκτύπωσης κάρτας

### Check-in
1. Μέλος σκανάρει barcode στην είσοδο
2. Σύστημα:
   - Επιβεβαιώνει ότι η συνδρομή είναι ενεργή
   - Open Gym: ελέγχει αν έχει λήξει
   - Personal: ελέγχει αν έχει προπονήσεις, αυξάνει +1
   - Καταγράφει check-in
   - Εμφανίζει όνομα + υπόλοιπο
   - Αν Personal ≤ 3 προπονήσεις: στέλνει warning email

### Υπενθύμιση πληρωμής
- Το cron `send_payment_reminders.php` τρέχει κάθε μέρα στις 09:00
- Στέλνει email σε όσους έχουν περάσει 30 ημέρες από την εγγραφή
- Στέλνει email όταν λήγει το Open Gym ή τελειώνουν οι προπονήσεις Personal

## Προεπιλεγμένες τιμές

Οι τιμές μπορούν να αλλάξουν στον πίνακα `settings` της βάσης (ή στο `config.php`):
- **Open Gym:** 40€ / 30 ημέρες
- **Personal Training:** 120€ / 12 προπονήσεις
- **Low sessions threshold:** 3 (στέλνει email όταν απομένουν 3 προπονήσεις)
- **Reminder days:** 30 (στέλνει υπενθύμιση 30 μέρες μετά την εγγραφή)

## Ασφάλεια

- Όλες οι ερωτήσεις SQL χρησιμοποιούν **prepared statements** (anti-SQL injection)
- Όλες οι έξοδοι HTML περνούν από `htmlspecialchars()` (anti-XSS)
- Οι κωδικοί χρηστών αποθηκεύονται με `password_hash()` (bcrypt)
- Session timeout στα 60 λεπτά αδράνειας
- Anti-double-scan: αγνοεί scan του ίδιου barcode εντός 5 λεπτών

## Σημείωση για το barcode

Το σύστημα παράγει **Code 128** barcode που είναι συμβατό με όλους τους κλασικούς scanner USB της αγοράς (που λειτουργούν σαν πληκτρολόγιο). Δεν χρειάζεται driver – ο scanner απλώς "πληκτρολογεί" το barcode στο πεδίο του scanner.php και πατάει Enter.

## Άδεια

MIT
