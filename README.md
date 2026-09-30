# Inquiry Management Portal

A customer inquiry management application built with Laravel 11, MySQL 8,
Laravel Sanctum, React 18, Vite, and Tailwind CSS.

## Project structure

```text
inquiry-portal/
├── backend/
├── frontend/
├── postman/
│   └── Inquiry-Management-Portal.postman_collection.json
└── README.md
```

## Implementation status

The source is delivered across the numbered implementation parts.

Automated tests and a production build have not been executed as part of this
source-code delivery. Run the checks below after assembling all files.

Commit the generated `composer.lock` and `package-lock.json` after resolving
dependencies and completing verification. Subsequent installations should use
those lock files.

## Assumptions and behavior

- Public visitors submit inquiries without creating an account.
- Administrators create staff accounts.
- Agents only access inquiries currently assigned to them.
- Managers and administrators can view all active inquiries.
- Notes are internal. Staff replies are stored and queued for email delivery.
- There is no inbound email ingestion. Customer message history initially
  contains the public form submission.
- Open statuses are `new`, `contacted`, and `qualified`.
- `pending` is counted separately.
- Closed statuses are `won` and `lost`.
- Reopening an inquiry clears `closed_at`.
- Least-loaded assignment counts open and pending inquiries.
- Reference numbers support up to 9,999 inquiries per calendar day.
- Reminders belong to the staff member who creates them.
- Upcoming reminders cover the next 30 days by default.
- Soft-deleted inquiries retain their related records and attachments.
- Users with authored notes or reminders must be deactivated rather than
  deleted, preserving required authorship relationships.
- Changing a user's email, password, role, or active state revokes their tokens.
- Authentication tokens are stored in browser session storage.
- CSV exports honor filters and optionally selected inquiry IDs.
- Dashboard results are cached for 60 seconds and invalidated after inquiry
  mutations commit.
- The database full-text index is present in MySQL. The current search endpoint
  uses literal substring matching to support phone and partial reference
  searches consistently.
- Database timestamps use UTC by default. The frontend displays timestamps in
  the user's device time zone.

## Prerequisites

### Backend

- PHP 8.2 or newer, compatible with Laravel 11.
- Composer 2.
- MySQL 8.
- PHP extensions required by Laravel, including:
  - Ctype
  - cURL
  - DOM
  - Fileinfo
  - Filter
  - Hash
  - Mbstring
  - OpenSSL
  - PCRE
  - PDO
  - PDO MySQL
  - Session
  - Tokenizer
  - XML
- PDO SQLite for the default portable test suite.

### Frontend

- Node.js 20.19 or newer.
- npm.

### Local development processes

Run these processes in separate terminals:

1. Laravel HTTP server.
2. Laravel queue worker.
3. Laravel scheduler.
4. Vite development server.

## Database creation

Connect to MySQL with an administrative account:

```sql
CREATE DATABASE inquiry_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'inquiry_app'@'localhost'
    IDENTIFIED BY 'replace-with-a-strong-local-password';

GRANT ALL PRIVILEGES ON inquiry_portal.*
    TO 'inquiry_app'@'localhost';
```

Set the corresponding credentials in `backend/.env`.

## Backend setup

From the repository root:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, replace the copy command with:

```powershell
Copy-Item .env.example .env
```

Update these values in `.env`:

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inquiry_portal
DB_USERNAME=inquiry_app
DB_PASSWORD=replace-with-a-strong-local-password

CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

Create the schema and demo data:

```bash
php artisan migrate --seed
php artisan storage:link
```

Start the API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```bash
cd backend
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=90
```

In a third terminal:

```bash
cd backend
php artisan schedule:work
```

The worker timeout must remain lower than `QUEUE_RETRY_AFTER`, which defaults
to 180 seconds.

The local development server is for trusted local use. It does not enforce
the attachment access rules in the supplied Nginx configuration. Do not
expose it publicly or use it for sensitive attachments.

## Frontend setup

In a fourth terminal, from the repository root:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Frontend environment:

```dotenv
VITE_API_URL=http://localhost:8000/api/v1
VITE_APP_NAME=Inquiry Management Portal
```

Open:

- Public form: http://localhost:5173/inquiry
- Staff login: http://localhost:5173/login
- Dashboard: http://localhost:5173/dashboard

