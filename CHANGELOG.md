# Changelog (All-in-One)

## 2025-10-19

### Dependencies
- Locked `laravel/framework` to `^12.34.0` so upgrades stay within the 12.x line that Filament 3.3 and the rest of the stack already validate against; `composer why-not laravel/framework 13.0` would fail because Laravel 13 is unreleased and would break the Illuminate contracts required by Filament.
- Narrowed `filament/filament` to `^3.3.43`, which keeps us on the 3.3 feature release that supports Laravel 12 while allowing only forward-compatible 3.x bugfixes—`composer why-not filament/filament 4.0` would point to missing Livewire 4 support.
- Required `predis/predis` as `^3.2.0`, matching the Redis client shipped with Laravel 12 and ensuring we avoid the 2.x API as well as any future 4.x BC breaks that `composer why-not predis/predis 4.0` would highlight.
- Set `symfony/uid` to `^7.3.1` so we stay aligned with the Symfony 7.3 components bundled with Laravel; newer 8.x releases would fail the UID polyfill contract.
- Bumped the static analysis toolchain to `phpstan/phpstan:^2.1.29` and `nunomaduro/larastan:^3.7.2` because Larastan 3.7 requires PHPStan ≥2.1.28; this mirrors the upstream Laravel 12 dev requirements while keeping us below 3.0.
- Pinned `laravel/pint` to `^1.25.1` so formatting runs use the same binary the framework builds against without jumping to a future major with incompatible rules.

