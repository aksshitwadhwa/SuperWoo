#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
source "$ROOT/tests/qa/wp-cli-db-guard.sh"
assert_parse() {
    local expected="$1" output="$2" wanted="$3" actual=""
    if actual="$(superwoo_wp_cli_exact_line "$expected" "$output")"; then
        [[ "$actual" == "$wanted" ]] || { printf 'FAIL: expected [%s], got [%s]\n' "$wanted" "$actual" >&2; exit 1; }
    else
        [[ -z "$wanted" ]] || { printf 'FAIL: expected [%s], parser rejected output\n' "$wanted" >&2; exit 1; }
    fi
}
assert_parse 'superwoo_phase14_candidate' $'Deprecated: harmless WP-CLI notice\nsuperwoo_phase14_candidate' 'superwoo_phase14_candidate'
assert_parse 'superwoo_phase14_candidate' $'superwoo_phase14_candidate\nWarning: unrelated output' 'superwoo_phase14_candidate'
assert_parse 'superwoo_phase14_candidate' $'Database is superwoo_phase14_candidate' ''
assert_parse 'superwoo_phase14_candidate' $'other_database\nWarning: expected database is superwoo_phase14_candidate' ''
assert_parse 'superwoo_phase14_candidate' $'superwoo_phase14_candidate\nsuperwoo_phase14_candidate' ''
printf 'PASS: 5 WP-CLI identity-output guard cases\n'
