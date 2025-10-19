# Feature Catalog

This catalog highlights the major product surfaces in MovieRec so newcomers can orient themselves quickly. Each section references the controllers, Filament widgets, and Blade templates that define the experience, along with the core data sets these features depend on.

## Public UI

### Home recommendations & highlights
- **Entry point:** [`HomeController`](../app/Http/Controllers/HomeController.php) renders `resources/views/home/index.blade.php` with personalised recommendations sourced from the `Recommender` service. When the tailored list is empty it falls back to IMDb sorted titles. Trending panels reuse the same view and hydrate from recent click counts.
- **Data dependencies:** Requires `rec_clicks` for seven-day trending snapshots and `movies` metadata for recommendation fallbacks.

### Search & filtering
- **Entry point:** [`SearchPageController`](../app/Http/Controllers/SearchPageController.php) powers the full-page search interface rendered by `resources/views/search/index.blade.php`.
- **Features:** Supports keyword, title ID, type, genre, and year range filters. Responses can be rendered as HTML or JSON through the same controller, making the view double as a progressive enhancement layer.
- **Data dependencies:** Relies on the `movies` table, with optional enrichment coming from translated genre metadata.

### Trends explorer
- **Entry point:** [`TrendsController`](../app/Http/Controllers/TrendsController.php) renders `resources/views/trends/index.blade.php` and can also emit JSON for dashboards.
- **Features:** Aggregates the most-clicked items over a configurable window, while falling back to IMDb popularity when no click data exists. The Blade view shows click totals alongside rating and vote context for each tile.
- **Data dependencies:** Pulls rolling metrics from `rec_clicks` and joins against `movies` for artwork and metadata.

## API Surfaces

### Search API
- **Endpoint:** [`Api\SearchController`](../app/Http/Controllers/Api/SearchController.php) mirrors the public search filters for programmatic consumers.
- **Response shape:** Returns `SearchResultCollection`, keeping the payload consistent with the HTML experience while enforcing sensible limits.
- **Data dependencies:** Same `movies` catalogue used by the public UI, ensuring API and web stay in sync.

### SSR issue feed
- **Endpoint:** [`SsrIssuesController`](../app/Http/Controllers/SsrIssuesController.php) exposes recent rendering concerns as JSON.
- **Features:** Consolidates the `ssr_metrics` table (or the `metrics/ssr.jsonl` fallback) into actionable hints covering blocking scripts, structured data, and OG tags.
- **Data dependencies:** Reads from `ssr_metrics` when available, falling back to filesystem logs for environments without the table.

## Internal Analytics & Operations

### Filament analytics panel
- **Widgets:** Located under `app/Filament/Widgets/`, including CTR charts (`CtrLineWidget`, `CtrBarsWidget`), funnels (`FunnelWidget`), A/B experiment reporting (`ZTestWidget`), queue health (`QueueStatsWidget`), and SSR insights (`SsrStatsWidget`, `SsrDropWidget`).
- **Data dependencies:** CTR visuals and funnels require both `rec_ab_logs` impressions and `rec_clicks` outcomes. Queue metrics pull from `jobs`, `failed_jobs`, and Redis Horizon keys, while SSR widgets reuse `ssr_metrics` or storage snapshots.

### Admin metrics snapshot
- **Entry point:** [`AdminMetricsController`](../app/Http/Controllers/AdminMetricsController.php) renders `resources/views/admin/metrics.blade.php`.
- **Features:** Surfaces queue depth, failed job counts, batch totals, and any available Horizon workload metadata for quick triage.
- **Data dependencies:** Queries `jobs`, `failed_jobs`, `job_batches`, and Horizon's Redis hashes/sets.

### Data ingestion status
- **Pipelines:** Ingestion workers persist status rows via the `IngestionRun` model, exposing TMDB/OMDb sync history for dashboards and alerts.
- **Data dependencies:** Reads from the `ingestion_runs` table, which stores `source`, `external_id`, `date_key`, the most recent ETag/`last_modified_at`, and optional JSON `meta` about the run payload.

### Server-side rendering telemetry
- **Middleware & views:** SSR scores funnel into Filament widgets and the JSON issues feed described above. The `SsrMetricsMiddleware` (registered via `app/Http/Kernel.php`) writes to `ssr_metrics`, enabling comparisons against drop detectors like `SsrDropWidget`.
- **Operational loops:** Combined insights help operations teams tune markup weight, script deferral, and structured data adoption.
