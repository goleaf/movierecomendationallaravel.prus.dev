#!/usr/bin/env bash

# Ensure Bash is used even if invoked via `sh`.
if [ -z "${BASH_VERSION:-}" ]; then
    exec /usr/bin/env bash "$0" "$@"
fi

set -euo pipefail
set -o errtrace

on_error() {
    local exit_code=$?
    local line_no=${BASH_LINENO[0]:-unknown}
    printf '\n\033[1;31m✖ Start script failed\033[0m (exit code %s at line %s).\n' "$exit_code" "$line_no" >&2
    printf 'Inspect the log above for details.\n' >&2
}

trap on_error ERR

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

banner() {
    printf '\033[1;35m'
    cat <<'BANNER'
 __  __             _               _
|  \/  | __ _ _ __ | | _____ _   _ | | ___   ___  _ __
| |\/| |/ _` | '_ \| |/ / _ \ | | || |/ _ \ / _ \| '__|
| |  | | (_| | | | |   <  __/ |_| || | (_) | (_) | |
|_|  |_|\__,_|_| |_|_|\_\___|\__, (_)_|\___/ \___/|_|
                             |___/
BANNER
    printf '\033[0m\n'
}

usage() {
    cat <<'USAGE'
Usage: ./start.sh [options]

Prepare the Laravel application for local development or deployment.

Options:
  --composer-update  Run "composer update" instead of "composer install".
  --skip-composer    Skip Composer dependency installation/update.
  --skip-node        Skip Node dependency installation/update.
  --skip-build       Skip building frontend assets with Vite.
  --npm-ci           Use "npm ci" for a clean install instead of "npm install".
  --migrate          Execute "php artisan migrate --force".
  --fresh            Execute "php artisan migrate:fresh --seed --force".
  --skip-tests       Do not run the Composer test suite.
  -h, --help         Display this help message.

Environment variables:
  APP_ENV            Used when running Artisan commands; defaults to existing value.
USAGE
}

section() {
    printf '\n\033[1;34m➡ %s\033[0m\n' "$1"
}

info() {
    printf '\033[0;36m• %s\033[0m\n' "$1"
}

success() {
    printf '\033[1;32m✓ %s\033[0m\n' "$1"
}

warning() {
    printf '\033[1;33m! %s\033[0m\n' "$1"
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        printf '\033[1;31mError:\033[0m Required command "%s" is not available in PATH.\n' "$1" >&2
        exit 1
    fi
}

run_cmd() {
    printf '\033[0;36m$ %s\033[0m\n' "$*"
    "$@"
}

ensure_env_file() {
    if [[ -f .env ]]; then
        return
    fi

    if [[ -f .env.example ]]; then
        run_cmd cp .env.example .env
        success 'Created .env from .env.example.'
    else
        printf '\033[1;31mError:\033[0m Missing .env file and no .env.example to copy from.\n' >&2
        exit 1
    fi
}

ensure_app_key() {
    if ! grep -q '^APP_KEY=' .env; then
        warning 'APP_KEY entry missing in .env; generating one.'
        run_cmd php artisan key:generate --ansi
        return
    fi

    local current_key
    current_key="$(grep '^APP_KEY=' .env | head -n1 | cut -d'=' -f2-)"
    if [[ -z "$current_key" ]]; then
        warning 'APP_KEY is empty; generating a new key.'
        run_cmd php artisan key:generate --ansi
    else
        info 'APP_KEY already present.'
    fi
}

COMPOSER_UPDATE=0
SKIP_COMPOSER=0
SKIP_NODE=0
SKIP_BUILD=0
NPM_CI=0
RUN_MIGRATIONS=0
FRESH_SETUP=0
SKIP_TESTS=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --composer-update)
            COMPOSER_UPDATE=1
            shift
            ;;
        --skip-composer)
            SKIP_COMPOSER=1
            shift
            ;;
        --skip-node)
            SKIP_NODE=1
            shift
            ;;
        --skip-build)
            SKIP_BUILD=1
            shift
            ;;
        --npm-ci)
            NPM_CI=1
            shift
            ;;
        --migrate)
            RUN_MIGRATIONS=1
            shift
            ;;
        --fresh)
            FRESH_SETUP=1
            RUN_MIGRATIONS=1
            shift
            ;;
        --skip-tests)
            SKIP_TESTS=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            printf '\033[1;31mError:\033[0m Unknown option: %s\n' "$1" >&2
            usage
            exit 1
            ;;
    esac
done

banner

section "Checking required tooling"
require_command php
if [[ $SKIP_COMPOSER -eq 0 ]]; then
    require_command composer
fi
if [[ $SKIP_NODE -eq 0 ]]; then
    require_command npm
    require_command node
fi

section "Ensuring environment configuration"
ensure_env_file
ensure_app_key

section "Preparing storage directories"
run_cmd mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache
run_cmd touch storage/logs/laravel.log

section "Setting filesystem permissions"
run_cmd chmod -R ug+rwX storage bootstrap/cache
run_cmd find storage -type d -exec chmod 775 {} +
run_cmd find storage -type f -exec chmod 664 {} +
run_cmd chmod -R 775 bootstrap/cache
if command -v chown >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

if [[ $SKIP_COMPOSER -eq 0 ]]; then
    if [[ $COMPOSER_UPDATE -eq 1 ]]; then
        section "Updating Composer dependencies"
        run_cmd composer update --no-interaction --prefer-dist
    else
        section "Installing Composer dependencies"
        run_cmd composer install --no-interaction --prefer-dist --optimize-autoloader
    fi
fi

if [[ $RUN_MIGRATIONS -eq 1 ]]; then
    if [[ $FRESH_SETUP -eq 1 ]]; then
        section "Refreshing database schema (migrate:fresh)"
        run_cmd php artisan migrate:fresh --seed --force
    else
        section "Running pending database migrations"
        run_cmd php artisan migrate --force
    fi
fi

if [[ $SKIP_NODE -eq 0 ]]; then
    section "Installing Node dependencies"
    if [[ $NPM_CI -eq 1 ]]; then
        if [[ -f package-lock.json ]]; then
            run_cmd npm ci
        else
            warning 'package-lock.json missing; falling back to npm install.'
            run_cmd npm install
        fi
    else
        run_cmd npm install
    fi

    if [[ $SKIP_BUILD -eq 0 ]]; then
        BUILD_SCRIPT="$(node -pe "(() => { try { const pkg = JSON.parse(require('fs').readFileSync('package.json', 'utf8')); return pkg.scripts && pkg.scripts.build ? pkg.scripts.build : '' } catch (_) { return '' } })()" 2>/dev/null)"
        if [[ -n "${BUILD_SCRIPT:-}" ]]; then
            section "Building frontend assets"
            run_cmd npm run build
        else
            warning 'No "build" script defined in package.json; skipping asset build.'
        fi
    fi
fi

section "Clearing Laravel caches"
run_cmd php artisan optimize:clear
run_cmd php artisan cache:clear
run_cmd php artisan config:clear
run_cmd php artisan route:clear
run_cmd php artisan view:clear

section "Rebuilding Laravel caches"
run_cmd php artisan config:cache
run_cmd php artisan route:cache
run_cmd php artisan view:cache
run_cmd php artisan event:cache
run_cmd php artisan optimize

if [[ $SKIP_TESTS -eq 0 ]]; then
    section "Running automated tests"
    if composer run-script --list 2>/dev/null | grep -qE '(^|\s)test(\s|$)'; then
        run_cmd composer test --ansi
    else
        run_cmd php artisan test --ansi
    fi
else
    section "Skipping automated tests (per flag)"
fi

section "Restarting Laravel queues"
if ! run_cmd php artisan queue:restart; then
    warning 'Queue restart failed; queue may not be running.'
fi

success 'Laravel application is ready.'
