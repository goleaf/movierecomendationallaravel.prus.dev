# MovieRec Outstanding Work

## Data ingestion & localisation
- [ ] Scaffold TMDB and OMDb import commands under `app/Console/Commands/` and queueable jobs so the "ready-to-run" ingestion pipelines promised in `README.md` actually populate `movies` and related tables.
- [ ] Wire `App\Services\TmdbI18n` into the import flow and persist translated titles/plots (e.g. via new jobs queued from the import command) instead of leaving the service unused.

## Recommendation experiments
- [ ] Create a `config/recs.php` that hydrates A/B weights from the `REC_A_*` / `REC_B_*` environment variables and update `App\Services\RecAb` to read both variants (it currently only loads a static `recs.A` fallback).
- [ ] Implement a real preference component for the `pref` weight in `App\Services\RecAb::score()` (persist device or profile affinities and apply them instead of the current `0.0` placeholder).

## Analytics & SSR tooling
- [ ] Replace the silent `try/catch` in `App\Http\Controllers\AdminMetricsController` with logging/telemetry so Horizon connection issues surface in observability.
- [ ] Localise the hard-coded Russian advice strings returned by `App\Http\Controllers\SsrIssuesController` and expose them through Laravel's translation files.
- [ ] Ensure SSR metrics persistence exists (either the `ssr_metrics` table migration or the `metrics/ssr.jsonl` writer) so the `/admin/ssr/issues` endpoint has data to analyse.

## Views & frontend polish
- [ ] Complete the search filter UI in `resources/views/search/index.blade.php` to match the query parameters handled in `SearchPageController` (type, genre, year range selectors).
- [ ] Build CTR SVG previews in the admin panel views (`resources/views/admin/*`) so the `CtrSvgController` and `CtrSvgBarsController` outputs appear in-page rather than requiring manual SVG fetches.

## Database & seeding
- [ ] Expand `database/seeders/DatabaseSeeder.php` (and supporting factories) to create movies, genres, and CTR metric fixtures for demos/tests.
- [ ] Add migrations for any missing analytics tables referenced by the codebase (e.g. `ssr_metrics`, CTR aggregates) to avoid runtime 500s on fresh installs.

## Testing & quality gates
- [ ] Add feature tests that cover home/search/trends/movie detail routes (replace the default `tests/Feature/ExampleTest.php`).
- [ ] Introduce HTTP tests for the admin analytics endpoints (`/admin/ctr`, `/admin/metrics`, `/admin/ssr/issues`) asserting data/JSON structure.
- [ ] Configure CI to run `php artisan test`, `./vendor/bin/pint`, and PHPStan level 7 as described in the docs.

## Deployment & documentation
- [ ] Document the ingestion pipeline, A/B tuning, and analytics dashboards in `/docs` or `README.md` follow-ups, filling the gaps noted in `.cursor/IMPLEMENTATION_STATUS_REPORT.md` (Priority 8: Documentation).
- [ ] Set up deployment automation (CI/CD workflows plus production env checklist) to address `.cursor/IMPLEMENTATION_STATUS_REPORT.md` Priority 9.
