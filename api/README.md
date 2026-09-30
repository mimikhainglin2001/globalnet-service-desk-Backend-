# GlobalNet Service Desk – Laravel API

REST API (`/api/v1`) for the GlobalNet customer request & ticket portal: role-based ticketing,
SLA tracking, a transactional outbox for notifications, idempotent writes and an audit log.

- Laravel 13 · PHP 8.4 · MySQL 8 · Sanctum tokens · database queue
- OpenAPI spec: [`docs/openapi.yaml`](docs/openapi.yaml)

## Demo credentials

All seeded accounts use the password **`Password123!`** (configurable with `DEMO_USER_PASSWORD`).

| Role     | Email                      | Teams                                |
|----------|----------------------------|--------------------------------------|
| Admin    | `admin@globalnet.test`     | –                                    |
| Agent    | `agent@globalnet.test`     | Technical Support, Customer Success  |
| Agent    | `agent2@globalnet.test`    | Billing Support, Customer Success    |
| Customer | `customer@globalnet.test`  | –                                    |
| Customer | `customer2@globalnet.test` | –                                    |
| Customer | `customer3@globalnet.test` | –                                    |

The seeders also create 3 teams, 6 categories (each routed to a team), SLA rules
(Urgent 4h, High 8h, Normal 24h, Low 72h) and 10 sample tickets across all statuses.
Seeders are idempotent, so `db:seed` is safe to run again in production.

## Running locally

### With Docker (one command)

From the repository root:

```bash
docker compose up --build
```

| Service     | Purpose                                                    |
|-------------|------------------------------------------------------------|
| `mysql`     | MySQL 8, exposed on `localhost:3316`                       |
| `app`       | PHP-FPM; runs migrations and seeders on start              |
| `web`       | nginx, API on **http://localhost:8080/api/v1**             |
| `queue`     | `php artisan queue:work` (outbox consumer, retries)        |
| `scheduler` | `php artisan schedule:work` (SLA check, outbox relay)      |

An `APP_KEY` is generated on first start and shared with the worker containers through the
storage volume. Mail goes to the container log (`MAIL_MAILER=log`).

### Without Docker

Requirements: PHP 8.4+, Composer, MySQL 8 (or `docker compose up mysql` for just the database).

```bash
cd api
cp .env.example .env          # set DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000/api/v1

# in two more terminals
php artisan queue:work --tries=5
php artisan schedule:work
```

Emails (password reset, notifications) are written to `storage/logs/laravel.log`.

## Tests and linting

```bash
composer test     # PHPUnit – SQLite in-memory, no MySQL needed
composer lint     # Laravel Pint (--test)
./vendor/bin/pint # auto-fix
```

57 tests cover authentication (rate limiting, password reset), policies including negative cases
(customers vs other customers' tickets and internal notes, agents outside their team, admin-only
endpoints), the status state machine and reopen window, ticket creation (reference, SLA due date,
routing), idempotency (replay, conflict, per-user scope, expiry), attachment validation, the
outbox (idempotent consumer, dead-lettering + admin retry, relay), the SLA command and the
dashboard. GitHub Actions runs Pint and the tests on every push (`.github/workflows/ci.yml`).

## Architecture

```
HTTP ──► FormRequest (validation) ──► Controller (thin: authorize → service → resource)
                                           │
                                           ▼
                           Services (use cases, transactions, business rules)
                           ├─ TicketService        create / update / assign
                           ├─ TicketStatusService  the only place status changes
                           ├─ CommentService, AttachmentService, SlaService
                           ├─ IdempotencyService   Idempotency-Key handling
                           └─ OutboxService        records domain events
                                           │
                     Repositories (Contracts/ + Repositories/) for ticket queries
                                           │
                                     Eloquent models ──► Auditable trait ──► audit_logs
                                           │
                          outbox_events ──► ProcessOutboxEvent job ──► OutboxEventProcessor
                                                                     └─► TicketNotificationService
                                                                         (database + mail)
```

