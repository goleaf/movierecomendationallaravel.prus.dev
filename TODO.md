# TODO – MovieRec Overlay

Maintain this backlog so the team can quickly align on what still needs to happen for the overlay to feel production ready. Use the checkboxes to track progress during work sessions and prune items once shipped.

## Analytics
- [ ] Align the Filament analytics blades in `resources/views/filament/analytics/*` with the parity checklist so CTR, funnel, and z-test widgets share copy, accessible labelling, and data contracts. (Owner: Maya — Data Insights)
- [ ] Extend Filament dashboards with uptime notifications for the SSR metrics API responses (currently only CTR trends are monitored). (Owner: Ben — Product Analytics)
- [ ] Produce runbooks for the analytics widgets (CTR lines/bars, funnels, z-test) so on-call engineers know expected behaviour and alert thresholds. (Owner: Liam — Ops Enablement)

## Observability
- [ ] Deepen instrumentation inside `app/Http/Middleware/SsrMetricsMiddleware.php` to capture hydration latency, queue spillover, and Horizon tag correlation. (Owner: Priya — Observability)
- [ ] Backfill docs covering SSR metrics sampling knobs and alert routes so instrumentation owners can triage noise quickly. (Owner: Ben — Product Analytics)
- [ ] Add smoke tests for the TMDB/OMDb ingestion commands in `app/Console/Commands` to guarantee queue wiring survives future refactors. (Owner: Taylor — Backend Guild)

## Infrastructure
- [ ] Install Laravel Boost locally (`composer require laravel/boost --dev` + `php artisan boost:install`) and commit the generated scaffolding. (Owner: Platform)
- [ ] Audit the Boost installation output and document any non-default files inside `.cursor/` so editors can attach to the MCP server without guesswork. (Owner: Platform)
- [ ] Verify CI covers `vendor/bin/pint`, `php artisan test`, and `phpstan` to match the quality gates described in the README. (Owner: Platform)
- [ ] Create feature tests that exercise both recommendation strategies exposed in `routes/web.php` and assert the correct environment flags are honoured. (Owner: Taylor — Backend Guild)
- [ ] Expand `README.md` with a quickstart focused on Horizon/Redis since those steps are only hinted at today. (Owner: Docs — Nina)
- [ ] Publish a `.cursor/rules.json` primer describing preferred generators/prompts for controllers, Livewire components, and Filament resources. (Owner: Docs — Nina)
