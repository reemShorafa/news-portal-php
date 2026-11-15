# XAMPP and Docker compatibility notes

## Problem

The application was developed under XAMPP at `http://localhost/news-portal/`, but Docker exposes the same project at `http://localhost:8080/`. Apache in the PHP container uses `/var/www/html` as its document root, and the project is mounted directly into that directory.

The existing shared `url()` function in `includes/functions.php` always returned URLs beginning with `/news-portal/`. Consequently, Docker received requests such as `/news-portal/assets/css/style.css` even though the file was available at `/assets/css/style.css`, producing 404 responses. This was an application URL-generation issue, not an Apache rewrite, Docker port, or database issue.

## Existing implementation

The project already had a centralized `url()` helper. Public and administration headers/footers, navigation links, search actions, article/category links, login/logout flows, post-action redirects, uploaded-image URLs, and administration links use this helper. `imageUrl()` also delegates to `url()` for uploaded images. Forms without an explicit `action` submit to their current URL and therefore already work in either deployment.

## Solution

`includes/functions.php` now derives the application base path once from the project directory relative to the web server's `DOCUMENT_ROOT`:

- XAMPP: project `C:/xampp/htdocs/news-portal` relative to document root `C:/xampp/htdocs` gives `/news-portal`.
- Docker: project `/var/www/html` equals document root `/var/www/html`, giving an empty base path.

The existing `url()` helper appends paths to that detected base. A fallback uses the executing script URL for unusual Apache alias configurations. This retains the existing centralized design, requires no per-environment source edits or new dependencies, and avoids duplicating URL logic throughout the project.

No Dockerfile or Compose changes were necessary. No database schema, records, or volumes were changed or removed.

## Verification performed on 2026-11-15

### Docker

- `docker compose config` completed successfully.
- `docker compose up -d --build` rebuilt the PHP image and started/recreated the existing services without deleting volumes.
- `php_app` and `mysql_db` are both running; PHP is published on port 8080.
- `GET http://localhost:8080/` returned `200 OK`.
- `GET http://localhost:8080/assets/css/style.css` returned `200 OK` with `text/css`.
- `GET http://localhost:8080/assets/js/main.js` returned `200 OK` with `text/javascript`.
- Generated Docker HTML uses `/assets/...`, `/search.php`, `/category.php`, `/login.php`, and `/` rather than `/news-portal/...`.
- `GET /search.php?q=test` and `GET /login.php` returned `200 OK`.
- Unauthenticated `GET /admin/dashboard.php` returned `302 Found` with `Location: /login.php`, confirming the Docker-aware redirect path.
- Every PHP file passed `php -l` in the PHP 8.2 container.
- Current Apache request logs show 200 responses for `/`, the CSS, and the JavaScript. The earlier status-255 PHP container exit did not recur during rebuild or testing.

### XAMPP

- XAMPP was available locally with Apache 2.4.58 and PHP 8.1.25.
- `GET http://localhost/news-portal/` returned `200 OK`.
- The XAMPP page generated `/news-portal/assets/...`, `/news-portal/search.php`, category/navigation links, and `/news-portal/login.php` correctly.
- The CSS and JavaScript both returned `200 OK` from `/news-portal/assets/...`.
- Unauthenticated `/news-portal/admin/dashboard.php` returned `302 Found` with `Location: /news-portal/login.php`.

### Coverage and limitations

Static asset URLs, public navigation, search action, login page, administration authentication redirect, upload URL generation, and all PHP syntax were verified. Administration templates and mutations were inspected and consistently use the shared helper; no administrator password was supplied, so authenticated create/edit form submission was not performed. The current database contains no published articles, so a real uploaded article image was not available for an HTTP request, but `imageUrl()` was verified in source to use the corrected helper.

The only log warning observed is Apache's non-fatal `ServerName` warning. It does not affect the application or asset loading. No unresolved compatibility error remains.

## Commands used

```text
rg --files
rg -n ...
docker compose config
docker compose ps -a
docker compose up -d --build
docker compose exec -T app sh -lc "find . -name '*.php' -print0 | xargs -0 -n1 php -l"
curl.exe ... http://localhost:8080/
curl.exe ... http://localhost:8080/assets/css/style.css
curl.exe ... http://localhost:8080/assets/js/main.js
curl.exe ... http://localhost:8080/search.php?q=test
curl.exe ... http://localhost:8080/login.php
curl.exe ... http://localhost:8080/admin/dashboard.php
curl.exe ... http://localhost/news-portal/
curl.exe ... http://localhost/news-portal/assets/css/style.css
curl.exe ... http://localhost/news-portal/assets/js/main.js
curl.exe ... http://localhost/news-portal/admin/dashboard.php
docker compose logs --no-color ...
```

