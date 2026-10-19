#!/usr/bin/env bash
# Full QA run: PHP lint, fresh site, seed data, e2e suites, debug.log check.
#
# Usage: WP_SRC=... SQLITE_SRC=... WC_SRC=... ENV_DIR=... WP_CLI_PHAR=... tests/run-all.sh
set -uo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
ENV_DIR="${ENV_DIR:-$REPO/tests/.env}"
SITE="$ENV_DIR/site"
PORT="${PORT:-8089}"
export WP_CLI="php ${WP_CLI_PHAR:-$ENV_DIR/wp-cli.phar} --path=$SITE --allow-root"
export NODE_PATH="${NODE_PATH:-$(npm root -g)}"
export BASE_URL="http://localhost:$PORT"
status=0

echo "== PHP lint"
lint_fail=0
for f in $(cd "$REPO" && git ls-files '*.php' | grep -v '^vendor/\|/codestar/\|RTL_License\|base-menu.php'); do
  php -l "$REPO/$f" >/dev/null || { echo "lint failed: $f"; lint_fail=1; }
done
[ $lint_fail -eq 0 ] && echo "all PHP files lint clean" || status=1

echo "== Fresh site"
ENV_DIR="$ENV_DIR" PORT="$PORT" WP_CLI="${WP_CLI_PHAR:-$ENV_DIR/wp-cli.phar}" "$REPO/tests/setup-env.sh" >/dev/null 2>&1 || { echo "setup failed"; exit 1; }
$WP_CLI eval-file "$REPO/tests/seed.php" 2>/dev/null
$WP_CLI wc tool run install_pages --user=admin >/dev/null 2>&1
$WP_CLI option update woocommerce_coming_soon no >/dev/null 2>&1
$WP_CLI rewrite flush >/dev/null 2>&1
tables=$($WP_CLI eval 'global $wpdb; echo count($wpdb->get_col("SELECT name FROM sqlite_master WHERE name LIKE \"%tkm_%\" AND type=\"table\""));' 2>/dev/null)
[ "$tables" = "5" ] && echo "activation created 5 tables" || { echo "expected 5 tables, got $tables"; status=1; }

if curl -s -o /dev/null "http://localhost:$PORT/"; then
  echo "port $PORT is already in use — stop the other server first"; exit 1
fi
php -S "localhost:$PORT" -t "$SITE" >/dev/null 2>&1 &
SERVER=$!   # the php process itself, so the trap really stops it
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1
: > "$SITE/debug.log"

for suite in front admin edge; do
  echo "== e2e: $suite"
  node "$REPO/tests/e2e/$suite.test.cjs" || status=1
done

echo "== debug.log (Ticketchi entries)"
# Only entries raised from the plugin's own files count (core may warn about blocked wordpress.org, etc.).
if grep "^\[" "$SITE/debug.log" | grep "wp-content/plugins/ticketchi/" | grep -iv "textdomain"; then
  status=1
else
  echo "no PHP notices, warnings or errors from the plugin"
fi

exit $status
