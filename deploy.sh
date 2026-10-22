#!/usr/bin/env bash
#
# Deploys the current branch on this server: pulls it, checks out the cms and
# ecommerce submodule commits it records, installs Composer dependencies,
# migrates, rebuilds Laravel's caches and reloads PHP-FPM so OPcache drops the
# old code (with opcache.validate_timestamps=0 it never notices changed files).
#
#   ./deploy.sh [remote [branch]]
#
# With no arguments the current branch is pulled from its upstream. A remote
# pulls the current branch from that remote instead, and a branch is switched
# to first (created to track <remote>/<branch> when it doesn't exist here):
#
#   ./deploy.sh origin main
#
# PHP-FPM is reloaded by sending USR2 to its master process. Where the deploy
# user can't signal it (a master running as root, or several PHP versions),
# say how to reload it:
#
#   PHP_FPM_RELOAD="sudo systemctl reload php8.4-fpm" ./deploy.sh
#
# Dev dependencies (phpunit, pint, boost, ...) are installed unless APP_ENV is
# production; COMPOSER_NO_DEV=1 or COMPOSER_NO_DEV=0 decides it either way.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

if [[ $# -gt 2 || "${1:-}" == -* ]]; then
    echo "Usage: $0 [remote [branch]]" >&2
    exit 1
fi

remote="${1:-}"

if [[ -n "$remote" ]]; then
    if ! git remote get-url "$remote" >/dev/null 2>&1; then
        echo "There's no git remote called \"$remote\"." >&2
        exit 1
    fi

    # Without a branch, the current one (which fails on a detached HEAD).
    branch="${2:-$(git symbolic-ref --short HEAD)}"
fi

php="${PHP_BIN:-php}"

# A real APP_ENV variable wins over .env, as it does in Laravel.
app_env="${APP_ENV:-$(sed -n 's/^APP_ENV=//p' .env 2>/dev/null | tail -n 1 | tr -d "\"'\r" || true)}"

if [[ "$app_env" == production ]]; then
    export COMPOSER_NO_DEV="${COMPOSER_NO_DEV:-1}"
else
    export COMPOSER_NO_DEV="${COMPOSER_NO_DEV:-0}"
fi

step() {
    printf '\n\033[1m==> %s\033[0m\n' "$*"
}

# Checked before anything changes, so a deploy can't finish with OPcache still
# serving the previous code.
if [[ -z "${PHP_FPM_RELOAD:-}" ]]; then
    mapfile -t fpm_masters < <(pgrep -f '^php-fpm: master' || true)

    if [[ ${#fpm_masters[@]} -ne 1 ]] || ! kill -0 "${fpm_masters[0]}" 2>/dev/null; then
        echo "Can't find one PHP-FPM master process this user can signal (found ${#fpm_masters[@]})." >&2
        echo "Say how to reload it, e.g. PHP_FPM_RELOAD=\"sudo systemctl reload php8.4-fpm\" $0${*:+ $*}" >&2
        exit 1
    fi

    PHP_FPM_RELOAD="kill -USR2 ${fpm_masters[0]}"
fi

if [[ -z "$remote" ]]; then
    step "Pulling $(git rev-parse --abbrev-ref HEAD)"
    git pull --ff-only
else
    step "Pulling $branch from $remote"
    git fetch "$remote" "$branch"

    if [[ "$(git symbolic-ref --short -q HEAD || true)" != "$branch" ]]; then
        if git show-ref --verify --quiet "refs/heads/$branch"; then
            git switch "$branch"
        else
            git switch --create "$branch" --track "$remote/$branch"
        fi
    fi

    git merge --ff-only FETCH_HEAD
fi

step 'Updating submodules'
git submodule sync --recursive
git submodule update --init --recursive

if [[ "$COMPOSER_NO_DEV" == 1 ]]; then
    step "Installing Composer dependencies without dev packages (APP_ENV=${app_env:-unset})"
else
    step "Installing Composer dependencies with dev packages (APP_ENV=${app_env:-unset})"
fi
composer install --no-interaction --prefer-dist --optimize-autoloader

step 'Migrating'
# Cleared first so the migrations read the new code's config, not a cached copy.
"$php" artisan config:clear
"$php" artisan migrate --force

step 'Rebuilding caches'
"$php" artisan optimize:clear
"$php" artisan optimize

step 'Reloading PHP-FPM'
bash -c "$PHP_FPM_RELOAD"

step 'Restarting queue workers'
"$php" artisan queue:restart

step "Deployed $(git rev-parse --short HEAD)"
