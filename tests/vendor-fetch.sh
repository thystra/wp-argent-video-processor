#!/usr/bin/env bash
# /home/alan/src/wp-argent-video-processor/tests/vendor-fetch.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_ROOT="$(mktemp -d)"
cleanup() { rm -rf "${TMP_ROOT}"; }
trap cleanup EXIT

PACKAGE_DIR="${TMP_ROOT}/package"
TARGET_DIR="${TMP_ROOT}/target"
mkdir -p "${PACKAGE_DIR}/dist" "${TARGET_DIR}"
cat > "${PACKAGE_DIR}/package.json" <<'JSON'
{"name":"hls.js","version":"1.6.16"}
JSON
cp "${ROOT_DIR}/assets/vendor/hls.LICENSE" "${PACKAGE_DIR}/LICENSE"
cat > "${PACKAGE_DIR}/dist/hls.min.js" <<'JS'
'use strict';module.exports=function Hls(){};module.exports.version='1.6.16';
JS
python3 - "${PACKAGE_DIR}/dist/hls.min.js" <<'PY'
from pathlib import Path
import sys
path = Path(sys.argv[1])
with path.open('a', encoding='utf-8') as handle:
    handle.write('/*' + ('x' * 110000) + '*/\n')
PY

tar -czf "${TMP_ROOT}/hls.js-1.6.16.tgz" -C "${TMP_ROOT}" package
ARGENT_VIDEO_HLS_PACKAGE_FILE="${TMP_ROOT}/hls.js-1.6.16.tgz" \
ARGENT_VIDEO_HLS_TARGET_DIR="${TARGET_DIR}" \
  bash "${ROOT_DIR}/build/fetch-hls-js.sh"

test -s "${TARGET_DIR}/hls.min.js"
test -s "${TARGET_DIR}/hls.LICENSE"
test "$(cat "${TARGET_DIR}/hls.VERSION")" = '1.6.16'
(cd "${TARGET_DIR}" && sha256sum --check hls.SHA256)
node - "${TARGET_DIR}/hls.min.js" <<'NODE'
const Hls = require(process.argv[2]);
if (Hls.version !== '1.6.16') {
  process.exit(1);
}
NODE

printf 'Vendor fetch regression test passed.\n'

# EOF: /home/alan/src/wp-argent-video-processor/tests/vendor-fetch.sh
