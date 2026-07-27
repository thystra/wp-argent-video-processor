#!/usr/bin/env bash
# /home/alan/src/wp-argent-video-processor/build/fetch-hls-js.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# Pin an exact stable release. Do not use npm major ranges or canary builds.
# npm verifies the registry package integrity before extraction; this script then
# verifies the package identity and the runtime Hls.version value.
HLS_VERSION='1.6.16'
TARGET_DIR="${ARGENT_VIDEO_HLS_TARGET_DIR:-${ROOT_DIR}/assets/vendor}"
TARGET_FILE="${TARGET_DIR}/hls.min.js"
TARGET_LICENSE="${TARGET_DIR}/hls.LICENSE"
TARGET_VERSION="${TARGET_DIR}/hls.VERSION"
TARGET_HASH="${TARGET_DIR}/hls.SHA256"
PACKAGE_OVERRIDE="${ARGENT_VIDEO_HLS_PACKAGE_FILE:-}"
NPM_REGISTRY='https://registry.npmjs.org/'

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    printf 'Required build command is unavailable: %s\n' "$1" >&2
    exit 1
  fi
}

validate_runtime_asset() {
  local asset="$1"
  local license="$2"

  if [[ ! -s "${asset}" ]] || [[ "$(wc -c < "${asset}")" -le 100000 ]]; then
    echo 'Vendored hls.js asset is missing or unexpectedly small.' >&2
    return 1
  fi
  if [[ ! -s "${license}" ]]; then
    echo 'Vendored hls.js license is missing.' >&2
    return 1
  fi

  node --check "${asset}" >/dev/null
  node - "${asset}" "${HLS_VERSION}" <<'NODE'
'use strict';
const assetPath = process.argv[2];
const expected = process.argv[3];
let exported;
try {
  exported = require(assetPath);
} catch (error) {
  console.error(`Unable to load vendored hls.js asset: ${error.message}`);
  process.exit(1);
}
const Hls = exported && exported.default ? exported.default : exported;
const actual = Hls && Hls.version;
if (actual !== expected) {
  console.error(`Vendored hls.js runtime version mismatch: expected ${expected}, found ${String(actual)}`);
  process.exit(1);
}
NODE
}

require_command node
require_command npm
require_command tar
require_command sha256sum
mkdir -p "${TARGET_DIR}"

if validate_runtime_asset "${TARGET_FILE}" "${TARGET_LICENSE}" 2>/dev/null; then
  printf '%s\n' "${HLS_VERSION}" > "${TARGET_VERSION}"
  (cd "${TARGET_DIR}" && sha256sum hls.min.js > "$(basename "${TARGET_HASH}")")
  printf 'Using verified existing hls.js %s asset: %s\n' "${HLS_VERSION}" "${TARGET_FILE}"
  exit 0
fi

TMP_ROOT="$(mktemp -d)"
cleanup() { rm -rf "${TMP_ROOT}"; }
trap cleanup EXIT

if [[ -n "${PACKAGE_OVERRIDE}" ]]; then
  if [[ ! -f "${PACKAGE_OVERRIDE}" ]]; then
    printf 'HLS package override does not exist: %s\n' "${PACKAGE_OVERRIDE}" >&2
    exit 1
  fi
  PACKAGE_FILE="${TMP_ROOT}/hls.js-${HLS_VERSION}.tgz"
  cp -- "${PACKAGE_OVERRIDE}" "${PACKAGE_FILE}"
else
  PACK_OUTPUT="$(
    npm pack "hls.js@${HLS_VERSION}" \
      --silent \
      --ignore-scripts \
      --registry="${NPM_REGISTRY}" \
      --pack-destination "${TMP_ROOT}"
  )"
  PACK_NAME="$(printf '%s\n' "${PACK_OUTPUT}" | tail -n1 | tr -d '\r')"
  if [[ -z "${PACK_NAME}" ]]; then
    echo 'npm pack did not report a package filename.' >&2
    exit 1
  fi
  if [[ "${PACK_NAME}" = /* ]]; then
    PACKAGE_FILE="${PACK_NAME}"
  else
    PACKAGE_FILE="${TMP_ROOT}/${PACK_NAME}"
  fi
fi

if [[ ! -s "${PACKAGE_FILE}" ]]; then
  echo 'Downloaded hls.js npm package is missing or empty.' >&2
  exit 1
fi

EXTRACT_DIR="${TMP_ROOT}/extract"
mkdir -p "${EXTRACT_DIR}"
tar -xzf "${PACKAGE_FILE}" -C "${EXTRACT_DIR}"
PACKAGE_DIR="${EXTRACT_DIR}/package"
PACKAGE_JSON="${PACKAGE_DIR}/package.json"
PACKAGE_ASSET="${PACKAGE_DIR}/dist/hls.min.js"
PACKAGE_LICENSE="${PACKAGE_DIR}/LICENSE"

node - "${PACKAGE_JSON}" "${HLS_VERSION}" <<'NODE'
'use strict';
const fs = require('fs');
const packagePath = process.argv[2];
const expected = process.argv[3];
let metadata;
try {
  metadata = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
} catch (error) {
  console.error(`Unable to read hls.js package metadata: ${error.message}`);
  process.exit(1);
}
if (metadata.name !== 'hls.js' || metadata.version !== expected) {
  console.error(`Unexpected npm package identity: ${String(metadata.name)}@${String(metadata.version)}`);
  process.exit(1);
}
NODE

TMP_ASSET="${TMP_ROOT}/hls.min.js"
TMP_LICENSE="${TMP_ROOT}/hls.LICENSE"
cp -- "${PACKAGE_ASSET}" "${TMP_ASSET}"
cp -- "${PACKAGE_LICENSE}" "${TMP_LICENSE}"
validate_runtime_asset "${TMP_ASSET}" "${TMP_LICENSE}"

if [[ -s "${TARGET_LICENSE}" ]] && ! cmp -s "${TMP_LICENSE}" "${TARGET_LICENSE}"; then
  echo 'Pinned hls.js package license differs from the reviewed repository copy.' >&2
  exit 1
fi

install -m 0644 "${TMP_ASSET}" "${TARGET_FILE}"
if [[ ! -s "${TARGET_LICENSE}" ]]; then
  install -m 0644 "${TMP_LICENSE}" "${TARGET_LICENSE}"
fi
printf '%s\n' "${HLS_VERSION}" > "${TARGET_VERSION}"
(cd "${TARGET_DIR}" && sha256sum hls.min.js > "$(basename "${TARGET_HASH}")")

validate_runtime_asset "${TARGET_FILE}" "${TARGET_LICENSE}"
printf 'Fetched and verified hls.js %s from the npm registry: %s\n' "${HLS_VERSION}" "${TARGET_FILE}"

# EOF: /home/alan/src/wp-argent-video-processor/build/fetch-hls-js.sh
