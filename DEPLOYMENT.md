# Deploying the Inquiry Portal (Vercel + Railway)

Frontend (React) → Vercel. Backend (Laravel API, MySQL, queue worker, scheduler, attachment storage) → Railway.
Vercel cannot run Laravel; the two are deployed separately and talk over HTTPS.

## 1. One-time code changes (on your laptop)

### 1a. Edit `backend/bootstrap/app.php`

Inside `->withRouting(...)`, add the health route:

```php
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
```

Inside `->withMiddleware(function (Middleware $middleware): void {`, add as the first line:

```php
        // Railway's edge proxy is the only way in, so trust its forwarded
        // headers. Without this, every visitor shares one IP and the
        // public-form rate limit (5/min) applies to everyone at once.
        $middleware->trustProxies(at: '*');
```

Keep everything else in that file (including `redirectGuestsTo`) unchanged.

### 1b. Upgrade Laravel 11 → 12 (security support for 11 has ended)

In the `backend` folder:

```powershell
composer require laravel/framework:^12.0 laravel/tinker:^2.10.1 -W
composer require --dev phpunit/phpunit:^11.5 nunomaduro/collision:^8.6 -W
php artisan test
composer audit
```

All tests must pass and `composer audit` must report no advisories before deploying.
This creates/updates `composer.lock` — commit it. The Docker build refuses to run without it.

## 2. Backend on Railway

1. railway.com → New Project → **Deploy from GitHub repo** → `dipeshc21/Inquiry-Portal`.
2. Service **Settings → Root Directory**: `/backend` (the Dockerfile is detected automatically).
3. In the project: **+ New → Database → MySQL**.
4. Service **Settings → Volumes → Add volume**, mount path: `/var/www/html/storage`
   (keeps uploaded attachments across redeploys).
5. Service **Settings → Networking → Generate Domain**, target port `8080`.
6. Service **Settings → Healthcheck Path**: `/up`.
7. Service **Variables** (Raw Editor):

```
APP_NAME="Inquiry Management Portal"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:PASTE_FROM_KEY_GENERATE
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
FRONTEND_URL=https://inquiry-portal-kohl.vercel.app
LOG_CHANNEL=stderr
LOG_LEVEL=info
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=180
FILESYSTEM_DISK=public
MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="${APP_NAME}"
SANCTUM_TOKEN_EXPIRATION=10080
INQUIRY_AUTO_ASSIGN=true
INQUIRY_ASSIGNMENT_STRATEGY=round_robin
```

   Generate `APP_KEY` on your laptop with `php artisan key:generate --show` (in `backend`).
   If your MySQL service is not named `MySQL`, adjust the `${{MySQL....}}` references.

8. Deploy. Migrations run automatically on start. The demo seeder is **not** run.
9. Create the first administrator (install the Railway CLI: `npm i -g @railway/cli`):

```powershell
railway login
railway link          # pick the project and the backend service
railway ssh
php artisan inquiry:create-admin
```

   Then create managers/agents from the Users page in the portal.

Check: open `https://YOUR-BACKEND.up.railway.app/up` (should show a green "Application up" page)
and `https://YOUR-BACKEND.up.railway.app/api/v1/auth/me` (should return JSON 401 "Authentication is required.").

## 3. Frontend on Vercel

Project **Settings**:
- Root Directory: `frontend`, Framework: Vite, Build: `npm run build`, Output: `dist`, Node 22.x
- Environment Variables → `VITE_API_URL` = `https://YOUR-BACKEND.up.railway.app/api/v1`
  (tick Production and Preview)

Deployments → latest → ⋯ → **Redeploy**. The build now fails with a clear message if
`VITE_API_URL` is missing, not https, or points to localhost.

## 4. Verification checklist (do not call it done until all pass)

- [ ] DevTools → Network: `login` request goes to the Railway domain, not localhost.
- [ ] Admin logs in; dashboard loads.
- [ ] Public form (`/inquiry`) submits and shows a reference number; it appears in the list.
- [ ] Submission with an attachment; download works; still downloadable after a Railway redeploy.
- [ ] Direct URL `https://YOUR-BACKEND/storage/attachments/...` is denied (403).
- [ ] Agent account sees only assigned inquiries; cannot delete or export.
- [ ] Emails: with `MAIL_MAILER=log` they appear in Railway logs (switch to SMTP to actually send).
- [ ] Reminder due in 2 minutes triggers (scheduler + queue running; check logs).
- [ ] Refreshing `/inquiries/1` on Vercel does not 404 (vercel.json rewrite).
