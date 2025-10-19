# TODO – MovieRec Overlay

Maintain this backlog so the team can quickly align on what still needs to happen for the overlay to feel production ready. Use the checkboxes to track progress during work sessions and prune items once shipped.

## Platform setup
- [ ] Complete the Laravel Boost installation and documentation:
  - [ ] `composer require laravel/boost --dev`
  - [ ] `php artisan boost:install`
  - [ ] Verify installer output and the generated files that land in the repo
  - [ ] Document any non-default `.cursor/` entries and MCP server registration steps so editors can attach to the Boost server without guesswork
- [ ] Verify CI covers `vendor/bin/pint`, `php artisan test`, and `phpstan` to match the quality gates described in the README.
- [ ] Plan follow-up hardening once Boost is stable (Blade/routes audit, Tailwind migration, regression tests).

## Feature hardening
- [ ] Add smoke tests for the TMDB/OMDb ingestion commands in `app/Console/Commands` to guarantee queue wiring survives future refactors.
- [ ] Create feature tests that exercise both recommendation strategies exposed in `routes/web.php` and assert the correct environment flags are honoured.
- [ ] Extend Filament dashboards with uptime notifications for the SSR metrics API responses (currently only CTR trends are monitored).

## Documentation & onboarding
- [ ] Expand `README.md` with a quickstart focused on Horizon/Redis since those steps are only hinted at today.
- [ ] Publish a `.cursor/rules.json` primer describing preferred generators/prompts for controllers, Livewire components, and Filament resources.
- [ ] Produce runbooks for the analytics widgets (CTR lines/bars, funnels, z-test) so on-call engineers know expected behaviour and alert thresholds.
