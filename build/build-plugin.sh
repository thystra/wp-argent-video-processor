#!/usr/bin/env bash
# /home/alan/src/wp-argent-video-processor/build/build-plugin.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"
SLUG='wp-argent-video-processor'
DIST_DIR="${ROOT_DIR}/dist"
STAGE_ROOT="$(mktemp -d)"
STAGE_DIR="${STAGE_ROOT}/${SLUG}"

cleanup() {
  rm -rf "${STAGE_ROOT}"
  rm -f \
    "${ROOT_DIR}/assets/vendor/hls.min.js" \
    "${ROOT_DIR}/assets/vendor/hls.LICENSE" \
    "${ROOT_DIR}/assets/vendor/hls.VERSION" \
    "${ROOT_DIR}/assets/vendor/hls.SHA256"
}
trap cleanup EXIT

if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
  echo "Usage: $0 X.Y.Z" >&2
  exit 2
fi

PLUGIN_VERSION="$(sed -n 's/^ \* Version: //p' "${ROOT_DIR}/wp-argent-video-processor.php" | head -n1)"
STABLE_TAG="$(sed -n 's/^Stable tag: //p' "${ROOT_DIR}/readme.txt" | head -n1)"
if [[ "${PLUGIN_VERSION}" != "${VERSION}" || "${STABLE_TAG}" != "${VERSION}" ]]; then
  echo "Plugin/readme version does not match ${VERSION}." >&2
  exit 1
fi

if [[ "${ARGENT_VIDEO_SKIP_HLS_FETCH:-0}" != '1' ]]; then
  if ! bash "${ROOT_DIR}/build/fetch-hls-js.sh"; then
    if [[ "${ARGENT_VIDEO_ALLOW_MISSING_HLS_JS:-0}" != '1' ]]; then
      echo 'Could not vendor hls.js; refusing to build a release package.' >&2
      exit 1
    fi
    echo 'WARNING: building without local hls.js; only native HLS and progressive fallback playback will be available.' >&2
  fi
elif [[ ! -s "${ROOT_DIR}/assets/vendor/hls.min.js" ]]; then
  if [[ "${ARGENT_VIDEO_ALLOW_MISSING_HLS_JS:-0}" != '1' ]]; then
    echo 'hls.js fetch was skipped and no local asset exists; refusing to build a release package.' >&2
    exit 1
  fi
  echo 'WARNING: hls.js fetch skipped; building without the local adaptive player.' >&2
fi

rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}" "${STAGE_DIR}"
rsync -a \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.gitignore' \
  --exclude='.gitattributes' \
  --exclude='AGENTS.md' \
  --exclude='AGENTS-PROFILE.md' \
  --exclude='build/' \
  --exclude='dist/' \
  --exclude='ops/' \
  --exclude='tests/' \
  --exclude='*.tar.gz' \
  --exclude='*.zip' \
  "${ROOT_DIR}/" "${STAGE_DIR}/"

find "${STAGE_DIR}" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
ZIP_NAME="${SLUG}-${VERSION}.zip"
(
  cd "${STAGE_ROOT}"
  zip -q -r "${DIST_DIR}/${ZIP_NAME}" "${SLUG}"
)

TOP_LEVEL_COUNT="$(unzip -Z1 "${DIST_DIR}/${ZIP_NAME}" | awk -F/ 'NF {print $1}' | sort -u | wc -l)"
TOP_LEVEL_NAME="$(unzip -Z1 "${DIST_DIR}/${ZIP_NAME}" | awk -F/ 'NF {print $1}' | sort -u)"
if [[ "${TOP_LEVEL_COUNT}" -ne 1 || "${TOP_LEVEL_NAME}" != "${SLUG}" ]]; then
  echo "Release ZIP does not contain exactly one ${SLUG}/ top-level directory." >&2
  exit 1
fi
for REQUIRED_VENDOR_FILE in \
  hls.min.js \
  hls.LICENSE \
  hls.VERSION \
  hls.SHA256
do
  if ! unzip -Z1 "${DIST_DIR}/${ZIP_NAME}" | grep -qx "${SLUG}/assets/vendor/${REQUIRED_VENDOR_FILE}"; then
    if [[ "${ARGENT_VIDEO_ALLOW_MISSING_HLS_JS:-0}" != '1' ]]; then
      printf 'Release ZIP is missing required vendored hls.js file: %s\n' "${REQUIRED_VENDOR_FILE}" >&2
      exit 1
    fi
  fi
done
(
  cd "${DIST_DIR}"
  sha256sum "${ZIP_NAME}" > SHA256SUMS
)
printf 'Built %s\n' "${DIST_DIR}/${ZIP_NAME}"
printf 'Checksums: %s\n' "${DIST_DIR}/SHA256SUMS"

# EOF: /home/alan/src/wp-argent-video-processor/build/build-plugin.sh
