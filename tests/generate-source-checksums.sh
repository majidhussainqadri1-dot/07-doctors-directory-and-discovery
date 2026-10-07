#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
export LC_ALL=C
while IFS= read -r -d '' path; do
  case "$path" in
    CHECKSUMS.sha256|build/*) continue ;;
  esac
  hash="$(sha256sum "$path" | awk '{print $1}')"
  printf '%s  ./%s\n' "$hash" "$path"
done < <(git ls-files -z | sort -z)
