# GitHub publication preparation

## Public configuration

Docker configuration is read from a local `.env`, which is ignored by both Git and the Docker build context. `.env.example` contains placeholders only. For a fresh installation, copy it to `.env`, replace every `CHANGE_ME` value, and keep the application credentials paired:

- `DB_USER` must match `MYSQL_APP_USER`.
- `DB_PASS` must match `MYSQL_APP_PASSWORD`.
- `MYSQL_ROOT_PASSWORD` should be a different strong password.

The current local `.env` intentionally retains the credentials already stored in the initialized Docker database volume. Changing environment values alone does not rotate credentials inside an existing MySQL volume. Rotation requires an intentional MySQL user/password change first; no live credential rotation was performed during publication preparation.

## Database initialization

`database/news_portal_db.sql` is a clean installation script. It contains the application tables, keys, indexes, foreign keys, default Arabic categories, and default settings. It contains no users, news, personal email addresses, or password hashes. A new installation opens `setup-admin.php` for creation of its own first administrator, and that page closes once an administrator exists.

The SQL file uses UTF-8/`utf8mb4`. PowerShell 5 may display BOM-less UTF-8 Arabic incorrectly when using `Get-Content` without `-Encoding UTF8`; this is a console decoding issue rather than corrupt file data.

## Docker storage model

Application source is copied into the image. The full repository is no longer bind-mounted at runtime. Named volumes persist:

- `db_data`: MySQL database files.
- `uploads_data`: user-uploaded images.

The web port binds to `127.0.0.1` by default. The database is not published to the host. The database health check delays application startup until MySQL is ready.

## Verification summary (2026-11-15)

- Existing Docker data before and after recreation: 1 user, 1 news record, 6 categories.
- Existing Docker homepage, CSS, JavaScript, and login returned HTTP 200.
- Existing setup page returned HTTP 410 because an administrator already exists.
- XAMPP homepage returned HTTP 200 after the changes.
- A separate Compose project on port 18080 used isolated test volumes and a dedicated non-root database user.
- Fresh SQL produced 0 users, 6 categories, and 5 settings.
- Fresh homepage, CSS, JavaScript, and administrator setup returned HTTP 200.
- A temporary test administrator was created; setup and a separate login both reached `/admin/dashboard.php`.
- Setup returned HTTP 410 after administrator creation.
- Arabic defaults rendered correctly in the fresh installation's UTF-8 HTML.
- All PHP files passed `php -l` under PHP 8.2.
- The isolated test containers and test volumes were removed afterward. Existing project volumes were not removed.

The remaining Apache `ServerName` message is a non-fatal development warning. External Bootstrap, Bootstrap Icons, and Google Fonts still require internet access.
