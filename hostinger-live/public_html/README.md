# Shakesbeard CYBER Sales Record Manager

A complete PHP and MySQL sales record manager for:

- Printing
- Scanning
- Photocopy
- Browsing
- Gaming
- Photography
- Binding
- Branding
- Lamination
- E-citizen Services
- Computer Training
- Computer Repairs

## Requirements

- PHP 8.0+
- MySQL 5.7+ (or MariaDB)
- XAMPP/WAMP/LAMP

## Setup

1. Create a database, e.g. `srm_db`.
2. Import [`database.sql`](database.sql).
3. Copy [`config.local.php.example`](config.local.php.example) to
	`config.local.php` and update the DB credentials there.
4. Place project in your web root (`htdocs/srm`).
5. Open `http://localhost/srm/login.php`.

`config.php` is safe to commit and can also read credentials from the
`SRM_DB_HOST`, `SRM_DB_USER`, `SRM_DB_PASS`, and `SRM_DB_NAME` environment
variables.

## Default Accounts

- Admin: `admin` / `admin123`
- Attendant: `attendant` / `attendant123`

Change both passwords after first login.

## Roles

- `admin`: full dashboard, reports, all management actions.
- `attendant`: dedicated attendant dashboard, can view/add sales, view/add services, and manage own profile.

## Default Behavior

- Services are preloaded in the `services` table.
- Sales are recorded with quantity, unit price, discount, payment method, and date.
- Sales are stamped with the logged-in user for accountability.
- Dashboard displays daily and monthly totals.
- Reports include service summaries, payment breakdown, and attendant performance.
- CSV export available from sales and reports pages.
