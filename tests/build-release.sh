#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION=1.1.0
TOP=doctors-directory-and-discovery
BUILD="$ROOT/build"
STAGE="$BUILD/stage/$TOP"
ZIP="$BUILD/07-doctors-directory-and-discovery-$VERSION.zip"
rm -rf "$BUILD/stage" "$ZIP"
mkdir -p "$STAGE"
cp -a "$ROOT/doctors-directory/." "$STAGE/"
find "$STAGE" -type f -exec touch -t 202608060000 {} +
(
 cd "$STAGE"
 find . -type f ! -name MANIFEST.sha256 -print0 | sort -z | xargs -0 sha256sum > MANIFEST.sha256
 touch -t 202608060000 MANIFEST.sha256
)
mkdir -p "$BUILD"
(
 cd "$BUILD/stage"
 TZ=UTC find "$TOP" -type f -print | LC_ALL=C sort | zip -X -q "$ZIP" -@
)
sha256sum "$ZIP" > "$ZIP.sha256"
echo "$ZIP"