Use `localhost:5173` consistently with the configured CORS origin.
If you use a different frontend hostname or port, update `FRONTEND_URL`
and clear the backend configuration cache.

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@example.com | password |
| Manager | manager@example.com | password |
| Agent | agent1@example.com | password |
| Agent | agent2@example.com | password |
| Agent | agent3@example.com | password |

The seeder creates:

- Two teams.
- Five staff accounts.
- Sixty inquiries spread across the previous 90 days.
- Mixed sources, priorities, statuses, and assignments.
- Customer messages, staff replies, notes, reminders, and activity records.

Demo passwords are intentionally simple. Newly created staff passwords must
have at least 12 characters, uppercase and lowercase letters, and a number.

Do not run demo seeders against a production database.

## Email configuration

### Log transport

The default configuration writes email content to:

```text
backend/storage/logs/laravel.log
```

The queue worker must be running for queued notifications.

### Mailtrap

Set:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Inquiry Management Portal"
```

Then run:

```bash
php artisan config:clear
php artisan queue:restart
```

New inquiry notifications go to active administrators, active managers,
and the assigned active agent. Customers receive an acknowledgement with
the reference number.

Staff replies are also queued for email delivery.

## Assignment settings

Default settings are defined in:

```text
backend/config/inquiry.php
```

Environment defaults:

```dotenv
INQUIRY_AUTO_ASSIGN=true
INQUIRY_ASSIGNMENT_STRATEGY=round_robin
```

Administrators can change settings from the Settings page or API.

Database settings override configuration defaults.

### Round robin

Selects the active agent with the oldest assignment timestamp.
Agents without prior assignments are considered first.

### Least loaded

Selects the active agent with the fewest unresolved inquiries.
Pending inquiries count as unresolved. Assignment recency resolves ties.

Assignment decisions are serialized using a database lock row.

## Reminders

The scheduler runs:

```bash
php artisan reminders:send
```

every minute.

To trigger it manually:

```bash
php artisan reminders:send
```

Due, incomplete, unnotified reminders are dispatched to the database queue.

The delivery job:

1. Reloads and locks the reminder.
2. Checks completion, due time, and previous notification state.
3. Checks the recipient is active and can still view the inquiry.
4. Sends the email.
5. Sets `notified_at`.
6. Records an activity entry.

A reminder does not become completed merely because its notification was sent.

Email delivery is not exactly-once: an interruption after the mail server
accepts a message but before the database commit can produce a duplicate
on retry.

## Authentication

Login returns a Sanctum Bearer token.

Include it on protected requests:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Default token lifetime is seven days:

```dotenv
SANCTUM_TOKEN_EXPIRATION=10080
```

Logout revokes the current token.

## Response format

Successful JSON response:

```json
{
  "success": true,
  "message": "Inquiry retrieved successfully.",
  "data": {
    "id": 1,
    "reference_no": "INQ-20260930-0001",
    "status": "new"
  },
  "meta": {}
}
```

Paginated response:

```json
{
  "success": true,
  "message": "Inquiries retrieved successfully.",
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0,
    "from": null,
    "to": null
  }
}
```

Validation failure:

```json
{
  "success": false,
  "message": "Please correct the highlighted fields.",
  "errors": {
    "email": ["Enter a valid email address."]
  }
}
```

CSV exports and attachment downloads return file responses rather than JSON.

### HTTP status codes

| Code | Meaning |
| --- | --- |
| 200 | Successful read, update, or delete |
| 201 | Resource created |
| 401 | Missing, expired, revoked, or invalid authentication |
| 403 | Forbidden |
| 404 | Resource not found |
| 409 | Conflict with account or relationship constraints |
| 422 | Validation failure |
| 429 | Rate limit exceeded |
| 500 | Unexpected server failure |

## API endpoint reference

All paths below use the `/api/v1` prefix.

“A/M” means administrator or manager. “Staff” includes agents, subject to
inquiry assignment policies.

| Method | Path | Access | Request / result |
| --- | --- | --- | --- |
| POST | `/public/inquiries` | Public | Multipart inquiry; returns reference |
| POST | `/auth/login` | Public | Email and password; returns token and user |
| POST | `/auth/logout` | Staff | Revokes current token |
| GET | `/auth/me` | Staff | Current user |
| GET | `/inquiries` | Staff | Filtered, sorted, paginated inquiries |
| GET | `/inquiries/export` | A/M | Filtered CSV; optional `ids[]` |
| GET | `/inquiries/{inquiry}` | Staff | Inquiry and related detail records |
| PUT | `/inquiries/{inquiry}` | Staff | Editable contact, source, and priority fields |
| PATCH | `/inquiries/{inquiry}/status` | Staff | `status`, optional `note` |
| PATCH | `/inquiries/{inquiry}/assign` | A/M | `assigned_to` or null |
| DELETE | `/inquiries/{inquiry}` | Admin | Soft delete |
| GET | `/inquiries/{inquiry}/messages` | Staff | Paginated message history |
| POST | `/inquiries/{inquiry}/messages` | Staff | `body`; saves and queues reply |
| GET | `/inquiries/{inquiry}/notes` | Staff | Paginated notes |
| POST | `/inquiries/{inquiry}/notes` | Staff | `body`; creates internal note |
| PUT | `/notes/{note}` | Owner/admin | `body`; inquiry access also required |
| DELETE | `/notes/{note}` | Owner/admin | Deletes note; inquiry access required |
| GET | `/inquiries/{inquiry}/reminders` | Staff | Paginated inquiry reminders |
| POST | `/inquiries/{inquiry}/reminders` | Staff | `title`, `remind_at` |
| GET | `/reminders/upcoming` | Staff | Current user's reminders; `status` filter |
| PATCH | `/reminders/{reminder}/complete` | Owner/A/M | Marks complete; inquiry access required |
| DELETE | `/reminders/{reminder}` | Owner/A/M | Deletes reminder; inquiry access required |
| POST | `/inquiries/{inquiry}/attachments` | Staff | Multipart `files[]` |
| GET | `/attachments/{attachment}/download` | Staff | Authorized download |
| DELETE | `/attachments/{attachment}` | Staff | Deletes attachment |
| GET | `/inquiries/{inquiry}/activity` | Staff | Paginated inquiry timeline |
| GET | `/dashboard/stats` | Staff | Role-scoped counts and chart data |
| GET | `/users/assignable` | A/M | Active agents |
| GET | `/users` | Admin | Paginated users; optional `q` |
| POST | `/users` | Admin | Creates staff account |
| GET | `/users/{user}` | Admin | User detail |
| PUT | `/users/{user}` | Admin | Updates account; password optional |
| DELETE | `/users/{user}` | Admin | Deletes eligible account |
| GET | `/teams` | Admin | Paginated teams; optional `q` |
| POST | `/teams` | Admin | `name` |
| GET | `/teams/{team}` | Admin | Team detail |
| PUT | `/teams/{team}` | Admin | `name` |
| DELETE | `/teams/{team}` | Admin | Deletes team; clears member team IDs |
| GET | `/activity-logs` | Admin | Paginated global activity |
| GET | `/settings/assignment` | Admin | Assignment configuration |
| PUT | `/settings/assignment` | Admin | `auto_assign`, `strategy` |

### Public inquiry example

```bash
curl -X POST http://localhost:8000/api/v1/public/inquiries \
  -H "Accept: application/json" \
  -F "name=Jamie Carter" \
  -F "email=jamie@example.com" \
  -F "subject=Product demonstration" \
  -F "message=Please arrange a demonstration for our customer support team." \
  -F "source=website" \
  -F "honeypot="
