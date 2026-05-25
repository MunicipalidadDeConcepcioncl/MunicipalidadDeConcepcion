#!/usr/bin/env bash
set -euo pipefail

# This script runs from the cPanel-managed repository after a deploy trigger.
REPOPATH="$(pwd)"
DEPLOYPATH="${DEPLOYPATH:-}"

if [ -z "${DEPLOYPATH}" ]; then
  echo "ERROR: DEPLOYPATH is empty. Set it in .cpanel.yml"
  exit 1
fi

SOURCE_THEME="${REPOPATH}/wp-content/themes/sitio-cero/"
TARGET_THEME="${DEPLOYPATH}/wp-content/themes/sitio-cero/"
SOURCE_MU="${REPOPATH}/wp-content/mu-plugins/"
TARGET_MU="${DEPLOYPATH}/wp-content/mu-plugins/"

if [ ! -d "${SOURCE_THEME}" ]; then
  echo "ERROR: Source theme not found at ${SOURCE_THEME}"
  exit 1
fi

/bin/mkdir -p "${TARGET_THEME}"
/bin/mkdir -p "${TARGET_MU}"

if command -v rsync >/dev/null 2>&1; then
  RSYNC_BIN="$(command -v rsync)"
  "${RSYNC_BIN}" -av --delete --exclude=".DS_Store" "${SOURCE_THEME}" "${TARGET_THEME}"

  if [ -d "${SOURCE_MU}" ]; then
    "${RSYNC_BIN}" -av --delete --exclude=".DS_Store" "${SOURCE_MU}" "${TARGET_MU}"
  fi
else
  /usr/bin/find "${TARGET_THEME}" -mindepth 1 -maxdepth 1 -exec /bin/rm -rf {} +
  /bin/cp -R "${SOURCE_THEME}" "${TARGET_THEME}"

  if [ -d "${SOURCE_MU}" ]; then
    /usr/bin/find "${TARGET_MU}" -mindepth 1 -maxdepth 1 -exec /bin/rm -rf {} +
    /bin/cp -R "${SOURCE_MU}" "${TARGET_MU}"
  fi
fi