| Folder                  | Responsibility                                                                 |
|-------------------------|--------------------------------------------------------------------------------|
| `app/Http/Controllers`  | Thin controllers, one per resource, versioned under `Api/V1`                   |
| `app/Http/Requests`     | Validation and request → DTO mapping                                           |
| `app/Http/Resources`    | JSON shape of every response                                                   |
| `app/DTOs`              | Typed input objects (`CreateTicketData`, `TicketFilters`)                      |
| `app/Services`          | Application use cases and business rules                                       |
| `app/Contracts`, `app/Repositories` | Ticket/comment persistence behind interfaces (bound in `AppServiceProvider`) |
| `app/Enums`             | `TicketStatus` (holds the state machine graph), `TicketPriority`, `Role`, …    |
| `app/Events`            | Domain events (`TicketCreated`, `TicketAssigned`, `TicketStatusChanged`, `TicketSlaBreached`) |
| `app/Policies`          | Row-level authorization (`TicketPolicy`, `AttachmentPolicy`) + `admin`/`staff` gates |
| `app/Models/Concerns`   | `Auditable` trait                                                              |
| `config/servicedesk.php`| All business settings (SLA fallback, reopen window, upload limits, TTLs, rate limits) |

### Key decisions and trade-offs

- **Services + selective repositories.** Business rules live in services; controllers only
  authorize, delegate and shape the response. A repository exists for tickets because their
  queries are non-trivial (role scoping, many filters, full-text search, severity sort). Plain
  admin CRUD (teams, categories, SLA rules) uses Eloquent directly – a repository there would
  only add indirection.
- **Sanctum bearer tokens, not SPA cookies.** The SPA (Vercel) and API run on different
  domains. Cookie auth would need `SameSite=None` third-party cookies, a shared parent domain
  and CSRF handling, and is increasingly blocked by browsers. Tokens are sent explicitly in the
  `Authorization` header, so CSRF does not apply; the trade-off is that the SPA must protect the
  token from XSS (kept in memory/localStorage, React escapes output, no `dangerouslySetInnerHTML`).
  Tokens are revoked on logout, password reset and role change; `SANCTUM_EXPIRATION` can bound
  their lifetime.
- **Authorization on the server.** `Ticket::scopeVisibleTo()` restricts every list and dashboard
  query (customer → own, agent → own teams or assigned, admin → all); `TicketPolicy` guards
  every single-ticket action with explicit deny messages. Internal notes are removed in the SQL
  query for customers, not in the UI. `role` is not mass-assignable, so registration can never
  create staff.
- **State machine in one place.** `TicketStatus::allowedTransitions()` is the graph;
  `TicketStatusService` enforces it (plus the 7-day reopen window and timestamps) under a row
  lock; `TicketPolicy::changeStatus()` decides *who* may request a transition (customers: close
  or reopen only). `allowed_transitions` in the ticket resource tells the UI which buttons to show.
- **Sequential references.** `GN-000123` is derived from the auto-increment id right after the
  insert (inside the same transaction), so it is unique without a separate sequence table.
- **SLA.** `due_at` is computed on creation from the active rule for the priority (falling back
  to `config/servicedesk.php`). Changing the priority re-derives it from the creation time.
  Editing a rule does not retroactively change existing tickets.
- **Audit log via a model trait.** `Auditable` hooks `created/updated/deleted` on Ticket, User
  (role only), SlaRule, Team and Category, so no service can forget to log. It runs in the same
  transaction as the change. Actions by the scheduler have `user = null` (system).
- **Consistent errors.** `App\Exceptions\ApiExceptionRenderer` renders every error as
  `{message, code, errors?}` and hides model class names and stack traces.
- **N+1 prevention.** Every list eager-loads its relations; `Model::preventLazyLoading()` is
  enabled outside production so a lazy load fails loudly in development and tests. Indexes:
  `(status, priority)`, `(team_id, status)`, `(requester_id, status)`, `assignee_id`, `due_at`,
  `(status, due_at, sla_breached_at)` for the SLA scan, `created_at`, `resolved_at`, FULLTEXT on
  `subject, description` (MySQL), `audit_logs.created_at`, `(processed_at, failed_at, created_at)`
  for the outbox relay. Every list endpoint is paginated.

## Reliable messaging

### Transactional outbox

1. A service changes a ticket **and** calls `OutboxService::record(new TicketXxx(...))` inside the
   same `DB::transaction`. The event row (`outbox_events`, with a UUID `event_id`) is committed
   atomically with the change, or not at all. Recording outside a transaction throws.
2. After commit, `ProcessOutboxEvent` is dispatched (`afterCommit`) for low latency.
3. `outbox:dispatch` (every minute) is the **relay/safety net**: it re-dispatches pending events
   older than 60 s whose job was lost (e.g. the queue was down at commit time).
   `ShouldBeUnique` prevents the relay from queueing duplicates.

### Idempotent consumer