```

Response:

```json
{
  "success": true,
  "message": "Your inquiry has been received. We will contact you shortly.",
  "data": {
    "reference_no": "INQ-20260930-0001"
  },
  "meta": {}
}
```

Optional uploads use repeated `files[]` fields:

```bash
-F "files[]=@requirements.pdf"
```

Up to three files are allowed per request, each at most 5 MB.
Supported extensions: PDF, DOC, DOCX, JPG, JPEG, PNG.

The endpoint allows five requests per minute per IP address.

### Login example

```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Response data:

```json
{
  "token": "1|example-token-value",
  "token_type": "Bearer",
  "expires_at": "2026-10-07T09:00:00+00:00",
  "user": {
    "id": 1,
    "name": "Alex Morgan",
    "email": "admin@example.com",
    "role": "admin",
    "is_active": true
  }
}
```

### Inquiry filtering

Supported parameters:

```text
q
status[]
source[]
priority
assigned_to
unassigned=true
date_from=YYYY-MM-DD
date_to=YYYY-MM-DD
sort_by=created_at|name|status|priority
sort_dir=asc|desc
page
per_page
ids[]
```

`per_page` defaults to 15 and cannot exceed 100.

`date_to` includes the entire specified day in the application time zone.

Example:

```text
/api/v1/inquiries?status[]=new&status[]=qualified&priority=high&per_page=30
```

