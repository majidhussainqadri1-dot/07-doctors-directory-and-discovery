# File 07 — Forty Review-and-Correction Rounds

**Repository branch:** `codex/file-07-complete-v1.0.0`  
**Runtime candidate:** `1.1.0`  
**Review date:** 06 August 2026  
**Method:** ہر round ایک الگ concern پر کیا گیا؛ جہاں نقص ملا، اسی round کے اختتام پر اصلاح ہوئی اور متعلقہ check دوبارہ چلایا گیا۔

## حتمی شمار

- **کل نظرِ ثانی:** 40
- **جن نظرِ ثانیوں میں نقائص ملے:** 15
- **جن نظرِ ثانیوں میں کوئی نقص نہیں ملا:** 25
- **تمام 15 defect rounds کی اصلاح:** مکمل
- **اصلاح کے بعد repository-level unresolved critical/high defects:** 0
- **External production gates:** Hostinger staging، حقیقی companion integrations، browser/device acceptance، backup/restore/rollback rehearsal اور Founder acceptance الگ لازمی evidence ہیں۔

## مکمل رجسٹر

| Round | دائرۂ نظرِ ثانی | ابتدائی نتیجہ | اصلاح/ثبوت |
|---:|---|---|---|
| 1 | Release/PR identity | **نقص ملا** | PR اور release evidence پرانا v1.0.0 ظاہر کر رہے تھے؛ v1.1.0 identity سے ہم آہنگ کیے گئے۔ |
| 2 | Root checksum integrity | **نقص ملا** | `CHECKSUMS.sha256` موجودہ source سے مختلف تھا؛ مکمل integrity manifest دوبارہ بنایا گیا۔ |
| 3 | Legacy workflow package path | **نقص ملا** | workflow `1.0.0.zip` مانگتا تھا جبکہ builder `1.1.0.zip` بناتا تھا؛ obsolete path ختم ہوا۔ |
| 4 | Workflow QA parity | **نقص ملا** | basic اور final workflows کے core gates مختلف تھے؛ دونوں harmonize کیے گئے۔ |
| 5 | Final workflow integrity coverage | **نقص ملا** | final workflow checksum coverage سے باہر تھا؛ اسے manifest میں شامل کیا گیا۔ |
| 6 | Source/evidence manifest | **نقص ملا** | hardening adapter، final workflow، 40-round test اور review evidence درج نہیں تھے؛ شامل کیے گئے۔ |
| 7 | Release hash source of truth | **نقص ملا** | ZIP hash workflow میں duplicate hard-coded تھا؛ canonical `RELEASE-CANDIDATE.sha256` نافذ ہوا۔ |
| 8 | CI action maintenance | **نقص ملا** | `checkout@v4` پر Node deprecation warning تھی؛ `checkout@v5` کیا گیا۔ |
| 9 | Legacy identity fail-closed semantics | **نقص ملا** | خالی suspension/risk values کو clear سمجھا جا رہا تھا؛ صرف explicit clear states منظور ہوئے۔ |
| 10 | Adult guardian compatibility | **نقص ملا** | explicit adult state بھی missing guardian metadata سے بلاک ہوسکتا تھا؛ adult کو guardian-not-required بنایا گیا۔ |
| 11 | Verification state normalization | **نقص ملا** | approved/active doctor states verified نہ بنتے تھے؛ verified/approved/active positive allowlist ہوئی۔ |
| 12 | Founder contract revalidation | **نقص ملا** | external Founder projection owner claims سے دوبارہ validate نہیں ہوتی تھی؛ institutional/public fail-closed recheck شامل ہوا۔ |
| 13 | Legal-hold privacy erasure | **نقص ملا** | held report rows eraser کو ایک ہی batch پر روک سکتی تھیں؛ held rows exclude اور remaining-unheld termination نافذ ہوئی۔ |
| 14 | Forty-round rate-limit evidence | **نقص ملا** | executable review gate rate-limit SQL کو غلط source file میں تلاش کر رہا تھا؛ canonical helper path پر درست کیا گیا۔ |
| 15 | Review register alignment | **نقص ملا** | انسانی review register اور executable control numbering میں عدم مطابقت تھی؛ chronological register اور post-correction gate کی حیثیت الگ واضح کی گئی۔ |
| 16 | Required runtime inventory | **نقص نہیں ملا** | تمام canonical runtime files موجود ہیں۔ |
| 17 | Namespace/text domain/package | **نقص نہیں ملا** | DDD namespace، text domain اور package folder ہم آہنگ ہیں۔ |
| 18 | Secret/artifact scan | **نقص نہیں ملا** | keys، env files یا واضح hard-coded secrets نہیں ملے۔ |
| 19 | PHP direct-access guards | **نقص نہیں ملا** | تمام PHP entry points محفوظ guards رکھتے ہیں۔ |
| 20 | Destructive SQL/uninstall | **نقص نہیں ملا** | `REPLACE INTO`، default `DROP TABLE` یا destructive uninstall نہیں۔ |
| 21 | Transactional schema/indexes | **نقص نہیں ملا** | InnoDB، uniqueness اور queue/idempotency indexes موجود ہیں۔ |
| 22 | Activation/repair locking | **نقص نہیں ملا** | token، expiry، compare-and-delete اور `finally` release موجود ہیں۔ |
| 23 | Resumable migration | **نقص نہیں ملا** | bounded batch، cursor اور scheduled continuation موجود ہیں۔ |
| 24 | Managed page lifecycle | **نقص نہیں ملا** | page map اور reversible/non-destructive page ownership موجود ہے۔ |
| 25 | Mandatory owner contracts | **نقص نہیں ملا** | Files 00/03/09 unavailable ہوں تو eligibility fail closed ہوتی ہے۔ |
| 26 | Eligibility matrix | **نقص نہیں ملا** | account، risk، age، guardian، verification، privacy اور destination checks مکمل ہیں۔ |
| 27 | Founder separation | **نقص نہیں ملا** | Founder ordinary/recent/all groups سے جدا ہے۔ |
| 28 | Featured governance | **نقص نہیں ملا** | label، reason، duration، approver، audit اور optimistic version موجود ہیں۔ |
| 29 | Recent/all ordering | **نقص نہیں ملا** | authoritative verification date، stable tie-breaker اور cursor pagination موجود ہیں۔ |
| 30 | Search/facets/transliteration | **نقص نہیں ملا** | تمام plan filters، aliases اور transliteration normalization موجود ہیں۔ |
| 31 | Cursor tamper resistance | **نقص نہیں ملا** | HMAC، filter binding اور tamper rejection tests کامیاب ہیں۔ |
| 32 | Opaque public identity | **نقص نہیں ملا** | persistent UUIDv4 public IDs اور internal-ID exclusion موجود ہے۔ |
| 33 | Same-origin/public DTO privacy | **نقص نہیں ملا** | destinations same-origin ہیں؛ doctor/attachment internal IDs public DTO سے خارج ہیں۔ |
| 34 | Saved references | **نقص نہیں ملا** | unique ownership key اور live eligibility recheck موجود ہے۔ |
| 35 | Reports/rate limiting/idempotency | **نقص نہیں ملا** | actor-scoped idempotency، bounded persistent limits اور safe validation موجود ہیں۔ |
| 36 | Moderation/audit transitions | **نقص نہیں ملا** | explicit state law، transactions، optimistic version اور audit موجود ہیں۔ |
| 37 | Events/cache freshness | **نقص نہیں ملا** | inbox dedupe، outbox retry/dead-letter، stale recovery اور invalidation موجود ہیں۔ |
| 38 | REST/SEO/security boundaries | **نقص نہیں ملا** | capability callbacks، signed intake، canonical/sitemap/noindex/no-store موجود ہیں۔ |
| 39 | Accessibility/RTL/responsive | **نقص نہیں ملا** | focus، 44px targets، RTL، reduced motion اور contrast controls موجود ہیں۔ |
| 40 | Safe Mode/operations/staging truth | **نقص نہیں ملا** | System Check، repair، redacted observability، rollback اور staging boundary واضح ہیں۔ |

## Automated enforcement

`tests/review-40.py` چالیس post-correction controls چلاتا ہے۔ یہ chronological defect register نہیں بلکہ final-state regression gate ہے؛ دونوں GitHub Actions workflows اسے لازماً چلاتے ہیں۔ `CHECKSUMS.sha256` source integrity، `RELEASE-CANDIDATE.sha256` deterministic ZIP identity اور final workflow clean-extract manifest verification نافذ کرتے ہیں۔

## صداقت کی حد

یہ 40 rounds repository/source/package سطح کی نظرِ ثانی ہیں۔ یہ Hostinger staging، حقیقی MySQL migration، exact Files 00/03/08/09/20/25/26 integration، real-role privacy journeys، browser/device/accessibility، backup restore، rollback drill یا Founder acceptance کا متبادل نہیں ہیں۔
