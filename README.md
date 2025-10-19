# MovieRec — All-in-One Ultimate (Laravel overlay)

MovieRec bundles every feature shipped across the previous MovieRec releases into a single Laravel overlay. The project delivers ingestion pipelines, A/B-tested movie recommendations, SVG CTR visualisations, TMDB-powered localisation, RSS feeds, on-the-fly SSR metrics, Filament dashboards, optimised database indexes, automated tests, and strict PHPStan level 7 typing.

👉 Track outstanding integration tasks in the project [TODO list](TODO.md).

---

## Project trackers

- [TODO backlog](TODO.md) — domain-grouped analytics, observability, and infrastructure backlog
- [Works log](WORKS.md)
- [Feature catalog](docs/features.md)

## Onboarding callouts

- Review the dated backlog in [`TODO.md`](TODO.md) during your first sync so owners stay aligned on analytics, SSR, and ingestion follow-ups.

---

## Feature overview

- [Project TODO](TODO.md) — live backlog of outstanding implementation work.

- **Data ingestion & enrichment** – ready-to-run import jobs for TMDB/OMDb metadata with translation support and background queues.
- **Recommendation engines** – dual strategy A/B tests combining popularity, recency, and personal preference weights (`REC_A_*` / `REC_B_*` env flags).
- **Analytics & reporting** – SVG click-through-rate graphs (lines & bars), funnels by genre/year, z-test comparison tooling, and RSS trend feeds.
- **Filament admin** – Analytics panel with CTR, trends, queue, Horizon, SSR insights, and on-call drop detection widgets.
- **SSR observability** – On-the-fly metrics middleware, issues API, and device fingerprint cookie guard.
- **Quality gates** – PHPUnit feature/unit coverage, PHPStan level 7, Pint formatting, and helpful helper/service providers.
- **Performance** – Database indexes and caching presets with Redis/Horizon integration.

## Works log

Review the consolidated delivery log in [WORKS.md](WORKS.md) or visit `/works` when the application is running to explore the list via the web UI.

---

## Requirements

- PHP 8.3+
- Composer 2.6+
- Node.js 20+ and npm (for Vite/Tailwind builds)
- SQLite (default), or MySQL/PostgreSQL if you adjust `.env`
- Redis (optional, required for Horizon and redis cache store)

---

## Quick start (fresh Laravel 11 project)

```bash
composer create-project laravel/laravel movierec
cd movierec

# Unpack this overlay on top of the clean install
unzip /path/to/movierec_all_in_one_ultimate.zip -d .

composer require filament/filament:"^3.0" -W
composer require predis/predis symfony/uid

# Optional dev tooling
composer require --dev laravel/pint nunomaduro/larastan:^2.9 phpstan/phpstan:^1.11

php artisan migrate

# Optional: Horizon dashboard backed by Redis
composer require laravel/horizon
php artisan horizon:install
```

Install the frontend dependencies and start Vite if you are working with the UI:

```bash
npm install
npm run dev   # use npm run build for production assets
```

### Connect your MCP tooling

Boost configuration is already versioned. Launch the Laravel Boost MCP server with:

```bash
php artisan boost:mcp
```

Editors that support MCP (Cursor, Zed, etc.) will auto-discover the `.cursor/mcp.json` profile and connect without extra tweaks. Review [the full playbook](docs/mcp-tooling.md) for available capabilities and day-to-day workflows.

---

## Boost MCP tooling

Boost ships with an MCP server so AI agents can inspect and operate on the
project using Laravel-aware helpers. The curated toolset focuses on the tasks we
perform most often while building analytics features and Filament panels.

### Capability map

