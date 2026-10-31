#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
source "$ROOT/tests/qa/wp-cli-db-guard.sh"
SITE="/tmp/superwoo-phase14-release/site"
EXPECTED_DB="superwoo_phase14_candidate"
EXPECTED_URL="http://127.0.0.1:8770"
OLD_ZIP="/tmp/superwoo-phase15-release/SuperWoo-1.6.31-PUBLISHED.zip"
NEW_ZIP="/tmp/superwoo-phase16-release/artifacts/SuperWoo-1.6.32.zip"

die() { printf 'Phase 16 upgrade verification: %s\n' "$1" >&2; exit 1; }
[[ -f "$SITE/wp-config.php" ]] || die "expected disposable wp-config.php is missing; refusing another site."
[[ -f "$OLD_ZIP" && -f "$NEW_ZIP" ]] || die "both exact source and final candidate archives must exist."
unzip -tqq "$OLD_ZIP" >/dev/null || die "published 1.6.31 source archive is invalid."
unzip -tqq "$NEW_ZIP" >/dev/null || die "final 1.6.32 candidate archive is invalid."

capture_wp() {
    local output status
    set +e
    output="$(wp --path="$SITE" "$@" 2>&1)"
    status=$?
    set -e
    [[ $status -eq 0 ]] || die "WP-CLI command failed: wp $*\n$output"
    printf '%s' "$output"
}

db_output="$(capture_wp config get DB_NAME)"
db_name="$(superwoo_wp_cli_exact_line "$EXPECTED_DB" "$db_output")" || die "database identity output did not contain exactly one expected DB_NAME."
[[ "$db_name" == "$EXPECTED_DB" ]] || die "database mismatch; refusing to run upgrade."
site_output="$(capture_wp option get siteurl)"
site_url="$(superwoo_wp_cli_exact_line "$EXPECTED_URL" "$site_output")" || die "siteurl output did not contain exactly one expected disposable URL."
home_output="$(capture_wp option get home)"
home_url="$(superwoo_wp_cli_exact_line "$EXPECTED_URL" "$home_output")" || die "home output did not contain exactly one expected disposable URL."
[[ "$site_url" == "$EXPECTED_URL" && "$home_url" == "$EXPECTED_URL" ]] || die "disposable URL mismatch; refusing to run upgrade."
wp --path="$SITE" db check >/dev/null 2>&1 || die "WordPress cannot connect to the guarded disposable database."

read_version() {
    local output value
    output="$(capture_wp plugin get SuperWoo --field=version)"
    value="$(printf '%s\n' "$output" | awk '/^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$/ { found=$0; count++ } END { if (count == 1) print found; else exit 1 }')" || die "could not parse exactly one plugin version from WP-CLI output."
    printf '%s' "$value"
}

settings_hash() {
    local output value
    output="$(capture_wp eval 'echo hash("sha256", serialize(get_option("superwoo_settings")));')"
    value="$(printf '%s\n' "$output" | awk '/^[a-f0-9]{64}$/ { found=$0; count++ } END { if (count == 1) print found; else exit 1 }')" || die "could not parse settings hash from WP-CLI output."
    printf '%s' "$value"
}

original_version="$(read_version)"
wp --path="$SITE" plugin is-active SuperWoo >/dev/null 2>&1 || die "SuperWoo must be active before test."
before_original_settings="$(settings_hash)"

wp --path="$SITE" plugin install "$OLD_ZIP" --force --activate >/dev/null 2>&1 || die "could not install source 1.6.31 archive into disposable clone."
[[ "$(read_version)" == "1.6.31" ]] || die "source archive did not produce plugin version 1.6.31."
wp --path="$SITE" plugin is-active SuperWoo >/dev/null 2>&1 || die "SuperWoo is not active after installing 1.6.31."
before_upgrade_settings="$(settings_hash)"
[[ "$before_upgrade_settings" == "$before_original_settings" ]] || die "settings changed while staging the 1.6.31 baseline."

wp --path="$SITE" plugin install "$NEW_ZIP" --force --activate >/dev/null 2>&1 || die "could not install exact final candidate archive."
[[ "$(read_version)" == "1.6.32" ]] || die "candidate archive did not produce plugin version 1.6.32."
wp --path="$SITE" plugin is-active SuperWoo >/dev/null 2>&1 || die "SuperWoo is not active after upgrading to candidate."
after_upgrade_settings="$(settings_hash)"
[[ "$before_upgrade_settings" == "$after_upgrade_settings" ]] || die "superwoo_settings hash changed during 1.6.31 -> 1.6.32 upgrade."
wp --path="$SITE" plugin is-active woocommerce >/dev/null 2>&1 || die "WooCommerce is not active after candidate install."

printf 'UPGRADE_VERIFICATION=PASS\n'
printf 'SITE=%s\nDATABASE=%s\nURL=%s\n' "$SITE" "$db_name" "$site_url"
printf 'INITIAL_VERSION=%s\nTESTED_PATH=1.6.31 -> 1.6.32\n' "$original_version"
printf 'FINAL_VERSION=%s\nSUPERWOO_ACTIVE=YES\nWOOCOMMERCE_ACTIVE=YES\nSETTINGS_SHA256_BEFORE=%s\nSETTINGS_SHA256_AFTER=%s\n' "$(read_version)" "$before_upgrade_settings" "$after_upgrade_settings"
