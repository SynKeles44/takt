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
#   TAKT_HOST=local.takt.de  the name Takt answers on
#   TAKT_PORT=8000           the port the server listens on
#   TAKT_AUTOSTART=1|0       install the login item without asking

set -euo pipefail

REPO="${TAKT_REPO:-SynKeles44/takt}"
REF="${TAKT_REF:-main}"
DIR="${TAKT_DIR:-$HOME/Takt}"
HOST="${TAKT_HOST:-local.takt.de}"
PORT="${TAKT_PORT:-8000}"
URL="http://$HOST:$PORT"

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
info() { printf '  %s\n' "$1"; }
fail() { printf '\033[31m  %s\033[0m\n' "$1" >&2; exit 1; }

bold "Takt"
info "Repository: $REPO ($REF)"
info "Zielordner: $DIR"
info "Adresse:    $URL"
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

# ------------------------------------------------------------------------- name
# The name has to reach this machine before anything else uses it. This is the only
# step that needs administrator rights, and only the first time.
if [ "$HOST" != "localhost" ] && ! grep -qE "^[^#]*[[:space:]]$HOST([[:space:]]|$)" /etc/hosts; then
    bold "Name eintragen"
    info "$HOST zeigt danach auf diesen Rechner (/etc/hosts, einmalig)."

    if sudo -n true 2>/dev/null; then
        sudo sh -c "printf '127.0.0.1 %s # takt\n' '$HOST' >> /etc/hosts"
        info "Eingetragen."
    elif [ -t 0 ]; then
        sudo sh -c "printf '127.0.0.1 %s # takt\n' '$HOST' >> /etc/hosts" \
            && info "Eingetragen." \
            || { info "Übersprungen — Takt läuft dann unter http://localhost:$PORT."; HOST="localhost"; URL="http://localhost:$PORT"; }
    else
        info "Ohne Rechte übersprungen. Später einmal:"
        info "  sudo sh -c 'printf \"127.0.0.1 $HOST # takt\\n\" >> /etc/hosts'"
        HOST="localhost"
        URL="http://localhost:$PORT"
    fi
fi

# ------------------------------------------------------------------- everything
autostart_flag=""
autostart="${TAKT_AUTOSTART:-}"

if [ "$(uname -s)" = "Darwin" ]; then
    if [ -z "$autostart" ] && [ -t 0 ]; then
        printf '  Server automatisch beim Anmelden starten? [J/n] '
        read -r answer
        case "$answer" in [nN]*) autostart=0 ;; *) autostart=1 ;; esac
    fi

    [ "${autostart:-1}" = "0" ] && autostart_flag="--no-autostart"
fi

bold "Einrichten"
php artisan takt:setup --host="$HOST" --port="$PORT" $autostart_flag | sed 's/^/  /'

# Without a login item the server is started here: a detached server and a captured pipe
# do not mix, so this belongs in the shell and not in the artisan command.
if [ "$autostart_flag" = "--no-autostart" ] || [ "$(uname -s)" != "Darwin" ]; then
    bold "Server starten"
    nohup make start PORT="$PORT" </dev/null >/dev/null 2>&1 &

    for _ in $(seq 1 40); do
        curl -fsS -o /dev/null -m 1 "http://127.0.0.1:$PORT/" && break
        sleep 0.25
    done

    if curl -fsS -o /dev/null -m 1 "http://127.0.0.1:$PORT/"; then
        info "Läuft auf Port $PORT."
    else
        info "Kommt nicht hoch — siehe storage/logs/serve.log."
    fi
fi

# --------------------------------------------------------------------- finished
echo
bold "Fertig"
info "Ordner:  $DIR"

if [ "$(uname -s)" = "Darwin" ]; then
    info "App:     ~/Applications/Takt.app"
    open -a "$HOME/Applications/Takt.app" 2>/dev/null && info "App geöffnet."
fi

info "Browser: $URL"
info "Lege beim ersten Start ein Konto an — Takt läuft komplett lokal."
