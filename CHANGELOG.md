# Changelog (All-in-One)

## 2025-10-21

### Dependencies
- Locked `laravel/framework` to `~12.34.0` so our app only receives patch updates on the 12.34 line that Filament 3.3.43 and Larastan 3.7.2 already ship against; `composer why-not laravel/framework 12.35.0` fails because of this tilde constraint, keeping us away from minors that may tighten Symfony requirements before Filament catches up.
- Narrowed `filament/filament` to `~3.3.43` to remain on the Laravel 12–certified build tested with Livewire 3.6; a hypothetical `composer why-not filament/filament 3.4.0` now surfaces the tilde guard, ensuring we avoid minors that could demand Laravel 13 or Livewire 4 upgrades.
- Required `predis/predis` as `~3.2.0`, which keeps Redis integration locked to the v3.2 API Laravel 12 uses; attempting `composer why-not predis/predis 3.3.0` points back to our constraint so we do not unknowingly absorb protocol changes.
- Set `symfony/uid` to `~7.3.1` to match the Symfony 7.3 components bundled with Laravel 12; `composer why-not symfony/uid 7.4.0` confirms we cannot jump to a minor that might require PHP 8.4 before Laravel raises its floor.
- Pinned the static analysis toolchain to `phpstan/phpstan` `~2.1.31` and `nunomaduro/larastan` `~3.7.2`, satisfying Larastan’s `^2.1.28` floor while blocking the next PHPStan minor that historically introduced rule changes needing config updates.
- Locked `laravel/pint` to `~1.25.1` so formatting remains consistent with the Laravel 12 preset—`composer why-not laravel/pint 1.26.0` reinforces that we only accept 1.25.x patches which avoid style drift mid-cycle.

