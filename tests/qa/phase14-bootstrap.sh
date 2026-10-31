#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
QA_ROOT="/tmp/superwoo-phase10"
SITE="/tmp/superwoo-phase11/site"
DB_ROOT="$QA_ROOT/db"
DB_SOCKET="$QA_ROOT/mysql.sock"
DB_PID="$QA_ROOT/mysql.pid"
HTTP_PORT="8765"
HTTP_URL="http://127.0.0.1:${HTTP_PORT}"
CHROME="/tmp/superwoo-phase10-browsers/chromium_headless_shell-1217/chrome-headless-shell-mac-arm64/chrome-headless-shell"
JQUERY="$SITE/wp-includes/js/jquery/jquery.min.js"
SCREENSHOT="$ROOT/docs/smart-cart/evidence/phase-14/bootstrap-smoke.png"

die() { printf 'QA bootstrap: %s\n' "$1" >&2; exit 1; }
command -v php >/dev/null || die "PHP CLI is missing."
command -v mariadb >/dev/null || die "MariaDB client is missing."
command -v mariadb-admin >/dev/null || die "MariaDB admin client is missing."
command -v node >/dev/null || die "Node.js is missing."
command -v wp >/dev/null || die "WP-CLI is missing."
command -v curl >/dev/null || die "curl is missing."
command -v lsof >/dev/null || die "lsof is missing."
[[ -x "$CHROME" ]] || die "Expected sandboxed Chromium executable is missing: $CHROME"
[[ -f "$JQUERY" ]] || die "Disposable WordPress jQuery fixture is missing: $JQUERY"
[[ -f "$SITE/wp-config.php" && -d "$DB_ROOT/superwoo_phase11" && -d "$DB_ROOT/superwoo_offer_audit" ]] || die "Expected disposable WordPress/database identities were not found. Refusing alternate data stores."

db_ping() {
    mariadb-admin --protocol=socket --socket="$DB_SOCKET" ping >/dev/null 2>&1
}

if ! db_ping; then
    if [[ -f "$DB_PID" ]]; then
        existing_pid="$(cat "$DB_PID" 2>/dev/null || true)"
        if [[ "$existing_pid" =~ ^[0-9]+$ ]] && kill -0 "$existing_pid" 2>/dev/null; then
            die "The disposable MariaDB PID is alive but its socket is unavailable; refusing to start a second server."
        fi
    fi
    mariadbd --no-defaults --user="$(id -un)" --datadir="$DB_ROOT" --socket="$DB_SOCKET" --pid-file="$DB_PID" --skip-networking --log-error="$QA_ROOT/mysql.log" >/dev/null 2>&1 &
    for _ in {1..30}; do
        db_ping && break
        sleep 1
    done
    db_ping || die "Could not start the isolated MariaDB instance. See $QA_ROOT/mysql.log."
fi

db_name="$(wp --path="$SITE" config get DB_NAME 2>&1 | sed -n '/^superwoo_phase11$/p' | tail -1)"
[[ "$db_name" == "superwoo_phase11" ]] || die "Disposable WordPress database identity is not superwoo_phase11; refusing to continue."
wp --path="$SITE" db check >/dev/null 2>&1 || die "WordPress could not connect to its configured disposable database."

site_url="$(wp --path="$SITE" option get siteurl 2>&1 | grep -Fx "$HTTP_URL" | tail -1)"
home_url="$(wp --path="$SITE" option get home 2>&1 | grep -Fx "$HTTP_URL" | tail -1)"
[[ "$site_url" == "$HTTP_URL" && "$home_url" == "$HTTP_URL" ]] || die "Disposable WordPress URLs do not match $HTTP_URL."

if ! curl --silent --show-error --fail --max-time 4 "$HTTP_URL/product/audit-hair-boost-4/" >/dev/null 2>&1; then
    if lsof -nP -iTCP:"$HTTP_PORT" -sTCP:LISTEN >/dev/null 2>&1; then
        die "Port $HTTP_PORT is occupied but the expected disposable product page did not respond; refusing to use an unknown server."
    fi
    php -S "127.0.0.1:$HTTP_PORT" -t "$SITE" "$QA_ROOT/router.php" >"$QA_ROOT/http.log" 2>&1 &
    for _ in {1..20}; do
        curl --silent --show-error --fail --max-time 2 "$HTTP_URL/product/audit-hair-boost-4/" >/dev/null 2>&1 && break
        sleep 1
    done
fi
curl --silent --show-error --fail --max-time 4 "$HTTP_URL/" >/dev/null || die "Disposable homepage did not return HTTP 200."
curl --silent --show-error --fail --max-time 4 "$HTTP_URL/product/audit-hair-boost-4/" >/dev/null || die "Disposable product page did not return HTTP 200."
curl --silent --show-error --fail --max-time 4 "$HTTP_URL/cart/" >/dev/null || die "Disposable cart page did not return HTTP 200."

mkdir -p "$(dirname "$SCREENSHOT")"
profile="$(mktemp -d /tmp/superwoo-phase14-chrome-XXXXXX)"
dom_file="$(mktemp /tmp/superwoo-phase14-dom-XXXXXX)"
trap 'rm -rf "$profile"; rm -f "$dom_file"' EXIT
"$CHROME" --headless=new --disable-gpu --disable-background-networking --no-first-run --user-data-dir="$profile" --host-resolver-rules='MAP * ~NOTFOUND, EXCLUDE 127.0.0.1' --virtual-time-budget=3000 --screenshot="$SCREENSHOT" --dump-dom "$HTTP_URL/" >"$dom_file"
grep -qi 'SuperWoo' "$dom_file" || die "Chromium did not render the expected disposable WordPress page."
[[ -s "$SCREENSHOT" ]] || die "Chromium did not produce the smoke screenshot."

SUPERWOO_TEST_CHROME="$CHROME" SUPERWOO_TEST_JQUERY="$JQUERY" node --test "$ROOT/tests/cart-add-performance-browser.test.js"
printf 'QA_READY=YES\nDATABASE=superwoo_phase11 + superwoo_offer_audit (isolated)\nHTTP=%s (loopback only)\nCHROMIUM_SANDBOX=enabled\nSCREENSHOT=%s\n' "$HTTP_URL" "$SCREENSHOT"
