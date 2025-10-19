# MovieRec — All-in-One Ultimate (Laravel overlay)

MovieRec bundles every feature shipped across the previous MovieRec releases into a single Laravel overlay. The project delivers ingestion pipelines, A/B-tested movie recommendations, SVG CTR visualisations, TMDB-powered localisation, RSS feeds, on-the-fly SSR metrics, Filament dashboards, optimised database indexes, automated tests, and strict PHPStan level 7 typing.

👉 Track outstanding integration tasks in the project [TODO list](TODO.md).

---

## Project trackers

- [TODO backlog](TODO.md)
- [Works log](WORKS.md)

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

---

## Environment configuration

Configure the application in `.env` (examples below):

```ini
TMDB_API_KEY=your_tmdb_api_key          # Required for localisation and poster metadata
OMDB_API_KEY=your_omdb_api_key          # Used for fallback ratings and extended metadata
CACHE_STORE=redis                       # Use "file" or "database" if Redis is unavailable
SSR_METRICS=true                        # Enable server-side rendering performance metrics

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
