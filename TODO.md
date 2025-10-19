# MovieRec Outstanding Work

## Data ingestion & storage
- [ ] Create migrations for the movie and analytics tables (`movies`, `rec_ab_logs`, `rec_clicks`, `device_history`, `ssr_metrics`) that the controllers and Filament dashboards query, then backfill them with proper indexes.
- [ ] Build queueable TMDB/OMDb import commands to populate movies, translations, and raw metadata as promised in the README "Data ingestion & enrichment" section.
- [ ] Extend `DatabaseSeeder` (and supporting factories) to seed representative movies, click logs, and device histories for local development.

## Recommendation engine
- [ ] Expose configurable weights for variants A/B (ENV `REC_A_*` / `REC_B_*`) and update `App\\Services\\RecAb` to honor the preference scores instead of the current hard-coded zero.
- [ ] Persist per-device view & click history so `HomeController` fallbacks, funnels, and CTR analytics receive real data.
- [ ] Cover recommendation variants with feature tests to lock in scoring, variant assignment, and fallback behaviour.

## Analytics, SSR, and Filament dashboards
- [ ] Implement the jobs/listeners that write impressions, clicks, and device events into `rec_ab_logs`, `rec_clicks`, and `device_history` so `CtrController`, `TrendsController`, and SVG endpoints can render meaningful charts.
- [ ] Ensure `SsrMetricsMiddleware` stores metrics in the database (or storage fallback) and add migrations plus pruning to support the Filament SSR widgets.
- [ ] Add the missing `public/img/og_default.jpg` (or update the layout) so the Open Graph image reference in `resources/views/layouts/app.blade.php` stops 404ing.

## Documentation & developer experience
- [ ] Consolidate the `.cursor` Filament-only rules into a checked-in developer guide and reference it from contributor docs.
- [ ] Document the ingestion, metrics, and dashboard workflows in the README (commands, queues, Horizon) so operators can run the stack end-to-end.
- [ ] Localise Blade views by moving the hard-coded Russian strings into Laravel translation files to match the multilingual requirement mentioned in `.cursor` guidance.
