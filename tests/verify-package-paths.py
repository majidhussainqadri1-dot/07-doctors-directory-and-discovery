from pathlib import PurePosixPath
import re
import sys
import zipfile

if len(sys.argv) != 2:
    print('usage: verify-package-paths.py <zip>', file=sys.stderr)
    raise SystemExit(2)

archive = sys.argv[1]
with zipfile.ZipFile(archive) as zf:
    names = zf.namelist()
    if not names:
        print('FAIL: package is empty', file=sys.stderr)
        raise SystemExit(1)
    for raw in names:
        if '\\' in raw:
            print(f'FAIL: backslash path separator is forbidden: {raw!r}', file=sys.stderr)
            raise SystemExit(1)
        normalized = raw.replace('\\', '/')
        if normalized.startswith('/') or re.match(r'^[A-Za-z]:', normalized):
            print(f'FAIL: absolute package path is forbidden: {raw!r}', file=sys.stderr)
            raise SystemExit(1)
        path = PurePosixPath(normalized)
        if '..' in path.parts:
            print(f'FAIL: parent traversal component is forbidden: {raw!r}', file=sys.stderr)
            raise SystemExit(1)
        if any(part in ('', '.') for part in path.parts):
            print(f'FAIL: ambiguous package path component is forbidden: {raw!r}', file=sys.stderr)
            raise SystemExit(1)

print(f'PASS: {len(names)} ZIP members use relative traversal-safe POSIX paths')
