#!/bin/bash
# Parse a WP-CLI command's combined output without allowing warning text to
# satisfy the disposable-database identity check.
superwoo_wp_cli_exact_line() {
    local expected="$1"
    local output="$2"
    printf '%s\n' "$output" | awk -v expected="$expected" '
        $0 == expected { matches++ }
        END {
            if (matches == 1) {
                print expected
                exit 0
            }
            exit 1
        }
    '
}
