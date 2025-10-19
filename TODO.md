# Delivery Backlog — Updated 2025-02-14

Keep this checklist current so onboarding engineers can quickly identify active gaps. Group owners are suggestions based on the
skills required to land the work.

## Frontend / Admin Experience
- [ ] **Filament polish (Analytics specialist)** — Refactor the CTR and SSR widgets in `app/Filament/Widgets` so shared colour
      scales and axis helpers live in a single trait, reducing duplication across `CtrLineWidget.php`, `CtrBarsWidget.php`, and
      `SsrStatsWidget.php`.
- [ ] **SSR surface telemetry (Full-stack SSR)** — Extend the SSR insights card within the Filament dashboard to surface
      middleware timings captured by `app/Http/Middleware/SsrMetricsMiddleware.php`, including percentiles and alert badges.
- [ ] **User segmentation toggles (Product-minded dev)** — Add audience filters to the analytics panel page in `app/Filament/Pages`
      so we can switch between global CTR data and cohort-specific ingestion metrics without reloading the dashboard.

## Analytics & Instrumentation
- [ ] **Pipeline attribution (Data engineer)** — Rework the ingestion summary emitted from `app/Services/TmdbI18n.php` to include
      source attribution fields before the data reaches the widgets, ensuring funnels in `FunnelWidget.php` can differentiate TMDB
      translations from OMDb fallbacks.
- [ ] **SSR drop classifier (Observability)** — Capture error fingerprints inside `app/Http/Middleware/SsrMetricsMiddleware.php`
      and persist them so `SsrDropWidget.php` can highlight repeat offenders and suggest remediation playbooks.
- [ ] **Clickstream reconciliation (Analytics engineer)** — Add a background job that reconciles daily CTR rollups against raw
      events, exposing drift warnings through `ZTestWidget.php` and `QueueStatsWidget.php`.

## Infrastructure & Data Ingestion
- [ ] **Backfill scheduler (Platform)** — Create a queued job pipeline to rerun stale imports when `app/Models/Movie.php`
      indicates missing localisation metadata, wiring it into `schedule:work` and notifying the Filament queue widgets.
- [ ] **TMDB/OMDb retry strategy (Backend)** — Implement exponential backoff and circuit-breaking within the ingestion services
      so we stop hammering third-party APIs during outages, with instrumentation hooks for the SSR middleware alerts.
- [ ] **Environment parity tests (QA lead)** — Add end-to-end feature coverage in `tests/Feature` that verifies SSR metrics,
      ingestion pipelines, and Filament dashboards stay consistent across `local`, `staging`, and `production` environment configs.

## Testing & Quality Gates
- [ ] **Middleware contract tests (QA engineer)** — Write targeted tests in `tests/Unit` for `SsrMetricsMiddleware.php` to ensure
      request attributes, sampling configuration, and downstream logging stay stable during refactors.
- [ ] **Analytics UI snapshots (Frontend QA)** — Capture Livewire-powered snapshots for the widgets under `app/Filament/Widgets`
      so UI regressions are caught before merges.
- [ ] **Data ingestion smoke suite (Ops)** — Build lightweight smoke tests for the TMDB/OMDb pathways using the existing
      service classes in `app/Services` to validate queue configuration, retries, and guardrail metrics.
