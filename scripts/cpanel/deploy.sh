#!/usr/bin/env bash
set -euo pipefail

# This script runs from the cPanel-managed repository after a deploy trigger.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPOPATH="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DEPLOYPATH="${DEPLOYPATH:-}"
FULL_DEPLOY_PACKAGE="${FULL_DEPLOY_PACKAGE:-}"
IMPORT_DB_ON_DEPLOY="${IMPORT_DB_ON_DEPLOY:-0}"

CPANEL_DB_HOST="${CPANEL_DB_HOST:-localhost}"
CPANEL_DB_PORT="${CPANEL_DB_PORT:-3306}"
CPANEL_DB_NAME="${CPANEL_DB_NAME:-}"
CPANEL_DB_USER="${CPANEL_DB_USER:-}"
CPANEL_DB_PASSWORD="${CPANEL_DB_PASSWORD:-}"

escape_php_single() {
  printf '%s' "$1" | sed "s/['\\\\]/\\\\&/g"
}

configure_wp_config() {
  local config_path tmp_file db_name_esc db_user_esc db_password_esc db_host_esc
  config_path="${DEPLOYPATH}/wp-config.php"

  if [ ! -f "${config_path}" ]; then
    echo "INFO: wp-config.php not found in deploy path, skipping config update."
    return 0
  fi

  if [ -z "${CPANEL_DB_NAME}" ] || [ -z "${CPANEL_DB_USER}" ] || [ -z "${CPANEL_DB_PASSWORD}" ]; then
    echo "INFO: Missing CPANEL_DB_* values, skipping wp-config.php DB fallback update."
    return 0
  fi

  db_name_esc="$(escape_php_single "${CPANEL_DB_NAME}")"
  db_user_esc="$(escape_php_single "${CPANEL_DB_USER}")"
  db_password_esc="$(escape_php_single "${CPANEL_DB_PASSWORD}")"
  db_host_esc="$(escape_php_single "${CPANEL_DB_HOST}")"

  tmp_file="$(mktemp "/tmp/wp-config.XXXXXX")"

  while IFS= read -r line; do
    case "${line}" in
      *"define( 'DB_NAME', getenv_docker('WORDPRESS_DB_NAME'"*)
        echo "define( 'DB_NAME', getenv_docker('WORDPRESS_DB_NAME', '${db_name_esc}') );" >> "${tmp_file}"
        ;;
      *"define( 'DB_USER', getenv_docker('WORDPRESS_DB_USER'"*)
        echo "define( 'DB_USER', getenv_docker('WORDPRESS_DB_USER', '${db_user_esc}') );" >> "${tmp_file}"
        ;;
      *"define( 'DB_PASSWORD', getenv_docker('WORDPRESS_DB_PASSWORD'"*)
        echo "define( 'DB_PASSWORD', getenv_docker('WORDPRESS_DB_PASSWORD', '${db_password_esc}') );" >> "${tmp_file}"
        ;;
      *"define( 'DB_HOST', getenv_docker('WORDPRESS_DB_HOST'"*)
        echo "define( 'DB_HOST', getenv_docker('WORDPRESS_DB_HOST', '${db_host_esc}') );" >> "${tmp_file}"
        ;;
      *)
        echo "${line}" >> "${tmp_file}"
        ;;
    esac
  done < "${config_path}"

  /bin/mv "${tmp_file}" "${config_path}"
  echo "INFO: wp-config.php updated with cPanel DB fallback values."
}

if [ -z "${DEPLOYPATH}" ]; then
  echo "ERROR: DEPLOYPATH is empty. Set it in .cpanel.yml"
  exit 1
fi

