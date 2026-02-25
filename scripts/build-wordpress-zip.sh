#!/usr/bin/env bash
set -euo pipefail

SLUG="enkronos-publisher-for-growthpilot"
MAIN_FILE="${SLUG}.php"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

OUTPUT_PATH="${1:-/tmp/${SLUG}.zip}"
if [[ -d "${OUTPUT_PATH}" ]]; then
  OUTPUT_PATH="${OUTPUT_PATH%/}/${SLUG}.zip"
fi

if [[ ! -f "${REPO_ROOT}/${MAIN_FILE}" ]]; then
  echo "Missing main plugin file: ${MAIN_FILE}" >&2
  exit 1
fi

if [[ ! -f "${REPO_ROOT}/readme.txt" ]]; then
  echo "Missing readme.txt" >&2
  exit 1
fi

if [[ ! -d "${REPO_ROOT}/includes" || ! -d "${REPO_ROOT}/assets" ]]; then
  echo "Missing required directories: includes/ and/or assets/" >&2
  exit 1
fi

STAGE_ROOT="$(mktemp -d)"
trap 'rm -rf "${STAGE_ROOT}"' EXIT

PACKAGE_DIR="${STAGE_ROOT}/${SLUG}"
mkdir -p "${PACKAGE_DIR}"

cp "${REPO_ROOT}/${MAIN_FILE}" "${PACKAGE_DIR}/"
cp "${REPO_ROOT}/readme.txt" "${PACKAGE_DIR}/"
cp -R "${REPO_ROOT}/includes" "${PACKAGE_DIR}/"
cp -R "${REPO_ROOT}/assets" "${PACKAGE_DIR}/"

if [[ -f "${REPO_ROOT}/LICENSE" ]]; then
  cp "${REPO_ROOT}/LICENSE" "${PACKAGE_DIR}/"
fi

mkdir -p "$(dirname "${OUTPUT_PATH}")"
rm -f "${OUTPUT_PATH}"

(
  cd "${STAGE_ROOT}"
  zip -rq "${OUTPUT_PATH}" "${SLUG}"
)

echo "Created ${OUTPUT_PATH}"
