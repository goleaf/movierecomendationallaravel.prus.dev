# MovieRec — All-in-One Ultimate (Laravel overlay)

Полный комплект кода из всех предыдущих пакетов: импорт, рекомендации (A/B), CTR-графики (SVG), Filament Analytics,
i18n (TMDB), RSS, SSR-метрики (on-the-fly), админ-виджеты, индексы БД, тесты и строгая типизация (PHPStan lvl 7).

## Быстрый старт (на чистом Laravel 11)
```bash
composer create-project laravel/laravel movierec
cd movierec

# Распакуй этот архив поверх проекта
unzip movierec_all_in_one_ultimate.zip -d .

composer require filament/filament:"^3.0" -W
composer require predis/predis symfony/uid

# Dev-инструменты (опционально)
composer require --dev laravel/pint nunomaduro/larastan:^2.9 phpstan/phpstan:^1.11

php artisan migrate

# (Опционально) Horizon + Redis
composer require laravel/horizon
php artisan horizon:install
```

### ENV
```
TMDB_API_KEY=...
OMDB_API_KEY=...
CACHE_STORE=redis
SSR_METRICS=true
REC_A_POP=0.55
REC_A_RECENT=0.20
REC_A_PREF=0.25
REC_B_POP=0.35
REC_B_RECENT=0.15
REC_B_PREF=0.50
```

### Провайдеры и middleware
В `config/app.php` добавь провайдеры:
```php
App\Providers\AnalyticsPanelProvider::class,
App\Providers\HelpersServiceProvider::class,
```
В `app/Http/Kernel.php` в глобальные middleware добавь:
```php
\App\Http\Middleware\EnsureDeviceCookie::class,
\App\Http\Middleware\SsrMetricsMiddleware::class,
```

### Маршруты
Уже в `routes/web.php` (см. дальше).

### Запуск тестов
```
php artisan test
```

### Статический анализ
```
./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=1G
```
Дата: 2025-10-19
