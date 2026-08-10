# Dependency and Supply-Chain Inventory

## Runtime dependencies
- WordPress core APIs only.
- PHP extensions/functions used by WordPress/PHP baseline; no Composer packages.
- Browser JavaScript without external npm runtime libraries.

## Build/test tools
- PHP CLI
- Node.js syntax checker
- Python 3 standard library
- POSIX shell, `zip`, `unzip`, `sha256sum`, `cmp`
- GitHub Actions `actions/checkout@v4`

No bundled third-party binary, remote script, tracking SDK, analytics SDK or payment library is included.