deploy_custom_content_only() {
  local source_theme target_theme source_mu target_mu
  source_theme="${REPOPATH}/wp-content/themes/sitio-cero/"
  target_theme="${DEPLOYPATH}/wp-content/themes/sitio-cero/"
  source_mu="${REPOPATH}/wp-content/mu-plugins/"
  target_mu="${DEPLOYPATH}/wp-content/mu-plugins/"

  if [ ! -d "${source_theme}" ]; then
    echo "ERROR: Source theme not found at ${source_theme}"
    exit 1
  fi

  /bin/mkdir -p "${target_theme}"
  /bin/mkdir -p "${target_mu}"

  if command -v rsync >/dev/null 2>&1; then
    local rsync_bin
    rsync_bin="$(command -v rsync)"
    "${rsync_bin}" -av --delete --exclude=".DS_Store" "${source_theme}" "${target_theme}"

    if [ -d "${source_mu}" ]; then
      "${rsync_bin}" -av --delete --exclude=".DS_Store" "${source_mu}" "${target_mu}"
    fi
  else
    /usr/bin/find "${target_theme}" -mindepth 1 -maxdepth 1 -exec /bin/rm -rf {} +
    /bin/cp -R "${source_theme}" "${target_theme}"

    if [ -d "${source_mu}" ]; then
      /usr/bin/find "${target_mu}" -mindepth 1 -maxdepth 1 -exec /bin/rm -rf {} +
      /bin/cp -R "${source_mu}" "${target_mu}"
    fi
  fi
}

import_database() {
  local sql_file
  sql_file="$1"

  if [ "${IMPORT_DB_ON_DEPLOY}" != "1" ]; then
    echo "INFO: Database import disabled (IMPORT_DB_ON_DEPLOY=${IMPORT_DB_ON_DEPLOY})."
    return 0
  fi

  if [ ! -s "${sql_file}" ]; then
    echo "ERROR: SQL file not found or empty: ${sql_file}"
    exit 1
  fi

  if [ -z "${CPANEL_DB_NAME}" ] || [ -z "${CPANEL_DB_USER}" ] || [ -z "${CPANEL_DB_PASSWORD}" ]; then
    echo "ERROR: Missing DB credentials. Set CPANEL_DB_NAME, CPANEL_DB_USER and CPANEL_DB_PASSWORD."
    exit 1
  fi

  if ! command -v mysql >/dev/null 2>&1; then
    echo "ERROR: mysql client not found in cPanel environment."
    exit 1
  fi

  echo "INFO: Importing database into ${CPANEL_DB_NAME}..."
  MYSQL_PWD="${CPANEL_DB_PASSWORD}" mysql \
    -h "${CPANEL_DB_HOST}" \
    -P "${CPANEL_DB_PORT}" \
    -u "${CPANEL_DB_USER}" \
    "${CPANEL_DB_NAME}" < "${sql_file}"
}

deploy_full_wordpress() {
  local package_path workdir source_wp db_sql
  package_path="${FULL_DEPLOY_PACKAGE}"

  if [ "${package_path#/}" = "${package_path}" ]; then
    package_path="${REPOPATH}/${package_path}"
  fi

  if [ ! -f "${package_path}" ]; then
    echo "ERROR: FULL_DEPLOY_PACKAGE not found: ${package_path}"
    exit 1
  fi

  workdir="$(mktemp -d "/tmp/wp-full-deploy.XXXXXX")"
  trap '[ -n "${workdir:-}" ] && rm -rf "${workdir}"' EXIT

  tar -xzf "${package_path}" -C "${workdir}"

  source_wp="${workdir}/wordpress/"
  db_sql="${workdir}/database.sql"

  if [ ! -d "${source_wp}" ]; then
    echo "ERROR: Full package does not contain wordpress/ directory."
    exit 1
  fi

  /bin/mkdir -p "${DEPLOYPATH}"

  if command -v rsync >/dev/null 2>&1; then
    local rsync_bin
    rsync_bin="$(command -v rsync)"
    "${rsync_bin}" -av --delete --exclude=".DS_Store" "${source_wp}" "${DEPLOYPATH}/"
  else
    /usr/bin/find "${DEPLOYPATH}" -mindepth 1 -maxdepth 1 -exec /bin/rm -rf {} +
    /bin/cp -R "${source_wp}" "${DEPLOYPATH}/"
  fi

  configure_wp_config
  import_database "${db_sql}"
}

if [ -n "${FULL_DEPLOY_PACKAGE}" ]; then
  echo "INFO: Running full WordPress deploy using package ${FULL_DEPLOY_PACKAGE}"
  deploy_full_wordpress
else
  echo "INFO: Running custom-content deploy (theme + mu-plugins)."
  deploy_custom_content_only
fi