`OutboxEventProcessor` does everything in one transaction: lock the outbox row → skip if already
processed → claim the `event_id` with `INSERT IGNORE` into `processed_events` (unique index) →
send notifications → mark the row processed. A redelivered event either sees `processed_at` or
fails to claim the `event_id`, so the database notification is never written twice.

*Crash after sending but before commit:* the transaction rolls back, including the database
notification and the `processed_events` claim, and the retry sends again. Database notifications
are therefore exactly-once; mail (an external side effect) is at-least-once, and a crash between
the SMTP call and the commit could duplicate an email. That is the usual outbox trade-off; a
provider-side idempotency key would close it.

### Retries and dead letters

`ProcessOutboxEvent` retries 5 times with backoff `10s, 30s, 60s, 120s` (configurable). After the
last attempt Laravel stores it in `failed_jobs` and `failed()` marks the outbox row `failed_at` with
`last_error`, which stops the relay from retrying it forever. Admins inspect and retry via
`GET /admin/failed-jobs`, `POST /admin/failed-jobs/{uuid}/retry`,
`GET /admin/outbox-events?status=failed` and `POST /admin/outbox-events/{id}/retry`.

Events and recipients:

| Event                   | Notified                                       |
|-------------------------|------------------------------------------------|
| `ticket.created`        | requester + agents of the ticket's team        |
| `ticket.assigned`       | new assignee + requester (not the actor)       |
| `ticket.status_changed` | requester + assignee (not the actor)           |
| `ticket.sla_breached`   | assignee (or the whole team) + all admins      |

Channels: `database` (read by the notification bell endpoints) and `mail`.

### Idempotent ticket creation

`POST /tickets` accepts an optional `Idempotency-Key` header. `IdempotencyService`:

- fingerprints the request (SHA-256 of the validated fields plus each file's name, size and hash);
- in **one transaction** locks any existing `(user_id, key)` row; if one exists and is younger than
  24 h, it replays the stored status and body (`Idempotent-Replayed: true`), or returns **409** if
  the fingerprint differs;
- otherwise it creates the ticket and stores the key with the response, atomically. Two
  concurrent first requests race on the unique `(key, user_id)` index; the loser rolls back and
  replays the winner's response (files from the losing attempt are deleted).

Keys are scoped per user and purged daily by `idempotency:purge`.

## Scheduled commands

| Command             | Schedule        | Purpose                                                       |
|---------------------|-----------------|---------------------------------------------------------------|
| `sla:check`         | every 5 minutes | Flags overdue active tickets once (row lock) and emits `ticket.sla_breached` |
| `outbox:dispatch`   | every minute    | Outbox relay for events whose job was lost                    |
| `idempotency:purge` | daily           | Deletes expired idempotency keys                              |

## Security notes

- Rate limits: login/register 5/min per email+IP (and 20/min per IP), password reset 3/min,
  API 120/min per user.
- Uploads: max 3 per ticket, 2 MB each, extension **and** MIME whitelist, stored on the private
  disk under a random UUID name; downloads go through `AttachmentPolicy`.
- Password reset links point to the SPA (`FRONTEND_URL/reset-password?token=…`); the endpoint never
  reveals whether an email exists; all tokens are revoked after a reset.
- CORS allows only `CORS_ALLOWED_ORIGINS` (plus an optional regex for Vercel previews).

## Deployment notes

Configure through environment variables (see `.env.example`):

- `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<api-host>`
- `DB_*` for the hosted MySQL
- `FRONTEND_URL=https://<app>.vercel.app` and `CORS_ALLOWED_ORIGINS=https://<app>.vercel.app`
- `QUEUE_CONNECTION=database`, `MAIL_MAILER=log` (or real SMTP)

The Docker image runs migrations/seeders on start when `RUN_MIGRATIONS=true` / `RUN_SEEDERS=true`.
Run the same image three times: `php-fpm` (behind nginx), `php artisan queue:work` and
`php artisan schedule:work`. If the host cannot run a long-lived worker, schedule
`php artisan queue:work --stop-when-empty` every minute instead: the outbox relay guarantees
nothing is lost, at the cost of up to a minute of notification latency.

## Known limitations / next steps

- Dashboard statistics are computed live; next step is a short Redis cache invalidated by the
  same domain events.
- Mail notifications are at-least-once (see above).
- No real-time push yet (Reverb/Echo); the SPA polls the unread-count endpoint.
- Attachments use the local disk; production should use S3-compatible storage (`ATTACHMENTS_DISK`).
