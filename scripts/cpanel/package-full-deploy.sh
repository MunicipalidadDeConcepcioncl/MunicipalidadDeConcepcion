#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPOPATH="$(cd "${SCRIPT_DIR}/../.." && pwd)"

COMPOSE_FILE="${COMPOSE_FILE:-${REPOPATH}/docker-compose.yml}"
COMPOSE_PROJECT_DIR="${COMPOSE_PROJECT_DIR:-${REPOPATH}}"
WORDPRESS_SERVICE="${WORDPRESS_SERVICE:-wordpress}"
DB_SERVICE="${DB_SERVICE:-db}"
OUTPUT_DIR="${OUTPUT_DIR:-${REPOPATH}/deploy/full}"
TIMESTAMP="$(date +"%Y%m%d-%H%M%S")"
PACKAGE_FILENAME="${PACKAGE_FILENAME:-wordpress-full-${TIMESTAMP}.tar.gz}"

WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/wp-full-package.XXXXXX")"
trap 'rm -rf "${WORKDIR}"' EXIT

compose_cmd() {
  docker compose -f "${COMPOSE_FILE}" --project-directory "${COMPOSE_PROJECT_DIR}" "$@"
}

echo "INFO: Verifying services are running..."
compose_cmd ps --services --status running | grep -Fx "${WORDPRESS_SERVICE}" >/dev/null || {
  echo "ERROR: Service '${WORDPRESS_SERVICE}' is not running."
  exit 1
}
compose_cmd ps --services --status running | grep -Fx "${DB_SERVICE}" >/dev/null || {
  echo "ERROR: Service '${DB_SERVICE}' is not running."
  exit 1
}

echo "INFO: Exporting database from service '${DB_SERVICE}'..."
compose_cmd exec -T "${DB_SERVICE}" sh -lc \
  'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > "${WORKDIR}/database.sql"

echo "INFO: Exporting WordPress files from service '${WORDPRESS_SERVICE}'..."
/bin/mkdir -p "${WORKDIR}/wordpress"
compose_cmd exec -T "${WORDPRESS_SERVICE}" sh -lc \
  'tar -C /var/www/html -cf - .' | tar -C "${WORKDIR}/wordpress" -xf -

{
  echo "generated_at=${TIMESTAMP}"
  echo "git_commit=$(git -C "${REPOPATH}" rev-parse --short HEAD 2>/dev/null || echo unknown)"
  echo "compose_file=${COMPOSE_FILE}"
  echo "wordpress_service=${WORDPRESS_SERVICE}"
  echo "db_service=${DB_SERVICE}"
} > "${WORKDIR}/manifest.txt"

/bin/mkdir -p "${OUTPUT_DIR}"
tar -C "${WORKDIR}" -czf "${OUTPUT_DIR}/${PACKAGE_FILENAME}" wordpress database.sql manifest.txt

# Keep a stable name for cPanel automation.
/bin/cp "${OUTPUT_DIR}/${PACKAGE_FILENAME}" "${OUTPUT_DIR}/latest.tar.gz"

echo "INFO: Full deploy package created:"
echo " - ${OUTPUT_DIR}/${PACKAGE_FILENAME}"
echo " - ${OUTPUT_DIR}/latest.tar.gz"