| Workflow focus | MCP tool | Why it matters | Config source |
| --- | --- | --- | --- |
| Analytics pipelines & Filament scaffolding | `ListArtisanCommands` | Surfaces the custom Artisan commands that wrap ingestion jobs and Filament generators. | `config/boost.php` → `mcp.capabilities.artisan` |
| Laravel & Filament research | `SearchDocs` | Runs version-aware documentation searches before writing code. | `config/boost.php` → `mcp.capabilities.docs` |
| Experiment toggles | `GetConfig` | Reads feature flags that gate CTR experiments and dashboards. | `config/boost.php` → `mcp.capabilities.config` |
| Panel routing | `ListRoutes` | Lists the Filament panel routes that back office links depend on. | `config/boost.php` → `mcp.capabilities.routes` |
| Preview links | `GetAbsoluteUrl` | Generates absolute URLs for analytics dashboards and Filament resources. | `config/boost.php` → `mcp.capabilities.urls` |
| Schema exploration | `DatabaseSchema` | Provides read-only schema dumps to speed up analytics query design. | `config/boost.php` → `mcp.capabilities.schema` |

Write operations such as `DatabaseQuery` and `Tinker` stay disabled in
development by default so agents cannot mutate data unintentionally. Adjust the
`mcp.tools.exclude` list in `config/boost.php` if you need to opt in later.

### Editor integration

The repository’s `boost.json` already registers the MCP endpoint for editors and
agents that understand Boost configuration:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        }
    }
}
```

Run `php artisan boost:mcp` from your development shell (or let your editor do
it automatically) to boot the server. Boost only activates when `APP_ENV=local`
or `APP_DEBUG=true`, so production builds stay unaffected.

### Usage guidelines

- **Analytics work** – Start by calling `SearchDocs` with queries for funnels,
  queue metrics, or SSR insights. Follow up with `DatabaseSchema` to confirm
  table names before writing SQL or dashboard cards.
- **Filament changes** – Use `ListArtisanCommands` to locate the generator or
  helper command you need, then inspect panel routes with `ListRoutes` and
  create preview links with `GetAbsoluteUrl` for QA reviewers.
- **Configuration checks** – Before toggling experiments, fetch the current
  settings via `GetConfig` so AI agents understand the active recommendation
  strategy weights and feature flags.

These workflows keep agents grounded in the project’s real structure and
encourage documentation-first changes when shipping analytics or Filament
features.

---

## Log Map

The logging configuration exposes a curated set of channels that balance local
investigations with container-friendly streams. Every channel documented below
is mirrored in [`config/logging.php`](config/logging.php) via the `log_map`
section so the README stays in sync with the runtime configuration.

### Channels at a glance

| Channel | Writes to | Rotation / cleanup | Notes |
| --- | --- | --- | --- |
| `stack` | Delegates to the channels listed in `LOG_STACK` (defaults to `daily`). | Clean up by targeting the nested channels. Switch to `stack/json` when you need both file and stdout logs. | Default channel for the framework. |
| `stack/json` | `storage/logs/laravel.log` **and** `php://stdout` via nested channels. | File cleanup mirrors `single`; stdout retention depends on the process manager. | Handy when running in Docker/Kubernetes. |
| `single` | `storage/logs/laravel.log`. | Manually delete or truncate the file (`rm storage/logs/laravel.log` or `truncate -s 0 storage/logs/laravel.log`), or delegate to OS logrotate. | Great for quick local debugging. |
| `daily` | `storage/logs/laravel-YYYY-MM-DD.log`. | Laravel removes files older than `LOG_DAILY_DAYS` (14 by default). | Use when you need rotating history on disk. |
| `importers` | `storage/logs/importers-YYYY-MM-DD.log`. | Keeps 14 days of ingestion logs before pruning. | Keeps importer noise out of the main app logs. |
| `ingestion` | `storage/logs/ingestion-YYYY-MM-DD.log`. | Retention controlled by `INGESTION_LOG_DAYS` (defaults to 14). | Structured formatter for ingestion request tracing. |
| `json` | `php://stdout`. | Stream retention is managed by your container runtime or log collector. | Structured JSON payloads for observability pipelines. |
| `stderr` | `php://stderr`. | Managed by the host runtime. | Surface fatal issues to orchestrators. |
| `slack` | Remote Slack webhook defined by `LOG_SLACK_WEBHOOK_URL`. | Retention handled by Slack. | Sends critical alerts to on-call chat. |
| `papertrail` | Remote Papertrail endpoint configured via `PAPERTRAIL_URL` / `PAPERTRAIL_PORT`. | Retention handled by Papertrail. | Use for long-term hosted retention. |
| `syslog` | Host syslog daemon (`LOG_SYSLOG_FACILITY`). | Host syslog policy governs cleanup. | Forward logs to system-level collectors. |
| `errorlog` | `php.ini` `error_log` destination. | Managed by PHP / host. | Fallback to PHP error logging. |
| `null` | Discarded. | Not applicable. | Use when you need silence in tests. |
| `emergency` | `storage/logs/laravel.log`. | Same manual cleanup as `single`. | Framework fallback if a primary channel fails. |

