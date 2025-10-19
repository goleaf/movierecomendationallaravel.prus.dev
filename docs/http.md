# HTTP Client Standards

This guide standardises how we fan out HTTP calls so that background ingestion, SSR instrumentation, and synchronous controllers stay predictable. Always start from this decision tree when touching the client layer.

1. **A single downstream call** &mdash; start from `Policy::external()` and, if necessary, customise headers or options on the returned `PendingRequest`.
2. **Two to five calls with static fan-out** (for example, fetching a couple of poster variants alongside the primary response) &mdash; reach for [`Http::pool`](https://laravel.com/docs/http-client#concurrent-requests).
3. **Six or more calls or a dynamic collection** (bulk TMDB hydration, SSR metric replays, sync jobs) &mdash; use [`Http::batch`](https://laravel.com/docs/http-client#batched-requests).

## Baseline pending request policy

Use `Policy::external()` for any single-request workflow or as the seed request passed into pools or batches. It applies our default safety rails:

- **Timeouts.** Every outbound call uses a **15 second** response timeout and **5 second** connect timeout (see `Policy::external()`); override them only when the downstream SLA requires a stricter value.
- **Retries.** We retry **3** times with a **200 ms** delay and `throw: false`, so callers can inspect the final response instead of catching exceptions by default.
- **Headers.** The helper sets `Accept: application/json` and a deterministic `User-Agent` based on `config('app.name')`. Use `->replaceHeaders()` when the downstream service expects different values (for example, binary image proxies).
- **Tracing.** When invoking external services from HTTP requests, forward the context that `AttachRequestContext` adds to the request object. At minimum propagate `X-Request-ID`, and include `X-Device-ID` / `X-AB-Variant` when available:
  ```php
  Policy::external()
      ->withHeaders(array_filter([
          'X-Request-ID' => request()->attributes->get('request_id'),
          'X-Device-ID' => request()->attributes->get('device_id'),
          'X-AB-Variant' => request()->attributes->get('ab_variant'),
      ]));
  ```
  This keeps downstream logs correlated with our ingestion and queue traces.

## `Http::pool`

`Http::pool` keeps latency low for a small, known set of requests that must resolve before we can return control to the caller.

- **Scope.** Only use in controllers, Livewire actions, or synchronous services where the call graph is short-lived.
- **Concurrency.** Cap the fan-out to **5 concurrent connections** by passing `5` as the concurrency argument to `Http::pool`. This keeps us inside TMDB's public limits while still masking round-trip latency.
- **Timeouts.** Call `Policy::external()` (or an equivalent pending request) before invoking the pool so every branch inherits the standard 15s/5s guardrails. When a service demands a tighter limit, set it on the shared request before handing it to the pool.
- **Retries.** Apply retry logic on the shared pending request (for example, `Policy::external()->retry(2, 150)`) so that each pooled call behaves the same.
- **Responses.** Use named requests (`$pool->as('images')->get(...)`) so downstream code can address results deterministically and we can assert on them in tests.
- **Tracing.** Pass the same contextual headers described above so each request in the pool is traceable.

## `Http::batch`

`Http::batch` is designed for background or bulk operations where we queue many similar requests and need back-pressure.

- **Scope.** Prefer `Http::batch` from queued jobs, scheduled commands, or long-running ingestion services. It is the default for TMDB translation refreshes, SSR replay loops, and any future data backfills.
- **Concurrency.** Set batch concurrency to **10** via the batch builder's `concurrency` helper unless the target service has a stricter limit. This strikes the balance between throughput and TMDB's recommended 20 req/s cap.
- **Chunk sizing.** Feed `Http::batch` with generators or chunked collections instead of preloading arrays, so we avoid loading large payloads into memory.
- **Retries and timeouts.** Chain retry and timeout rules on the shared pending request before calling `batch` (for example, `Policy::external()->timeout(8)->retry(3, 200)`) so each task respects the same behaviour.
- **Tracing and logging.** Register `then` callbacks on the batch to log slow or failed requests. Forward the `X-Request-ID` header when the batch originates from an HTTP request, and emit summaries to `StoreSsrMetric` or dedicated logs when running from the queue.

## Testing and verification

- Feature and unit tests that mock HTTP fan-out **must** assert that the expected number of requests were sent via `Http::assertSentCount()` or the batch-specific helpers.
- CI runs the documentation linter; keep examples valid PHP so the linter can parse them.
- When reviewing client code, ensure the chosen approach aligns with the decision tree above and honours the concurrency caps.

## Current client audit

| Component | Usage | Timeouts / Retries | Concurrency & Rate Limits | Tracing Notes |
| --- | --- | --- | --- | --- |
| `App\Support\Http\Policy` | Base helper for all single-call clients (`Policy::external()`). | 15s timeout, 5s connect timeout, 3 retries @ 200ms. | Sequential; no concurrency management. | Should be combined with contextual headers as shown above. |
| `App\Http\Controllers\Api\ArtworkProxyController` | Streams third-party artwork through `Policy::external()` with overridden `Accept` header. | Inherits Policy defaults. | Single call. | Currently does not forward request context headers; update before adding new behaviour. |
| `App\Http\Controllers\PosterProxyController` | Proxies poster images with streaming enabled via `Policy::external()`. | Inherits Policy defaults; wraps failures with fallback assets. | Single call. | Same as above—propagate context when modifying. |
| `App\Services\TmdbI18n` | Sequential TMDB lookups via `Policy::external()`; iterates locales without pooling. | Policy defaults per call. | No parallelism yet; consider `Http::pool` if the locale list grows. | Needs manual header propagation when invoked from HTTP entrypoints. |
| `App\Services\MovieApis\RateLimitedClient` | Shared TMDB/OMDb client built on `HttpFactory`. Applies rate limiting, retries, and backoff per config. | Timeout + retry counts sourced from `config('services.tmdb|omdb')` (e.g. 10s timeout, 2/1 retries). | Uses Laravel rate limiter (35 req / 10s for TMDB, 5 req / 1s for OMDb). Sequential execution inside the limiter. | Add contextual headers via `$options['headers']` when the caller has them. |
| `App\Services\MovieApis\TmdbClient` | Delegates to `RateLimitedClient`; no additional concurrency. | Inherits TMDB config. | Rate limiter enforces allowance; no batching. | Ensure higher-level callers pass trace headers through `$options`. |
| `App\Services\MovieApis\OmdbClient` | Delegates to `RateLimitedClient` with default query params. | Inherits OMDb config. | Rate limiter enforces allowance; no batching. | Same propagation guidance as TMDB client. |

There are currently **no production usages** of `Http::pool` or `Http::batch`. When introducing them, follow the standards above so retries, timeouts, and tracing stay consistent across services.
