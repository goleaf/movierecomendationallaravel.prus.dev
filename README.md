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

## Environment configuration

Configure the application in `.env` (examples below):

```ini
TMDB_API_KEY=your_tmdb_api_key          # Required for localisation and poster metadata
OMDB_API_KEY=your_omdb_api_key          # Used for fallback ratings and extended metadata
CACHE_STORE=redis                       # Use "file" or "database" if Redis is unavailable
SSR_METRICS=true                        # Enable server-side rendering performance metrics
SSR_METRICS_STORAGE=database            # Primary storage driver (database, jsonl, or both)
SSR_METRICS_RETENTION_DAYS=30           # Days to retain SSR samples before pruning
SSR_METRICS_JSONL_DISK=local            # Filesystem disk for JSONL fallback snapshots
SSR_METRICS_JSONL_PATH=metrics/ssr.jsonl # Relative path used when writing JSONL snapshots
SSR_METRICS_PENALTY_TIMEOUT=25          # Score penalty applied when SSR responses timeout
SSR_METRICS_PENALTY_ERROR=50            # Score penalty applied when SSR responses error
SSR_METRICS_PENALTY_SLOW_FIRST_BYTE=10  # Score penalty applied for slow TTFB
SSR_METRICS_PENALTY_MISSING_JSON_LD=5   # Score penalty applied when JSON-LD is missing
SSR_METRICS_PENALTY_MISSING_OPEN_GRAPH=5 # Score penalty applied when Open Graph tags are missing
SSR_METRICS_PENALTY_BLOCKING_SCRIPTS=3  # Score penalty applied for blocking scripts
SSR_METRICS_PENALTY_HEAVY_HTML=2        # Score penalty applied when HTML payloads are heavy

# A/B recommendation strategy weights
REC_A_POP=0.55
REC_A_RECENT=0.20
REC_A_PREF=0.25
REC_B_POP=0.35
REC_B_RECENT=0.15
REC_B_PREF=0.50
```

The SSR metrics variables let operators control persistence and scoring defaults without touching code:
- `SSR_METRICS_STORAGE` selects the primary driver (`database`, `jsonl`, or `both`) used by the `StoreSsrMetric` job.
- `SSR_METRICS_RETENTION_DAYS` defines how many days of samples to keep before pruning maintenance jobs run.
- `SSR_METRICS_JSONL_DISK` and `SSR_METRICS_JSONL_PATH` configure the fallback JSONL snapshots used when the database is unavailable.
- The `SSR_METRICS_PENALTY_*` variables tune how heavily issues such as timeouts, missing metadata, or heavy HTML payloads reduce the computed SSR score.

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
