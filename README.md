# pos

Repository for the Shakesbeard CYBER POS codebase and deployment snapshots.

## Layout

- `hostinger-live/public_html/`: sanitized copy of the live PHP application pulled
	from Hostinger.
- `snapshot/public-site/`: fallback snapshot of the public login page only.

## Local setup

1. Import `hostinger-live/public_html/database.sql` into MySQL.
2. Copy `hostinger-live/public_html/config.local.php.example` to
	 `hostinger-live/public_html/config.local.php`.
3. Update the local DB credentials in `config.local.php`.
4. Serve `hostinger-live/public_html/` from your PHP web root.

The tracked `config.php` is sanitized for version control and loads local
overrides from `config.local.php` or environment variables.