CSV export accepts the same filters. Pagination parameters do not limit export.
`ids[]` restricts export to selected records that also match the filters.

### Update inquiry example

```json
{
  "name": "Jamie Carter",
  "email": "jamie@example.com",
  "phone": "+1 202 555 0134",
  "company": "Carter Operations",
  "subject": "Enterprise demonstration",
  "source": "referral",
  "priority": "high"
}
```

Use the dedicated status and assignment endpoints for those fields.
The original customer message cannot be edited.

### Status example

```json
{
  "status": "qualified",
  "note": "Requirements confirmed during the introductory call."
}
```

### Assignment example

```json
{
  "assigned_to": 3
}
```

Unassign:

```json
{
  "assigned_to": null
}
```

### Reply or note example

```json
{
  "body": "Please arrange a follow-up call next week."
}
```

### Reminder example

```json
{
  "title": "Follow up on proposal",
  "remind_at": "2026-10-15T10:00:00Z"
}
```

Supply a future timestamp. The frontend converts local input to UTC.

Reminder list filters:

```text
status=upcoming
status=overdue
status=completed
status=all
```

Omitting `status`, or using `all`, returns incomplete reminders, including
overdue and future reminders.

### User creation example

```json
{
  "name": "Morgan Reed",
  "email": "morgan@example.com",
  "password": "ExamplePassword123",
  "password_confirmation": "ExamplePassword123",
  "role": "agent",
  "team_id": 1,
  "is_active": true
}
```

For updates, omit both password fields to preserve the current password.

### Assignment settings example

```json
{
  "auto_assign": true,
  "strategy": "least_loaded"
}
```

### Activity filters

```text
q
inquiry_id
user_id
action
date_from
date_to
page
per_page
```

## Database relationships

- `teams` has many `users`.
- `users.team_id` is nullable and becomes null when a team is deleted.
- `users` has many assigned `inquiries` through `inquiries.assigned_to`.
- `inquiries` has many `inquiry_messages`, `notes`, `reminders`,
  `attachments`, and `activity_logs`.
- `inquiry_messages.user_id` is nullable for customer messages.
- `notes.user_id` identifies the author.
- `reminders.user_id` identifies the reminder owner.
- `attachments.uploaded_by` is nullable for public form uploads.
- Activity records can exist without an inquiry or user.
- Inquiries use soft deletes.
- `reference_sequences` allocates daily reference numbers.
- `assignment_locks` serializes assignment and related administrative changes.
- `settings` stores administrator overrides for assignment configuration.
- Sanctum tokens are stored in `personal_access_tokens`.
- Queue state uses `jobs`, `job_batches`, and `failed_jobs`.
- Database caching uses `cache` and `cache_locks`.

### Important indexes

- Inquiry reference number: unique.
- User email: unique.
- Inquiry status, source, priority, email, assignee, and creation time.
- Composite inquiry indexes on status/creation time and assignee/status.
- MySQL full-text index on inquiry name, email, subject, and message.
- Reminder due time/completion and owner/completion/due time.
- Activity inquiry/creation time.

## Running tests

From `backend`:

```bash
php artisan config:clear
php artisan test
```

Or:

```bash
composer test
```

The supplied PHPUnit configuration uses an in-memory SQLite database and
fake mail/queue transports.

Run individual suites:

```bash
php artisan test --filter=PublicInquiryTest
php artisan test --filter=AuthorizationTest
php artisan test --filter=AssignmentTest
php artisan test --filter=DashboardTest
php artisan test --filter=ReminderTest
```

Tests cover:

