# Aarcon Builders - Cash, PDC Check & Disbursement Management System

A financial management, post-dated check (PDC) funding, petty cash tracking, and client collection system built strictly with **PHP, HTML, CSS, JavaScript, and SQL**.

---

## 🏗️ The 4 Main Modules

### 1. Module 1: Check Management (PDCs & Funding)
- **Physical Creation & Entry**: Encode hand-written or typewritten checks signed by the boss: Check Number, Bank Name, Payee / Supplier, Amount, Issue Date, and Maturity Check Date (Post-Date).
- **Monthly Check Release Report (Printable)**: Grouped and sorted by month (e.g. October 2026, November 2026, December 2026) with month subtotals and commitment schedules.
- **Checks for Funding (Boss Review)**: Dedicated queue showing checks due for the day or week. The boss reviews and 1-clicks **"Fund"** (or multi-selects with the live dynamic sum calculator).
- **Automatic Routing**: Once funded, checks instantly route to the Final Disbursement Report.

### 2. Module 2: Physical Cash Requests (Petty Cash / Operational Cash)
- **Cash Request Entry**: Staff/engineers encode urgent cash needs: Voucher Number, Requester, Department / Site Project, Amount, Purpose, and Date.
- **Boss Approval / Funding**: Appears in the boss's pending funding list. Boss approves or disapproves requests with 1 click.
- **Automatic Routing**: Approved cash requests immediately transfer into the Disbursement Report under cash expenses.

### 3. Module 3: Collections (Cash In)
- **Collection Log**: Staff logs all incoming funds: Client/Customer Name, Project, Amount Received, Payment Method (Cash, Bank Deposit, Incoming Check, Online Transfer), Receipt / OR Number, Reference #, and Date.
- **Automatic Link**: All recorded collections immediately feed the Total Inflow column of the Disbursement Report.

### 4. Module 4: The Final Disbursement & Cash Balance Report
- Live executive dashboard and printable statement answering: **"How much money do we have left?"**
- **Net Cash Balance Formula**:
  $$\text{Net Cash Balance} = \text{Total Collections} - (\text{Funded Checks} + \text{Approved Cash Expenses})$$
- Breakdown of Incoming Collections vs Outgoing Funded Checks vs Outgoing Cash Expenses.
- Quick period filtering (Today, This Week, This Month, Custom Date Range, All Time).
- Official printable format with company letterhead, reconciliation table, and executive signature lines (`@media print`).

### 5. Module 5: Admin System Dashboard & User Management
- **Full User Management (CRUD)**: Create, edit, activate/deactivate, and delete accounts with custom roles (`admin`, `manager`, `cashier`, `engineer`).
- **Company & Financial Configuration**: Live editing of company name, tagline, address, contact details, currency symbol, default approver signatory, and fiscal year.
- **Real-Time Security Audit Trail**: Detailed chronological logs tracking logins, logouts, check funding, cash voucher approvals, and configuration edits.
- **Database Health & Maintenance**: One-click SQLite index vacuuming, defragmentation, and diagnostic checks.

---

## 🔐 System Access & Accounts (2 Roles Only)

The system is configured with two distinct user roles:

| Role | Username | Password | Access Level & Scope |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin` | `admin123` | **System Admin Only**: User management, system configuration, security audit logs, database maintenance |
| **Accountant** | `accountant` | `accountant123` | **Financial Operations (Existing Functions)**: Post-dated checks & funding, petty cash voucher approvals, client collections, disbursement report |

---

## 🚀 How to Run the Application

### Option A: 1-Click Launch (Windows)
Double-click `run.bat` in this folder. It will launch the local PHP server and automatically open `http://localhost:8000` in your web browser.

### Option B: Terminal / Command Prompt
Run the following command in this directory:
```bash
"C:\xampp\php\php.exe" -S localhost:8000
```
or (if `php` is in your environment PATH):
```bash
php -S localhost:8000
```
Then open your browser to [http://localhost:8000](http://localhost:8000).

### Option C: XAMPP Apache
You can copy or symlink this folder into `C:\xampp\htdocs\aarcon-builders` and access it at `http://localhost/aarcon-builders/`.

---

## 🗄️ Database Information

- **Default Driver**: **SQLite** (`database/aarcon_builders.db`). Automatically initializes tables, seed data, default users, and system settings upon first run — **zero manual configuration required**.
- **MySQL Compatibility**: A full SQL schema and seed data file is provided in `database/schema.sql` if you prefer to import it into phpMyAdmin or MySQL. To switch to MySQL, update `DB_DRIVER = 'mysql'` in `config/config.php`.
# AarconBuilders
