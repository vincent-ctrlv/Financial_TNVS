# SakaySaya - TNVS Finance

SakaySaya is a financial management and dashboard suite for the Transport Network Vehicle Service (TNVS) sector. Drivers, finance staff and administrators use it to track fares, manage receivables and payables, process disbursements and review an audit trail of every change.

---

## Key Features

*   **Two-step sign-in:** email + password, then a 6-digit one-time code sent by email (PHPMailer). Repeated wrong passwords or codes lock the form temporarily.
*   **Financial modules:**
    *   **General Ledger:** every transaction (fares, platform fees, fuel, lease, incentives, payouts) with totals by type.
    *   **Accounts Receivable & Payable:** amounts due with pending / paid / overdue status; overdue is set automatically after the due date.
    *   **Collections:** a combined statement of receivables and payables, filterable by status.
    *   **Disbursements:** payouts by method (cash, GCash, bank transfer). Marking a receivable or payable as paid records a disbursement automatically.
    *   **Budget:** coming soon.
*   **Role-based access:**
    *   **Drivers:** their own dashboard, earnings, statements, profile and activity.
    *   **Finance Staff:** all financial modules.
    *   **Administrators:** everything, plus user management.
*   **Audit log:** status changes, profile updates and user changes are recorded and visible under *My Activity*.

---

## Technology Stack

| Part | Technology |
|------|------------|
| Backend | PHP 8 (no framework) |
| Database | MySQL / MariaDB |
| Email | PHPMailer (installed with Composer) |
| Frontend | HTML, CSS (Outfit / Inter fonts), vanilla JavaScript |
| Config | `.env` file read by `src/env.php` |

---

## Project Structure

```text
Financial_TNVS/
├── public/                    # Web root - the only folder the browser can reach
│   ├── index.php              # Landing page
│   ├── dashboard.php          # Dashboard (different view per role)
│   ├── auth/                  # Login, email code (OTP) check, logout
│   ├── modules/               # Ledger, AR, AP, collection, disbursement, budget
│   ├── user/                  # Profile, change password, activity, my finances, my modules
│   ├── admin/                 # Users management page and its JSON API
│   ├── assets/                # CSS, JS, images
│   └── uploads/avatars/       # Profile pictures (PHP execution disabled)
├── src/                       # Shared PHP code (not web-reachable)
│   ├── bootstrap.php          # Loaded first by every page: env, DB, session, helpers
│   ├── auth.php               # Sign-in, roles/permissions, CSRF, audit log
│   ├── config.php             # Settings read from .env
│   ├── db.php                 # MySQL connection
│   ├── env.php                # .env loader
│   ├── mailer.php             # send_mail() via PHPMailer
│   └── partials/              # header.php, sidebar.php
├── database/
│   ├── schema.sql             # Database tables
│   └── seed.php               # Creates test accounts
├── .htaccess                  # Sends all requests into public/
├── .env.example               # Template for your .env
├── composer.json              # PHP dependencies (PHPMailer)
└── README.md
```

---

## Setup (XAMPP)

### 1. Get the code
Clone into your XAMPP `htdocs` folder and install PHPMailer:

```bash
cd C:\xampp\htdocs
git clone https://github.com/vincent-ctrlv/Financial_TNVS.git
cd Financial_TNVS
composer install
```

### 2. Create the database
In phpMyAdmin (or the MySQL client), create a database named `finance`, then import `database/schema.sql`:

```sql
CREATE DATABASE finance CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### 3. Configure `.env`
Copy the template and fill in your database and email settings:

```bash
copy .env.example .env
```

*   `DB_*`: your MySQL connection (XAMPP default: user `root`, empty password).
*   `SMTP_*`: the account that sends sign-in codes. For Gmail, create an **App Password** (Google Account → Security → App Passwords) and use it as `SMTP_PASS`.
*   `LOGIN_OTP`: set to `false` to sign in without an email code while developing locally.

### 4. Create test accounts

```bash
php database/seed.php
```

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `admin12345` |
| Finance Staff | `finance@example.com` | `finance12345` |
| Driver | `driver@example.com` | `driver12345` |

Change these passwords before using the system with real data.

### 5. Open the app
Start Apache and MySQL in XAMPP, then go to **http://localhost/Financial_TNVS/**.

---

## Security Notes

*   Only `public/` is served. The root `.htaccess` routes every request there, so `.env`, `src/`, `database/` and `vendor/` can't be opened from a browser.
*   `.env` is ignored by Git. Never commit it or put real credentials in `src/config.php`.
*   Database queries use prepared statements; forms are protected with CSRF tokens; passwords are stored with `password_hash()`.
*   Sign-in codes are stored hashed in the session, expire after 5 minutes, and are only sent to the account that just entered a correct password.
