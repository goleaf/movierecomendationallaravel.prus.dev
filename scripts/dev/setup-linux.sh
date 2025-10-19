#!/usr/bin/env bash
set -euo pipefail

log() {
    printf '\033[1;34m[linux-setup]\033[0m %s\n' "$1"
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        return 1
    fi
}

ensure_apt_packages() {
    local packages=("$@")

    if ((${#packages[@]} == 0)); then
        return
    fi

    log "Installing/updating packages: ${packages[*]}"
    sudo apt-get install -y "${packages[@]}"
}

ensure_php_packages() {
    local packages=(
        php8.3-cli
        php8.3-common
        php8.3-bcmath
        php8.3-curl
        php8.3-gd
        php8.3-intl
        php8.3-mbstring
        php8.3-opcache
        php8.3-readline
        php8.3-sqlite3
        php8.3-xml
        php8.3-zip
        php8.3-redis
    )

    ensure_apt_packages "${packages[@]}"
}

enable_php_extensions() {
    local version="$1"
    shift
    local extensions=("$@")

    for extension in "${extensions[@]}"; do
        if [[ -f "/etc/php/${version}/mods-available/${extension}.ini" ]]; then
            log "Enabling PHP ${version} extension: ${extension}"
            sudo phpenmod -v "${version}" "${extension}" >/dev/null 2>&1 || true
        fi
    done
}

configure_php_cli_cache() {
    local version="$1"
    local ini_path="/etc/php/${version}/cli/conf.d/99-movierec.ini"

    log "Configuring PHP ${version} CLI caching defaults in ${ini_path}"
    sudo tee "${ini_path}" >/dev/null <<'CONF'
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=192
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=0
CONF
}

ensure_composer() {
    if require_command composer; then
        return
    fi

    log "Installing Composer"
    local tmp_dir
    tmp_dir="$(mktemp -d)"
    trap 'rm -rf "${tmp_dir}"' EXIT

    curl -sS https://getcomposer.org/installer -o "${tmp_dir}/installer.php"
    php "${tmp_dir}/installer.php" --install-dir="${tmp_dir}" --filename=composer
    sudo mv "${tmp_dir}/composer" /usr/local/bin/composer

    rm -rf "${tmp_dir}"
    trap - EXIT
}

configure_composer_cache() {
    local cache_dir="$HOME/.cache/composer"
    local composer_home="$HOME/.config/composer"

    mkdir -p "${cache_dir}" "${composer_home}"
    log "Configuring Composer cache directory at ${cache_dir}"
    COMPOSER_HOME="${composer_home}" composer config -g cache-dir "${cache_dir}" >/dev/null
    COMPOSER_HOME="${composer_home}" composer config -g cache-files-dir "${cache_dir}" >/dev/null
    COMPOSER_HOME="${composer_home}" composer config -g cache-repo-dir "${cache_dir}/repo" >/dev/null
}

ensure_node() {
    if require_command node && [[ $(node -v) == v20.* ]]; then
        return
    fi

    log "Installing Node.js 20.x from NodeSource"
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
}

ensure_service_running() {
    local service_name="$1"

    if command -v systemctl >/dev/null 2>&1; then
        if ! sudo systemctl is-active --quiet "${service_name}"; then
            log "Starting ${service_name} via systemctl"
            sudo systemctl enable --now "${service_name}" || true
        fi
        return
    fi

    if command -v service >/dev/null 2>&1; then
        log "Starting ${service_name} via service"
        sudo service "${service_name}" start || true
    fi
}

php_repo_configured() {
    compgen -G "/etc/apt/sources.list.d/ondrej-ubuntu-php*.list" >/dev/null
}

main() {
    if [[ $(uname -s) != "Linux" ]]; then
        echo "This setup script is intended for Linux environments." >&2
        exit 1
    fi

    if ! require_command apt-get; then
        echo "apt-get is required. Install packages manually or use a Debian/Ubuntu derivative." >&2
        exit 1
    fi

    if ! require_command sudo; then
        echo "sudo is required to install system packages." >&2
        exit 1
    fi

    log "Updating apt sources"
    sudo apt-get update -y
    ensure_apt_packages software-properties-common apt-transport-https ca-certificates curl gnupg lsb-release

    if ! php_repo_configured; then
        log "Adding Ondřej Surý PHP repository"
        sudo add-apt-repository -y ppa:ondrej/php
    fi

    log "Refreshing apt sources"
    sudo apt-get update -y

    ensure_php_packages
    enable_php_extensions "8.3" sqlite3 pdo_sqlite redis opcache
    configure_php_cli_cache "8.3"

    if command -v update-alternatives >/dev/null 2>&1; then
        log "Setting PHP 8.3 as the default CLI version"
        sudo update-alternatives --set php /usr/bin/php8.3 || true
        sudo update-alternatives --set phar /usr/bin/phar8.3 || true
        sudo update-alternatives --set phar.phar /usr/bin/phar.phar8.3 || true
    fi

    ensure_composer
    configure_composer_cache

    ensure_node

    ensure_apt_packages sqlite3 redis-server
    ensure_service_running redis-server

    log "Verifying versions"
    php -v | head -n 1
    composer --version
    node -v
    npm -v
    sqlite3 --version | awk '{print $1}'
    redis-cli --version || true
    redis-cli ping || true

    log "Linux environment ready for MovieRec"
}

main "$@"