- Public submission, required validation, honeypot, and rate limits.
- Attachment validation.
- Login, account activation, and logout.
- Inquiry filters and pagination limits.
- Status changes and reopening.
- Staff reply persistence and queued mail.
- Agent visibility and role restrictions.
- Note ownership.
- Authorized attachment downloads.
- Soft deletion.
- Dashboard counts, cache invalidation, and aggregate query count.
- CSV filters, selected IDs, and formula neutralization.
- Round-robin and least-loaded assignment.
- Reminder dispatch, delivery, completion, and access after reassignment.

SQLite tests do not verify MySQL locking or full-text index behavior.
Use a separate MySQL test database for integration verification before release.
Do not point tests at a development or production database containing data.

To run against MySQL, copy `phpunit.xml` to a separate configuration file and
replace its forced `DB_CONNECTION` and `DB_DATABASE` entries with your dedicated
test database settings. Then run PHPUnit with that configuration.

### Code style

```bash
composer lint
composer format
```

### Frontend build

From `frontend`:

```bash
npm run build
npm run preview
```

Preview runs on port 4173. To test API calls from that origin, temporarily set
`FRONTEND_URL=http://localhost:4173` and clear the Laravel configuration cache.

## Postman

Import:

```text
postman/Inquiry-Management-Portal.postman_collection.json
```

Collection variables include:

- `baseUrl`
- `token`
- `loginEmail`
- `loginPassword`
- `inquiryId`
- `noteId`
- `reminderId`
- `attachmentId`
- `agentId`
- `userId`
- `teamId`

Run Login first to capture the token.

The public submission request captures the reference number. The inquiry list
request resolves the corresponding inquiry ID for subsequent requests.

File fields are disabled by default. Select a local file and enable the
appropriate field to test uploads.

Delete requests are grouped separately and should be run intentionally.
The final folder includes logout, which invalidates the collection token.

## Production deployment

1. Assemble and verify all source files.
2. Resolve dependencies and commit lock files.
3. Configure a production MySQL database.
4. Configure HTTPS and the frontend origin.
5. Set `APP_ENV=production` and `APP_DEBUG=false`.
6. Set a production `APP_KEY` and real mail credentials.
7. Install dependencies using the committed lock files.
8. Run migrations without demo seeding.
9. Create the initial administrator.
10. Configure a supervised queue worker and scheduler.
11. Build and host the frontend.
12. Verify attachment access controls and role permissions.

Backend commands:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan queue:restart
```

Create the initial administrator interactively:

```bash
php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Administrator';
$user->email = 'admin@your-company.example';
$user->password = 'replace-with-a-unique-strong-password';
$user->role = 'admin';
$user->is_active = true;
$user->save();
```

Use a unique password that meets your organization's requirements.

### Web server

An Nginx example is provided at:

```text
backend/deploy/nginx.conf.example
```

Adapt its hostname, root directory, TLS setup, and PHP-FPM socket.

The server must deny direct requests to:

```text
/storage/attachments
/storage/attachments/*
```

The requested public disk and storage symlink would otherwise expose stored
attachments without authentication. Only the authorized download API should
serve these files.

Apache users must enable the supplied `.htaccess` rules and the required
rewrite module.

### Frontend hosting

Build:

```bash
npm ci
npm run build
```

Serve `frontend/dist` as static files.

Configure SPA fallback so frontend paths such as `/inquiries/42` return
`index.html`. Keep API routing on the backend host.

`VITE_API_URL` is embedded at build time. Rebuild when changing it.

### Queue worker

Run under a process supervisor:

```bash
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=90
```

Restart workers after deployments:

```bash
php artisan queue:restart
```

Inspect failed jobs:

```bash
php artisan queue:failed
```

Retry a specific failed job:

```bash
php artisan queue:retry JOB_UUID
```

### Scheduler

Configure one scheduled invocation every minute:

```cron
* * * * * cd /var/www/inquiry-portal/backend && php artisan schedule:run >> /dev/null 2>&1
```

The database cache supports the scheduler's overlap and single-server locks.

### Backups and retention

Back up the MySQL database and `storage/app/public/attachments` together.

No automatic permanent purge of soft-deleted inquiries or attachment retention
policy is included. Define these according to the deployment's requirements.
