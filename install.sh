#!/usr/bin/env bash
#
# Takt — one-line install.
#
#   curl -fsSL https://raw.githubusercontent.com/SynKeles44/takt/main/install.sh | bash
#
# Overrides:
#   TAKT_DIR=~/Code/takt   where it lands (default: ~/Takt)
#   TAKT_REPO=owner/name   which repository to clone
#   TAKT_REF=main          which branch or tag
#   TAKT_AUTOSTART=1|0     install the login item without asking

set -euo pipefail

REPO="${TAKT_REPO:-SynKeles44/takt}"
REF="${TAKT_REF:-main}"
DIR="${TAKT_DIR:-$HOME/Takt}"

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
info() { printf '  %s\n' "$1"; }
fail() { printf '\033[31m  %s\033[0m\n' "$1" >&2; exit 1; }

bold "Takt"
info "Repository: $REPO ($REF)"
info "Zielordner: $DIR"
echo

# ---------------------------------------------------------------- requirements
missing=()
have() { command -v "$1" >/dev/null 2>&1; }

have git || missing+=("git")
have composer || missing+=("composer")
have npm || missing+=("node/npm")

if have php; then
    php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || fail "PHP 8.3 oder neuer nötig, gefunden $(php -r 'echo PHP_VERSION;')"
    # no `php -m | grep -q`: grep closes the pipe early and pipefail then fails the check
    php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' || missing+=("PHP-Erweiterung pdo_sqlite")
    php -r 'exit(extension_loaded("gd") ? 0 : 1);' || missing+=("PHP-Erweiterung gd")
else
    missing+=("php")
fi

if [ ${#missing[@]} -gt 0 ]; then
    joined=$(printf '%s, ' "${missing[@]}")
    printf '\033[31m  Es fehlt: %s\033[0m\n' "${joined%, }" >&2
    echo
    info "Auf macOS mit Homebrew:"
    info "  brew install php composer node git"
    exit 1
fi

# ------------------------------------------------------------------- get source
if [ -d "$DIR/.git" ]; then
    bold "Aktualisieren"
    git -C "$DIR" fetch --quiet origin "$REF"
    git -C "$DIR" checkout --quiet "$REF"
    git -C "$DIR" pull --quiet --ff-only
else
    [ -e "$DIR" ] && [ -n "$(ls -A "$DIR" 2>/dev/null)" ] && fail "$DIR ist nicht leer — setze TAKT_DIR auf einen anderen Pfad."
    bold "Herunterladen"
    git clone --quiet --branch "$REF" --depth 1 "https://github.com/$REPO.git" "$DIR"
fi

cd "$DIR"

# --------------------------------------------------------------------- install
bold "Abhängigkeiten"
composer install --no-interaction --prefer-dist --quiet
npm ci --silent 2>/dev/null || npm install --silent
npm run build --silent >/dev/null

bold "Einrichten"
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --quiet
touch database/database.sqlite
php artisan migrate --force --quiet
php artisan takt:icons >/dev/null

# ------------------------------------------------------------------- desktop app
if [ "$(uname -s)" = "Darwin" ]; then
    bold "App bauen"
    php artisan takt:app | sed 's/^/  /'

    autostart="${TAKT_AUTOSTART:-}"

    if [ -z "$autostart" ] && [ -t 0 ]; then
        printf '  Server automatisch beim Anmelden starten? [J/n] '
        read -r answer
        case "$answer" in [nN]*) autostart=0 ;; *) autostart=1 ;; esac
    fi

    if [ "$autostart" = "1" ]; then
        php artisan takt:autostart | sed 's/^/  /'
    else
        info "Ohne Login-Dienst: 'make start' im Ordner $DIR startet den Server."
    fi
else
    bold "Starten"
    info "make start   →  http://localhost:8000"
    info "Das App-Bundle gibt es nur auf macOS."
fi

echo
bold "Fertig"
info "Ordner:  $DIR"
if [ "$(uname -s)" = "Darwin" ]; then
    info "App:     ~/Applications/Takt.app  (Doppelklick)"
fi
info "Browser: http://localhost:8000"
info "Lege beim ersten Start ein Konto an — Takt läuft komplett lokal."