### Enable daily rotation

1. Set `LOG_CHANNEL=daily` in `.env` (or override per environment in
   `config/logging.php`).
2. Optionally adjust the retention window with `LOG_DAILY_DAYS=30` (or any
   integer) to keep more historical files.
3. When stacking channels, update `LOG_STACK` (for example, `LOG_STACK="daily,json"`)
   so the `stack` driver emits both rotated files and container streams.

### Clean up rotated logs

- Delete a single file after capturing the evidence:
  `rm storage/logs/laravel.log` or
  `rm storage/logs/laravel-2025-01-01.log`.
- Truncate in place to keep permissions intact:
  `truncate -s 0 storage/logs/laravel.log`.
- For daily channels, rely on Laravel’s automatic pruning or configure system
  logrotate for fleet-wide policies.

### Permissions & SELinux debugging

Keeping rotated log files writable is essential for ingestion pipelines and the
daily log driver.

- **Directory permissions** — ensure the directory is writable by both the web
  server and CLI users: `chmod -R ug+rw storage/logs`. Grant execute permissions
  on parent directories (`chmod -R ug+x storage`) so rotation can create new
  files.
- **File ownership** — align the owner and group with your PHP-FPM user (for
  example, `www-data` on Debian/Ubuntu):
  `chown -R www-data:www-data storage/logs`. When deploy tools run as a
  different user, add them to the same group so `daily` rotation can replace log
  files without permission errors.
- **SELinux contexts** — on SELinux-enabled hosts, allow web processes to write
  into `storage/logs` with
  `sudo chcon -R -t httpd_sys_rw_content_t storage/logs`, then restore contexts
  after deployments via `sudo restorecon -Rv storage/logs`.

---

## Environment configuration

Configure the application in `.env` (examples below):

