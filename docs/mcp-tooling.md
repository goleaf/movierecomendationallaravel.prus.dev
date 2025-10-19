# MCP & Boost Tooling Playbook

This guide captures the MovieRec team's standard Boost / MCP workflows so every contributor can connect to the Laravel Boost MCP server and rely on the same automation helpers.

## Supported Boost capabilities

The Laravel Boost MCP server exposes the following tools. Prefer these helpers before reaching for ad-hoc scripts so we keep our workflow consistent:

| Capability | What it does | Typical use cases |
| --- | --- | --- |
| `search-docs` | Searches version-matched documentation for Laravel, Livewire, Filament, Tailwind, Pest, and other first-party tooling. | Confirm APIs (e.g. Livewire lifecycle hooks) before coding, link references in PRs, capture upgrade notes. |
| `list-artisan-commands` | Lists available Artisan commands with parameters. | Discover command signatures, verify scheduled jobs before running them in automation. |
| `run-artisan` wrappers | Executes Artisan commands through Boost with environment awareness. | Run migrations, queues, and diagnostics without leaving the MCP session. |
| `tinker` | Executes arbitrary PHP against the application context. | Inspect Eloquent state, prototype logic, debug failing pipelines. |
| `database-query` | Performs read-only database queries. | Validate analytics datasets, confirm migration impacts, power-user reporting. |
| `browser-logs` | Streams recent browser console output collected by Boost. | Debug Livewire/Alpine issues surfaced in the browser. |
| `get-absolute-url` | Generates URLs to local services with the correct host/port. | Share Filament dashboard links or API endpoints in issues/PRs. |

> **Tip:** When in doubt start with `search-docs` to ground a change in the framework's recommended approach, then switch to `tinker` or `run-artisan` to validate the implementation.

## Connection prerequisites

Boost configuration lives in version control so the MCP server works out-of-the-box:

- `.cursor/mcp.json` points Cursor (and other MCP-aware editors) at the `laravel-boost` profile and shells out to `php artisan boost:mcp`.
- `boost.json` advertises the available MCP agents (`codex`, `cursor`) to Boost-aware tooling.

As long as contributors have PHP 8.3+ and Composer dependencies installed they can launch the MCP server with:

```bash
php artisan boost:mcp
```

The process binds to the default MCP port and terminates with <kbd>Ctrl</kbd>+<kbd>C</kbd> when you're done.

## Day-to-day usage patterns

### Framework & feature development

1. **Research:** Run `search-docs` with broad keywords (e.g. `["middleware", "rate limit"]`) to review relevant guidance before touching code.
2. **Scaffold:** Use `list-artisan-commands` to confirm generator signatures, then execute commands via `run-artisan` (for example, `php artisan make:livewire Analytics/Overview`).
3. **Verify:** Execute targeted PHPUnit runs or Livewire interactions through the MCP shell using the same environment as the app (`run-artisan test --filter=...`).
4. **Inspect:** When investigating Livewire state issues, pivot to `tinker` or `browser-logs` for server/client introspection without leaving the MCP session.

### Analytics & observability

1. **Validate data:** Query ingestion tables with `database-query` to confirm the latest TMDB/OMDb sync status or CTR rollups.
2. **Debug SSR metrics:** Combine `tinker` to fetch recent metrics aggregates with `browser-logs` to trace any client-side visualisation errors.
3. **Share context:** Generate sharable links to dashboards or APIs using `get-absolute-url` so URLs respect the local dev host mapping.

### Common troubleshooting flows

- **Unknown Artisan command?** Call `list-artisan-commands` to double-check availability, then run it via the MCP connection.
- **Need quick code validation?** Fire up `tinker` for inline checks instead of writing throwaway routes or jobs.
- **Docs mismatch?** Re-run `search-docs` with refined terms; results are filtered to the exact package versions used in this project.

## Contributing expectations

- Keep `.cursor/` and `boost.json` committed so new teammates inherit working defaults.
- When adding new Laravel packages or Livewire components, record the relevant MCP workflows (search terms, artisan commands) in this document.
- If Boost introduces new capabilities, document them here before relying on them in reviews or onboarding material.

Maintaining this shared playbook lets the team navigate MovieRec's analytics-heavy stack efficiently while keeping Boost automation at the center of our workflow.
