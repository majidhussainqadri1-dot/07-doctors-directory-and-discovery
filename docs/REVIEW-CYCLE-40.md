# File 07 — Forty Review-and-Correction Rounds

**Repository branch:** `codex/file-07-complete-v1.0.0`  
**Runtime candidate:** `1.1.0`  
**Review date:** 06 August 2026  
**Method:** ہر round کو الگ concern کے طور پر جانچا گیا؛ جہاں نقص ملا، اسی round کے اختتام پر اصلاح کی گئی، پھر متعلقہ check دوبارہ چلایا گیا۔

## حتمی شمار

- **کل نظرِ ثانی:** 40
- **جن نظرِ ثانیوں میں نقائص ملے:** 13
- **جن نظرِ ثانیوں میں کوئی نقص نہیں ملا:** 27
- **اصلاح کے بعد معلوم unresolved critical/high defects:** 0
- **External production gates:** Hostinger staging، real companion integrations، browser/device acceptance، backup/restore/rollback rehearsal اور Founder acceptance بدستور الگ لازمی evidence ہیں۔

## مکمل رجسٹر

| Round | دائرۂ نظرِ ثانی | ابتدائی نتیجہ | اصلاح/ثبوت |
|---:|---|---|---|
| 1 | Release/PR identity | **نقص ملا** | PR title/body اور evidence اب بھی v1.0.0/پرانے commit کو ظاہر کر رہے تھے؛ v1.1.0 current head/evidence سے ہم آہنگ کیا گیا۔ |
| 2 | Root checksum integrity | **نقص ملا** | GitHub Actions میں CHECKSUMS.sha256 mismatch تھا؛ مکمل checksum manifest دوبارہ بنایا گیا۔ |
| 3 | Legacy workflow package path | **نقص ملا** | بنیادی workflow 1.0.0 ZIP تلاش کر رہا تھا جبکہ builder 1.1.0 بناتا ہے؛ obsolete path ختم کیا گیا۔ |
| 4 | Workflow coverage parity | **نقص ملا** | دو workflows کے QA gates مختلف تھے؛ دونوں میں ایک ہی core tests اور 40-round gate لازم کیا گیا۔ |
| 5 | Final workflow integrity coverage | **نقص ملا** | final-quality-gates.yml root checksum سے باہر تھا؛ اسے integrity manifest میں شامل کیا گیا۔ |
| 6 | Source manifest completeness | **نقص ملا** | final workflow، release evidence، hardening adapter اور 40-round artifacts manifest میں درج نہیں تھے؛ شامل کیے گئے۔ |
| 7 | Release hash source of truth | **نقص ملا** | workflow میں ZIP hash hard-coded duplicate تھا؛ canonical RELEASE-CANDIDATE.sha256 سے validation کی گئی۔ |
| 8 | CI action maintenance | **نقص ملا** | checkout@v4 پر Node 20 deprecation warning آ رہی تھی؛ checkout@v5 کیا گیا۔ |
| 9 | Legacy identity fail-closed semantics | **نقص ملا** | خالی suspension/risk metadata کو clear سمجھا جا رہا تھا؛ صرف explicit positive clear states قبول کیے گئے۔ |
| 10 | Adult guardian compatibility | **نقص ملا** | explicit adult age state کے باوجود خالی guardian field account کو بلاک کرتی تھی؛ adult کو guardian-not-required سمجھا گیا۔ |
| 11 | Verification positive-state compatibility | **نقص ملا** | approved/active legacy doctor states verified نہ بنتے تھے؛ verified/approved/active کو یکساں positive allowlist کیا گیا۔ |
| 12 | Founder contract revalidation | **نقص ملا** | external Founder projection institutional/public owner claims سے دوبارہ validate نہیں ہوتی تھی؛ fail-closed revalidation شامل کی گئی۔ |
| 13 | Privacy erasure legal-hold termination | **نقص ملا** | retention_hold=1 والی پہلی 50 rows eraser کو مسلسل انہی rows پر روک سکتی تھیں؛ held rows exclude کرکے remaining-unheld completion logic نافذ کیا گیا۔ |
| 14 | Required runtime file inventory | **نقص نہیں ملا** | تمام canonical runtime files موجود اور manifestable ہیں۔ |
| 15 | Canonical namespace/text domain/package | **نقص نہیں ملا** | DDD_ namespace، text domain اور package folder ہم آہنگ ہیں۔ |
| 16 | Secret/artifact scan | **نقص نہیں ملا** | keys، env files یا واضح hard-coded secrets نہیں ملے۔ |
| 17 | PHP direct-access guards | **نقص نہیں ملا** | تمام PHP entry points ABSPATH/WP_UNINSTALL guard رکھتے ہیں۔ |
| 18 | Destructive SQL/uninstall | **نقص نہیں ملا** | REPLACE INTO، DROP TABLE اور destructive default uninstall موجود نہیں۔ |
| 19 | Transactional schema and indexes | **نقص نہیں ملا** | InnoDB، unique identifiers، queue/idempotency indexes موجود ہیں۔ |
| 20 | Activation/repair locking | **نقص نہیں ملا** | token، expiry، compare-and-delete اور finally release موجود ہیں۔ |
| 21 | Resumable migration | **نقص نہیں ملا** | bounded batch، cursor اور scheduled continuation موجود ہیں۔ |
| 22 | Managed page lifecycle | **نقص نہیں ملا** | page map اور reversible/non-destructive ownership موجود ہے۔ |
| 23 | Mandatory owner contracts | **نقص نہیں ملا** | Files 00/03/09 unavailable ہوں تو eligibility fail closed ہوتی ہے۔ |
| 24 | Eligibility state matrix | **نقص نہیں ملا** | account، suspension، risk، age، guardian، verification، privacy اور destination checks مکمل ہیں۔ |
| 25 | Founder separation | **نقص نہیں ملا** | Founder ordinary/recent/all groups سے جدا ہے۔ |
| 26 | Featured governance | **نقص نہیں ملا** | label، reason، start/end، approver، audit اور optimistic version موجود ہیں۔ |
| 27 | Recently verified ordering | **نقص نہیں ملا** | authoritative verified_at اور stable tie-breaker موجود ہیں۔ |
| 28 | All-doctors pagination | **نقص نہیں ملا** | stable signed cursor اور duplicate suppression موجود ہے۔ |
| 29 | Search/facets/transliteration | **نقص نہیں ملا** | تمام plan filters، aliases اور transliteration normalization موجود ہیں۔ |
| 30 | Cursor tamper resistance | **نقص نہیں ملا** | HMAC، filter binding اور tamper rejection tests موجود ہیں۔ |
| 31 | Opaque public identity | **نقص نہیں ملا** | persistent UUIDv4 public IDs اور internal ID exclusion موجود ہے۔ |
| 32 | Same-origin destinations | **نقص نہیں ملا** | profile/clinic/appointment URLs same-origin gate سے گزرتی ہیں۔ |
| 33 | Public DTO minimization | **نقص نہیں ملا** | doctor_id اور attachment ID public DTO میں نہیں نکلتے۔ |
| 34 | Saved references | **نقص نہیں ملا** | unique ownership key اور live eligibility recheck موجود ہے۔ |
| 35 | Reports/rate limiting/idempotency | **نقص نہیں ملا** | actor-scoped idempotency، persistent bounded rate limits اور safe validation موجود ہیں۔ |
| 36 | Moderation/audit transitions | **نقص نہیں ملا** | explicit state law، transaction، optimistic version اور audit موجود ہیں۔ |
| 37 | Events and cache freshness | **نقص نہیں ملا** | inbox dedupe/replay protection، outbox claim/retry/dead-letter، stale recovery اور cache invalidation موجود ہیں۔ |
| 38 | REST/SEO/security boundaries | **نقص نہیں ملا** | capability callbacks، signed event intake، canonical/sitemap/noindex/no-store موجود ہیں۔ |
| 39 | Accessibility/RTL/responsive | **نقص نہیں ملا** | focus-visible، 44px targets، RTL logical layout، reduced motion اور contrast tests موجود ہیں۔ |
| 40 | Safe Mode/operations/staging truth | **نقص نہیں ملا** | System Check، repair، redacted observability، rollback اور Hostinger staging boundary واضح ہیں۔ |

## Automated enforcement

`tests/review-40.py` انہی 40 post-correction controls کو executable gate بناتا ہے۔ دونوں GitHub Actions workflows اسے چلاتے ہیں۔ `CHECKSUMS.sha256` source integrity، `RELEASE-CANDIDATE.sha256` deterministic ZIP identity، اور final workflow clean-extract manifest verification نافذ کرتے ہیں۔

## صداقت کی حد

یہ 40 rounds repository/source/package سطح کی نظرِ ثانی ہیں۔ یہ Hostinger staging، حقیقی MySQL migration، exact Files 00/03/08/09/20/25/26 integration، real-role privacy journeys، browser/device/accessibility، backup restore، rollback drill یا Founder acceptance کا متبادل نہیں ہیں۔
