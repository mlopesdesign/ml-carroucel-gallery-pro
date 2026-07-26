#!/usr/bin/env bash
# Package build (Linux/CI). Produces dist/ml-carroucel-gallery-pro-vX.Y.Z.zip
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_DIR="$ROOT/ml-carousel-gallery-pro"
DIST_DIR="$ROOT/dist"
VERSION="${1:-}"
VERSION="${VERSION#v}"

if [ -z "$VERSION" ]; then
  echo "Usage: package.sh <version>" >&2
  exit 1
fi

if ! echo "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
  echo "[package] Versao invalida: '$VERSION'" >&2
  exit 1
fi

# Sync check
bash "$SCRIPT_DIR/sync-version.sh"

zip_name="ml-carroucel-gallery-pro-v${VERSION}.zip"
zip_path="$DIST_DIR/$zip_name"

mkdir -p "$DIST_DIR"
rm -f "$zip_path"

# Build ZIP with ml-carousel-gallery-pro/ as root
(cd "$ROOT" && zip -r "$zip_path" ml-carousel-gallery-pro \
  -x "*.git/*" "*.DS_Store" "*Thumbs.db" "*Desktop.ini" \
     "*.log" "*.swp" "*.swo" "*/node_modules/*" "*/vendor/*" \
     "*.zip")

sha=$(sha256sum "$zip_path" | cut -d' ' -f1)
size=$(stat -c%s "$zip_path")

echo "[package] OK"
echo "  Arquivo: $zip_name"
echo "  Tamanho: $size bytes"
echo "  SHA-256: $sha"
echo "  Caminho: $zip_path"