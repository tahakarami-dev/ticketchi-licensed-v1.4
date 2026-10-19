#!/usr/bin/env bash
# Copies the working tree into the test site and swaps the ionCube-encoded
# BASE_MENU for the test stub (the loader is not available locally).
set -euo pipefail
REPO="$(cd "$(dirname "$0")/.." && pwd)"
SITE="$1"
DEST="$SITE/wp-content/plugins/ticketchi"
rm -rf "$DEST"
mkdir -p "$DEST"
(cd "$REPO" && tar --exclude=.git --exclude=tests --exclude=node_modules -cf - .) | tar -xf - -C "$DEST"
cp "$REPO/tests/stubs/base-menu.php" "$DEST/inc/admin/abstract/base-menu.php"
rm -f "$DEST"/inc/admin/abstract/RTL_License_*.php
