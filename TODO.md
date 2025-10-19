# TODO – MovieRec Overlay

Maintain this backlog so the team can quickly align on what still needs to happen for the overlay to feel production ready. Use the checkboxes to track progress during work sessions and prune items once shipped.

## Platform setup
- [ ] Install Laravel Boost locally (`composer require laravel/boost --dev` + `php artisan boost:install`) and commit the generated scaffolding.
- [ ] Audit the Boost installation output and document any non-default files inside `.cursor/` so editors can attach to the MCP server without guesswork.
- [ ] Verify CI covers `vendor/bin/pint`, `php artisan test`, and `phpstan` to match the quality gates described in the README.

## Feature hardening
- [ ] Add smoke tests for the TMDB/OMDb ingestion commands in `app/Console/Commands` to guarantee queue wiring survives future refactors.
- [ ] Create feature tests that exercise both recommendation strategies exposed in `routes/web.php` and assert the correct environment flags are honoured.
- [ ] Extend Filament dashboards with uptime notifications for the SSR metrics API responses (currently only CTR trends are monitored).

## Documentation & onboarding
- [ ] Expand `README.md` with a quickstart focused on Horizon/Redis since those steps are only hinted at today.
- [ ] Publish a `.cursor/rules.json` primer describing preferred generators/prompts for controllers, Livewire components, and Filament resources.
- [ ] Produce runbooks for the analytics widgets (CTR lines/bars, funnels, z-test) so on-call engineers know expected behaviour and alert thresholds.
