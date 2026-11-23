#!/usr/bin/env bash
# Bangun zip rilis Rangkui yang siap diunggah ke hosting (tanpa perlu Composer).
# Pemakaian: bash .github/scripts/build-release.sh v1.0.1
set -euo pipefail

VERSION="${1:-dev}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BUILD="$ROOT/build/rangkui"
DIST="$ROOT/dist"

rm -rf "$ROOT/build" && mkdir -p "$BUILD" "$DIST"

# Salin isi repositori yang dilacak git (tanpa berkas pengembangan).
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$BUILD"
rm -rf "$BUILD/.github" "$BUILD/tests" "$BUILD/phpunit.xml.dist" "$BUILD/.gitattributes"

# Dependensi produksi. MongoDB opsional, jadi ekstensinya tidak wajib saat build.
composer install --working-dir="$BUILD" --no-dev --optimize-autoloader --no-interaction --no-progress

printf '%s\n' "$VERSION" > "$BUILD/VERSION"

(cd "$ROOT/build" && zip -qr "$DIST/rangkui-${VERSION}.zip" rangkui)
(cd "$DIST" && sha256sum "rangkui-${VERSION}.zip" > "rangkui-${VERSION}.zip.sha256")
echo "Selesai: $DIST/rangkui-${VERSION}.zip"