```ini
TMDB_API_KEY=your_tmdb_api_key          # Required for localisation and poster metadata
OMDB_API_KEY=your_omdb_api_key          # Used for fallback ratings and extended metadata
CACHE_STORE=redis                       # Use "file" or "database" if Redis is unavailable
SSR_METRICS=true                        # Enable server-side rendering performance metrics
SSR_METRICS_PATHS="/,/trends,/analytics/ctr" # CSV list of SSR routes to monitor (leading slashes normalised)
SSR_METRICS_STORAGE_PRIMARY_DISK=ssrmetrics  # Filesystem disk storing JSONL + aggregates when the DB is active
SSR_METRICS_STORAGE_PRIMARY_FILE=ssr-metrics.jsonl
SSR_METRICS_STORAGE_PRIMARY_AGGREGATE_FILE=ssr-metrics-summary.json
SSR_METRICS_STORAGE_FALLBACK_DISK=local      # Disk used when the database is unavailable
SSR_METRICS_STORAGE_FALLBACK_FILE=metrics/ssr.jsonl
SSR_METRICS_STORAGE_FALLBACK_RECOVERY_FILE=metrics/last.json
SSR_METRICS_WEIGHT_SPEED_INDEX=0.35          # Composite score weights; adjust when emphasising specific metrics
SSR_METRICS_WEIGHT_FCP=0.25
SSR_METRICS_WEIGHT_LCP=0.25
SSR_METRICS_WEIGHT_TTI=0.15
SSR_METRICS_THRESHOLD_PASSING=80             # Score thresholds powering the analytics “status” badges
SSR_METRICS_THRESHOLD_WARNING=65
SSR_METRICS_RETENTION_PRIMARY_DAYS=14        # Days to retain records on primary + fallback stores
SSR_METRICS_RETENTION_FALLBACK_DAYS=3
SSR_METRICS_RETENTION_AGGREGATE_DAYS=90
SSR_METRICS_PENALTY_BLOCKING_PER_SCRIPT=5    # Penalty deductions applied when computing SSR scores
SSR_METRICS_PENALTY_BLOCKING_MAX=30
SSR_METRICS_PENALTY_MISSING_LDJSON=10
SSR_METRICS_PENALTY_LOW_OG_MINIMUM=3
SSR_METRICS_PENALTY_LOW_OG_DEDUCTION=10
SSR_METRICS_PENALTY_OVERSIZED_THRESHOLD=921600
SSR_METRICS_PENALTY_OVERSIZED_DEDUCTION=20
SSR_METRICS_PENALTY_EXCESS_IMAGES_THRESHOLD=60
SSR_METRICS_PENALTY_EXCESS_IMAGES_DEDUCTION=10

# A/B recommendation strategy weights
REC_A_POP=0.55
REC_A_RECENT=0.20
REC_A_PREF=0.25
REC_B_POP=0.35
REC_B_RECENT=0.15
REC_B_PREF=0.50
```

Other useful flags:

- `QUEUE_CONNECTION=redis` when running Horizon for background jobs.
- `FILAMENT_PATH=/admin` to customise the Filament entry route.
- `APP_LOCALE` / `APP_FALLBACK_LOCALE` to tune default translations.

---

## Service providers & middleware

Enable the custom providers in `config/app.php`:

```php
App\Providers\AnalyticsPanelProvider::class,
App\Providers\HelpersServiceProvider::class,
```

Register the global middleware in `app/Http/Kernel.php`:

```php
\App\Http\Middleware\EnsureDeviceCookie::class,
\App\Http\Middleware\SsrMetricsMiddleware::class,
```

Routes for the overlay are already defined in `routes/web.php` and ship with the archive.

---

## Database & seeding

Run all migrations after configuring your database connection:

```bash
php artisan migrate
```

Optionally seed sample data (adjust `Database\Seeders\DatabaseSeeder` to your needs):

```bash
php artisan db:seed
```

This scaffold includes model factories so you can create fixtures for tests or demos quickly.

---

## Running the stack

- `php artisan serve` – local HTTP server.
- `php artisan schedule:work` – keeps scheduled metrics, imports, and clean-up tasks running.
- `php artisan horizon` – queue dashboard (when Redis & Horizon are installed).
- `php artisan optimize:clear` – clear caches after changing config or routes.

All Filament analytics widgets become available once you log into the admin panel with a user assigned the correct role/permissions.

---

## Testing & quality

```bash
php artisan test                             # PHPUnit feature/unit suite
./vendor/bin/pint --dirty                    # Fix coding style locally
./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=1G
```

Set up CI pipelines to run these commands for consistent quality gates.

---

## Release notes

- **2025-10-19** – Ultimate bundle (CTR SVG charts, z-test, funnels, TMDB i18n, RSS, Filament analytics, SSR metrics API, typed services, PHPStan lvl7, search filters, database indexes).

---

Need help? Review the source in `app/`, `routes/`, and `resources/` for concrete implementations, or open an issue describing your use case.
