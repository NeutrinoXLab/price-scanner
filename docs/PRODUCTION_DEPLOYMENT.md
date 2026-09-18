# Production deployment: scan.novelions.ro

Target application directory: `/home/ikasiypk/scan.novelions.ro`  
Required document root: `/home/ikasiypk/scan.novelions.ro/public`

Do not reuse the Novelion or Tender Scanner database, `.env`, storage directory, scheduler or queue worker.

## Requirements

- PHP web runtime and CLI 8.3 or newer, with PDO SQLite, mbstring, OpenSSL, tokenizer, XML, cURL and fileinfo.
- Composer 2 and Node.js suitable for the locked frontend dependencies.
- HTTPS active for `scan.novelions.ro`.
- The web server document root must point only to `public/`.
- `storage/`, `bootstrap/cache/` and the SQLite database must be writable by the PHP web user.

Verify both runtimes separately. `php -v` verifies CLI; cPanel MultiPHP Manager or a temporary protected diagnostic page verifies the web runtime. Remove any diagnostic page immediately after verification.

## Environment

Copy `.env.example` to `.env` on the server and set a fresh `APP_KEY` there. Keep:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://scan.novelions.ro
DB_CONNECTION=sqlite
QUEUE_CONNECTION=sync
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

Never copy the local `.env`, local SQLite database, credentials from another application, or provider secrets. Generate the key on the server with `php artisan key:generate`.

## Database

SQLite is appropriate for the initial public presentation because there are no live feeds, background workers or write-heavy public features. Create a new empty file at `database/database.sqlite`, grant the PHP user write access to the file and directory, then run migrations. Back it up before every future migration.

Move to a dedicated Price Scanner MySQL database before enabling high-volume feeds or concurrent workers. Creating that database and credentials requires explicit operator approval.

## Build and release

From the application directory:

```text
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --ignore-scripts
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

The current public UI does not depend on uploaded public files, so a missing storage link is not fatal, but creating it prepares standard Laravel storage safely.

Create the administrator interactively; the password is hidden and is never passed in the command line:

```text
php artisan scanner:admin novelionprime@gmail.com
```

There is no public signup route.

## Queue and scheduler

No permanent worker or cron entry is required while every commercial feed is disabled. `QUEUE_CONNECTION=sync` avoids an idle worker. Add the Laravel scheduler and a supervised queue worker only after the first authorized feed is configured and verified.

## Health and verification

- `GET /up` must return HTTP 200.
- `/` must return HTTP 200 over HTTPS and show the launch-stage disclosure and operator data.
- `/login` must be `noindex`; internal POST routes must redirect guests to `/login`.
- Verify CSS, response security headers, session cookies (`Secure`, `HttpOnly`, `SameSite=Lax`) and `storage/logs/laravel.log`.
- Run `php artisan test`, `vendor/bin/pint --format agent`, `composer validate --no-check-publish`, and `npm run build` before packaging.

## Rollback

Keep the previous application directory and database backup untouched. If verification fails, restore the prior directory/document-root target and its matching SQLite backup. Never run `migrate:fresh`. Database rollback should use a tested backup when a release includes schema changes.

## Connecting a future authorized feed

Confirm contractual permission, retain a real sample as a sanitized test fixture, implement and test the provider mapping, then configure its URL and approval flag directly in the production `.env`. Do not expose feed URLs, tokens or credentials in Git or logs.
