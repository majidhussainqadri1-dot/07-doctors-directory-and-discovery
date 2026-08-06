#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(tr -d '\r\n' < "$ROOT/VERSION")"
BUILD="$ROOT/build"
STAGE="$BUILD/doctors-directory-and-discovery"
ZIP="$BUILD/07-doctors-directory-and-discovery-${VERSION}.zip"
rm -rf "$BUILD"
mkdir -p "$STAGE"
cp -a "$ROOT/doctors-directory/." "$STAGE/"
find "$STAGE" -type f -exec touch -t 202601010000 {} +
(
  cd "$BUILD"
  find doctors-directory-and-discovery -type f -print0 | sort -z | xargs -0 zip -X -q "$ZIP"
)
unzip -t "$ZIP" >/dev/null
sha256sum "$ZIP" > "$BUILD/RELEASE-CANDIDATE.sha256"
find "$STAGE" -type f -print0 | sort -z | xargs -0 sha256sum > "$BUILD/SOURCE-CHECKSUMS.sha256"
printf 'Built %s\n' "$ZIP"
cat "$BUILD/RELEASE-CANDIDATE.sha256"
