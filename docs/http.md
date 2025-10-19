# HTTP Client Standards

This guide standardises how we fan out HTTP calls so that background ingestion, SSR instrumentation, and synchronous controllers stay predictable. Always start from this decision tree when touching the client layer.

1. **A single downstream call** &mdash; stick with a normal `Http::` request on the existing `PendingRequest` instance.
2. **Two to five calls with static fan-out** (for example, fetching a couple of poster variants alongside the primary response) &mdash; reach for [`Http::pool`](https://laravel.com/docs/http-client#concurrent-requests).
3. **Six or more calls or a dynamic collection** (bulk TMDB hydration, SSR metric replays, sync jobs) &mdash; use [`Http::batch`](https://laravel.com/docs/http-client#batched-requests).

## `Http::pool`

`Http::pool` keeps latency low for a small, known set of requests that must resolve before we can return control to the caller.

- **Scope.** Only use in controllers, Livewire actions, or synchronous services where the call graph is short-lived.
- **Concurrency.** Cap the fan-out to **5 concurrent connections** by passing `5` as the concurrency argument to `Http::pool`. This keeps us inside TMDB's public limits while still masking round-trip latency.
- **Timeouts.** Honour the default service timeout (10 seconds unless a method overrides it). When composing custom pools, call `timeout` on the pending request before invoking the pool so every branch shares the same guardrail.
- **Responses.** Use named requests (`$pool->as('images')->get(...)`) so downstream code can address results deterministically and we can assert on them in tests.

## `Http::batch`

`Http::batch` is designed for background or bulk operations where we queue many similar requests and need back-pressure.

- **Scope.** Prefer `Http::batch` from queued jobs, scheduled commands, or long-running ingestion services. It is the default for TMDB translation refreshes, SSR replay loops, and any future data backfills.
- **Concurrency.** Set batch concurrency to **10** via the batch builder's `concurrency` helper unless the target service has a stricter limit. This strikes the balance between throughput and TMDB's recommended 20 req/s cap.
- **Chunk sizing.** Feed `Http::batch` with generators or chunked collections instead of preloading arrays, so we avoid loading large payloads into memory.
- **Retries.** Chain retry logic on the shared pending request before invoking `batch` (for example, `Http::retry(3, 200)`), ensuring every individual request honours the same backoff strategy.
- **Observability.** Register `then` callbacks on the batch to log slow or failed requests. The batch object exposes `successful()` and `failed()` helpers that metrics jobs can forward to `StoreSsrMetric`.

## Testing and verification

- Feature and unit tests that mock HTTP fan-out **must** assert that the expected number of requests were sent via `Http::assertSentCount()` or the batch-specific helpers.
- CI runs the documentation linter; keep examples valid PHP so the linter can parse them.
- When reviewing client code, ensure the chosen approach aligns with the decision tree above and honours the concurrency caps.

Following these rules keeps our client layer consistent, predictable, and respectful of third-party rate limits while still hitting our ingestion SLAs.
