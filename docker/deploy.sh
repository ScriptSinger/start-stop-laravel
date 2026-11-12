#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

cd "${DEPLOY_ROOT:-$HOME/start-stop}"
exec 9>.deploy.lock
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }

archive=${1:?Release archive required}
configuration=${2:?Configuration file required}
stage=$(mktemp -d "$PWD/.release-XXXXXXXX")
previous=$(mktemp -d "$PWD/.previous-XXXXXXXX")
migration_started=0
maintenance=0
replaced=0

compose() { docker compose "$@"; }
artisan() { compose exec -T php php artisan "$@" --no-interaction; }
timed() {
    local label=$1
    shift
    local started=$SECONDS
    "$@"
    printf 'TIMING: %s: %s seconds\n' "$label" "$((SECONDS - started))"
}

failure() {
    trap - ERR
    set +e
    if (( maintenance )); then
        artisan down --render=errors::503 --retry=60 || compose stop nginx
    fi
    if (( migration_started )); then
        echo "Deployment failed after migrations started. Maintenance remains enabled; inspect database before recovery. Previous code: $previous" >&2
        compose stop worker scheduler
        exit 1
    fi
    if (( replaced )); then
        tar -xpzf "$previous/code.tar.gz" -C .
        if test -f "$previous/configuration"; then
            mv "$previous/configuration" .env
        fi
        compose up -d --build php nginx || exit 1
        artisan optimize:clear || exit 1
    fi
    if (( maintenance )); then
        artisan up || exit 1
        compose up -d worker scheduler || exit 1
    fi
    echo 'Deployment failed before migrations; previous application retained.' >&2
    exit 1
}
trap failure ERR

# Only the migrations and release markers are needed before the switch.
timed 'prepare migration files' tar -xpzf "$archive" -C "$stage" artisan docker-compose.prod.yml database/migrations
test -f "$stage/artisan"
test -f "$stage/docker-compose.prod.yml"
test -d "$stage/database/migrations"

# Query the running production database using the migration files of this release.
pending=0
compose exec -T php php artisan migrate:status --no-interaction --no-ansi \
    --pending=2 --path="/var/www/html/$(basename "$stage")/database/migrations" --realpath || pending=$?
case "$pending" in
    0|2) ;;
    *) echo 'Cannot determine migration state; deployment aborted.' >&2; exit 1 ;;
esac

# Preserve code only. Runtime storage and uploaded files remain in place.
timed 'save previous code' tar --exclude=bootstrap/cache --exclude=public/storage -czf "$previous/code.tar.gz" app bootstrap config database docker lang public resources routes vendor artisan composer.json composer.lock docker-compose.yml
maintenance=1
artisan down --render=errors::503 --retry=60
compose stop -t 60 worker scheduler
if (( pending == 2 )); then
    timed 'database backup' artisan db:backup --label=before-deploy
fi

replaced=1
mv .env "$previous/configuration"
mv "$configuration" .env
# Never overwrite runtime storage, including the maintenance response and uploads.
timed 'extract release' tar -xpzf "$archive" --exclude=storage --exclude=public/storage -C .
mv docker-compose.prod.yml docker-compose.yml
timed 'build containers' compose build php worker scheduler
timed 'restart web containers' compose up -d --no-deps --force-recreate php nginx
artisan optimize:clear
if (( pending == 2 )); then
    migration_started=1
    timed 'migrations' artisan migrate --force
fi
artisan storage:link --force
artisan optimize
timed 'refresh car landings' artisan car-landings:refresh
timed 'generate sitemap' artisan sitemap:generate
timed 'check Telegram webhook' artisan telegram:check-webhook https://edge.ufamasters.ru:8443/start-stop/api/telegram/webhook
# Reopen only after Artisan checks; return to maintenance if HTTP bootstrap fails.
artisan up
compose exec -T nginx wget -q -O /dev/null http://127.0.0.1/up
compose up -d worker scheduler
trap - ERR
echo "Deployment completed. Previous code retained at $previous"
