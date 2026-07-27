#!/usr/bin/env bash
# /home/alan/src/wp-argent-video-processor/build/fetch-hls-js.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# Pin an exact stable release. Do not use npm/jsDelivr major ranges or canary builds.
# Specific 1.7.0 alpha-canary package versions were reported as malicious in 2026.
HLS_VERSION='1.6.16'
TARGET_DIR="${ROOT_DIR}/assets/vendor"
TARGET_FILE="${TARGET_DIR}/hls.min.js"
URL="https://cdn.jsdelivr.net/npm/hls.js@${HLS_VERSION}/dist/hls.min.js"

mkdir -p "${TARGET_DIR}"
if [[ -s "${TARGET_FILE}" ]] && [[ "$(wc -c < "${TARGET_FILE}")" -gt 100000 ]]; then
  printf 'Using existing hls.js asset: %s\n' "${TARGET_FILE}"
  exit 0
fi

TMP_FILE="$(mktemp "${TARGET_DIR}/hls.min.js.tmp.XXXXXX")"
cleanup() { rm -f "${TMP_FILE}"; }
trap cleanup EXIT

curl --fail --location --silent --show-error \
  --connect-timeout 10 --max-time 60 --retry 2 \
  --proto '=https' --tlsv1.2 \
  "${URL}" \
  --output "${TMP_FILE}"

if [[ "$(wc -c < "${TMP_FILE}")" -le 100000 ]]; then
  echo 'Downloaded hls.js asset is unexpectedly small.' >&2
  exit 1
fi
if ! grep -Fq "hls.js v${HLS_VERSION}" "${TMP_FILE}"; then
  echo 'Downloaded hls.js asset does not contain the expected pinned-version marker.' >&2
  exit 1
fi

mv "${TMP_FILE}" "${TARGET_FILE}"
chmod 0644 "${TARGET_FILE}"
printf 'Fetched hls.js %s to %s\n' "${HLS_VERSION}" "${TARGET_FILE}"

# EOF: /home/alan/src/wp-argent-video-processor/build/fetch-hls-js.sh
