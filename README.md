#  SakaySaya - TNVS Finance 

![SakaySaya Landing Page](screenshot.png)

SakaySaya is a premium financial management and dashboard suite designed specifically for the Transport Network Vehicle Service (TNVS) sector. It allows drivers, finance staff, and administrators to track fares, manage leases, process disbursements, audit transactions, and verify user credentials in real-time.

---

##  Key Features

*   **Secure Dual-Factor Authentication:** Features secure login with email-based One-Time Password (OTP) verification using PHPMailer.
*   ** Financial Modules:**
    *   **General Ledger:** Tracks all cash-in, cash-out, and balances.
    *   **Accounts Payable & Receivable:** Manages outstanding amounts, invoices, and payment statuses (pending, paid, overdue).
    *   **Collections:** Records and processes driver fares and platform fees.
    *   **Disbursements:** Handles payouts, fuel expenses, and driver incentives.
    *   **Budgeting:** Provides high-level budget allocations and limits.
*   ** Role-Based Portals & Dashboards:**
    *   **Drivers:** Manage personal profile details, track gross earnings, and view outstanding receivables.
    *   **Administrators & Finance Staff:** Admin portal to manage users, process payouts, verify transactions, and view audit logs.
*   ** Modern UX/UI:** Beautiful user interface styling with custom light-mode design tokens, Outfit and Inter Google fonts, and responsive layout structures.

---

## Technology Stack

*   **Backend:** PHP (Vanilla)
*   **Database:** MySQL / MariaDB
*   **Security & Env:** Dotenv loader (`env.php`) parsing environment configurations
*   **Mail Engine:** PHPMailer (integrated for SMTP OTP delivery)
*   **Frontend:** HTML5, CSS3 (Custom styling with Outfit/Inter typography), JavaScript (Vanilla JS for interactive states)

---

##  Project Structure

```text
adminfinance1/
├── admin/                     # Admin dashboard and user operations
│   ├── user_api.php           # REST endpoint for managing users
│   └── users.php              # UI page for managing application users
├── assets/                    # Static files (CSS, JS, images)
│   ├── css/
│   └── js/
├── auth/                      # Authentication & OTP flows
│   ├── login.php              # Login view and submission
│   ├── logout.php             # Session destruction
│   ├── send_otp.php           # OTP generation and email dispatch
│   └── verify_otp.php         # OTP entry and validation check
├── database/                  # Database schema backups
│   └── finance-1.sql          # Primary MySQL DB structure
├── includes/                  # Global shared includes and helpers
│   ├── auth.php               # Auth check functions & session managers
│   ├── config.php             # Core configuration and SMTP settings
│   ├── db.php                 # Database connection handler
│   ├── env.php                # Environment variable parser
│   ├── mailer.php             # SMTP email dispatcher
│   └── ...
├── modules/                   # Financial system worksheets
│   ├── accounts_payable.php
│   ├── accounts_receivable.php
│   ├── collection.php
│   ├── disbursement.php
│   └── ledger.php
├── uploads/                   # User uploaded assets (avatars, etc.)
├── .env.example               # Template file for environment secrets
├── .gitignore                 # Files excluded from GitHub tracking
├── create_driver_seed.php     # CLI script to seed test driver users
├── dashboard.php              # Application workspace dashboard
├── index.php                  # Web system landing page
└── README.md                  # System manual
```

---

##  Setup & Local Installation

### 1. Prerequisites
*   An active local development server (e.g., **XAMPP**, **WAMP**, **Laragon**, or PHP 8.0+ CLI).
*   **MySQL / MariaDB** database server.

### 2. Database Initialization
1.  Open your MySQL administration tool (like **phpMyAdmin** or terminal MySQL client).
2.  Create a database named `finance`:
    ```sql
    CREATE DATABASE finance CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    ```
3.  Import the database schema from the file:
    [finance-1.sql](file:///c:/xampp/htdocs/adminfinance1/database/finance-1.sql).

### 3. Environment Configuration
For security reasons, your system configuration credentials are kept in a local `.env` file that is **excluded** from Git tracking.

1.  Copy the example environment template file:
    *   On Windows cmd: `copy .env.example .env`
    *   On PowerShell: `cp .env.example .env`
2.  Open the newly created `.env` file and fill in your local MySQL credentials and SMTP setup:
    ```ini
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_NAME=finance
    DB_USER=root
    DB_PASS=your_db_password_here

    SMTP_HOST=smtp.gmail.com
    SMTP_PORT=587
    SMTP_USER=your-email@gmail.com
    SMTP_PASS=your-google-app-password
    SMTP_FROM=your-email@gmail.com
    SMTP_FROM_NAME="SakaySaya Finance"
    ```

> [!IMPORTANT]
> **Gmail SMTP Setup:** If you are using Gmail SMTP, you must generate a **Google App Password** (16 characters) instead of using your primary password. Under Google Account Settings -> Security, search for "App Passwords" to generate one.

### 4. Create a Default Test Account
You can seed a sample driver account directly from your command terminal.
1.  Open your terminal or command prompt inside the project folder.
2.  Execute the following command:
    ```bash
    php create_driver_seed.php
    ```
3.  This script will seed a test account with:
    *   **Email:** `driver@example.com`
    *   **Password:** `driver123`

---

##  Security Best Practices & Hiding Secrets

When publishing code to a public platform like GitHub, safeguarding configuration details is paramount:

1.  **Strict Ignoring (.gitignore):**
    *   The [.gitignore](file:///c:/xampp/htdocs/adminfinance1/.gitignore) file ignores the `.env` file. This prevents your active database secrets and mail application passwords from being uploaded.
    *   Ensure you never run `git add -f .env` or bypass the ignore configuration.
2.  **Fallback Safe-Defaults:**
    *   Application config scripts (like [config.php](file:///c:/xampp/htdocs/adminfinance1/includes/config.php)) use generic environment fallbacks (`your-email@example.com`) rather than developer credentials.
3.  **Prevent SQL Dump Leaks:**
    *   `.gitignore` is configured to block generic `*.sql` files from the root of the project, protecting any manual or automated test database dumps you make during debugging. Only the template schema [finance-1.sql](file:///c:/xampp/htdocs/adminfinance1/database/finance-1.sql) is version-controlled.
