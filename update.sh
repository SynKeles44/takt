#!/usr/bin/env bash
#
# Takt — update an existing installation.
#
#   curl -fsSL https://raw.githubusercontent.com/SynKeles44/takt/main/update.sh | bash
#
# Overrides:
#   TAKT_DIR=~/Code/takt   which installation to update (default: ~/Takt)
#   TAKT_REF=main          which branch or tag to move to

set -euo pipefail

DIR="${TAKT_DIR:-$HOME/Takt}"
REF="${TAKT_REF:-main}"

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
info() { printf '  %s\n' "$1"; }
fail() { printf '\033[31m  %s\033[0m\n' "$1" >&2; exit 1; }

[ -d "$DIR/.git" ] || fail "In $DIR liegt keine Takt-Installation — setze TAKT_DIR oder nutze install.sh."

cd "$DIR"

bold "Takt aktualisieren"
info "Ordner: $DIR"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    fail "Es gibt lokale Änderungen. Sichere oder verwirf sie zuerst (git status)."
fi

before="$(git rev-parse --short HEAD)"

git fetch --quiet origin "$REF"
git checkout --quiet "$REF"
git pull --quiet --ff-only

after="$(git rev-parse --short HEAD)"

if [ "$before" = "$after" ]; then
    info "Schon aktuell ($after)."
    exit 0
fi

info "$before → $after"
echo

bold "Abhängigkeiten"
composer install --no-interaction --prefer-dist --quiet
npm ci --silent 2>/dev/null || npm install --silent
npm run build --silent >/dev/null

bold "Datenbank"
php artisan migrate --force --quiet
php artisan takt:icons >/dev/null

if [ "$(uname -s)" = "Darwin" ]; then
    bold "App neu bauen"
    php artisan takt:app | sed 's/^/  /'

    if [ -f "$HOME/Library/LaunchAgents/de.takt.server.plist" ]; then
        launchctl kickstart -k "gui/$(id -u)/de.takt.server" >/dev/null 2>&1 || true
        info "Server neu gestartet."
    fi

    pgrep -x Takt >/dev/null && info "Takt läuft — einmal beenden und neu öffnen, damit das neue Fenster greift."
fi

echo
bold "Fertig"
git --no-pager log --oneline "$before..$after" | head -10 | sed 's/^/  /' || true
