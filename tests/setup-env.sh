#!/usr/bin/env bash
# Builds a disposable WordPress (SQLite) + WooCommerce site for testing Ticketchi.
#
# Usage: WP_SRC=... SQLITE_SRC=... WC_SRC=... ENV_DIR=... tests/setup-env.sh
#   WP_SRC      WordPress core checkout (github.com/WordPress/WordPress)
#   SQLITE_SRC  sqlite-database-integration plugin folder
#   WC_SRC      unzipped woocommerce plugin folder (optional)
#   ENV_DIR     where the site is created (default: tests/.env)
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
ENV_DIR="${ENV_DIR:-$REPO/tests/.env}"
SITE="$ENV_DIR/site"
WP="php ${WP_CLI:-$ENV_DIR/wp-cli.phar} --path=$SITE --allow-root"
PORT="${PORT:-8089}"

rm -rf "$SITE"
mkdir -p "$SITE"
cp -r "$WP_SRC/." "$SITE/"
rm -rf "$SITE/.git"

cp -rL "$SQLITE_SRC" "$SITE/wp-content/plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    "$SQLITE_SRC/db.copy" > "$SITE/wp-content/db.php"

if [ -n "${WC_SRC:-}" ]; then
  cp -r "$WC_SRC" "$SITE/wp-content/plugins/woocommerce"
fi

cat > "$SITE/wp-config.php" <<CFG
<?php
define('DB_NAME', 'wp'); define('DB_USER', ''); define('DB_PASSWORD', ''); define('DB_HOST', '');
define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('DB_DIR', __DIR__ . '/wp-content/database/'); define('DB_FILE', '.ht.sqlite');
\$table_prefix = 'wp_';
define('WP_DEBUG', true); define('WP_DEBUG_LOG', __DIR__ . '/debug.log'); define('WP_DEBUG_DISPLAY', false);
define('WP_HOME', 'http://localhost:$PORT'); define('WP_SITEURL', 'http://localhost:$PORT');
define('DISABLE_WP_CRON', true); define('AUTOMATIC_UPDATER_DISABLED', true);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
CFG

"$REPO/tests/sync-plugin.sh" "$SITE"

$WP core install --url="http://localhost:$PORT" --title="Ticketchi Lab" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
$WP option update WPLANG fa_IR || true
$WP rewrite structure '/%postname%/' --hard
[ -n "${WC_SRC:-}" ] && $WP plugin activate woocommerce
$WP plugin activate ticketchi
echo "Site ready at http://localhost:$PORT (admin/admin)"
