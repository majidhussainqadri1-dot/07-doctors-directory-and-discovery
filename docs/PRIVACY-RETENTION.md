# Privacy and Retention

## Public minimization
Only public-consented professional directory fields are indexed. Identity documents, guardian evidence, private contact data, patient data, messages, risk notes and internal moderation evidence are excluded.

## Owned records
- Projection: retained while eligible plus a short operational tombstone only where required.
- Saved references: account lifetime or user removal.
- Reports/audit: policy-defined moderation retention; legal holds prevent destructive erasure.
- Rate limits/metrics/health: bounded operational retention and hashed/minimized identifiers.

## Rights
WordPress privacy exporter and eraser cover File 07 saves, listing reports and owned consent metadata. Erasure removes account-owned references and anonymizes report identity/details when no legal/safety hold applies. Doctor deletion removes the projection and preserves only the minimum pseudonymous audit relation required by policy.

Backups must honor expiry and deletion propagation according to the platform backup policy; restoration must reapply deletion/tombstone ledgers before public reindex.
