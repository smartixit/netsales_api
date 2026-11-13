# NetSales — Current Project State & Authorization Snapshot

**Last Updated Date:** 2026-11-13  
**Active Working Tree Status:** Operational Reports Provider Registry, Wallet Ledger Snapshots, and Official Document Snapshot Integration — source implementation in progress; final validation deferred  
**Canonical Repository:** `smartixit/netsales_api`  
**Client Repository:** `smartixit/netsales`  

---

## 1. Milestone & Authorization Tracking

| Milestone / Task | Status | Authorization Boundary |
|---|---|---|
| **Milestone 1:** Product Discovery & Blueprint | `ACCEPTED AS WORKING BASELINE` | Accepted with known limitations for forward progress. |
| **Governance Foundation:** Persistent Memory & Rules | `COMPLETED & VERIFIED` | User approved; full invariant rules, skills, and doc suite active. |
| **Milestone 2:** Clean Flutter Foundation, GetX Architecture, Workspace Isolation & Adaptive Navigation | `ACCEPTED AS WORKING BASELINE` | Implemented, tested, and reconciled. Client routing only; server remains sole security boundary. |
| **Milestone 3:** Identity & Access Foundation | `COMPLETED WITH LIMITATIONS` | Completed and authoritatively verified. Hashing invariant enforced at rest without double hashing; Registered Device Binding enforced; route-level tenant and Network-to-Network isolation proven; absolute session expiration verified. |
| **Milestone 4:** Commercial Entities — Networks, POS, Relationships | `COMPLETED & ENHANCED WITH SECURE QR LINKING` | Networks, POS, commercial relationships, and secure QR-based cryptographic linking challenge workflow implemented across Laravel and Flutter. Architectural compliance strictly enforced (0 StatefulWidget, 0 direct Eloquent in services, Clean Architecture 3-layer, GetX reactive state, automated feature & unit tests passing 100%). |
| **Milestone 5:** Packages & Contractual Pricing | `COMPLETED & VERIFIED` | Backend M5.1 security remediation verified. Frontend M5 catalog UI verified with end-to-end cursor pagination (`PaginatedList`), debounced server search (`search`), server-side status filtering, deterministic network selection via `GetMyNetworksUseCase`, screen decomposition into dedicated section widgets, and strict server financial authority with zero client calculations. Flutter static analysis clean (0 issues), full test suite passing. |
| **Milestone 6:** Procurement Requests Lifecycle | `COMPLETED & VERIFIED` | Fully delivered and verified across Flutter: POS Procurement Flow (M6.1) with new procurement order wizard (`PosNewProcurementOrderScreen`), requests list with cursor pagination (`PosProcurementOrdersScreen`), and cancellation action. Network Procurement Review Flow (M6.2) with incoming review queue (`NetworkSupplyRequestsReviewScreen`), approval decision dialog with automated invoicing (`approveRequest`), and rejection decision dialog with mandatory reason (`rejectRequest`). Detailed request inspection view (`ProcurementRequestDetailsScreen`) with items breakdown and server-calculated totals. Clean Architecture 3-layer strictly enforced, zero `StatefulWidget`/`setState`, zero direct HTTP calls, zero currency symbols/labels (`AmountFormatter.format`), full test suite passing (201/201 tests, 100%), static analysis clean (0 issues). |
| **Milestone 7:** Invoicing & Accounting | `COMPLETED & VERIFIED` | Fully delivered and verified across Flutter: Network Wholesale Invoices (M7.1) with cursor pagination, debounced search (300ms), sale type filtering, and network selector. POS Purchase Invoices (M7.2) with cursor pagination and purchase history. Invoice Details View (`InvoiceDetailsScreen`) with itemized breakdown table, authoritative server-calculated financial totals, and direct navigation to related procurement requests. Official Invoice Document Generation (`InvoiceDocumentService`): PDF export via `FileExportService`, direct printing via `Printing.layoutPdf`, and system sharing via `shareBytes`, with RTL Arabic support (IBM Plex Sans Arabic fonts). Zero `StatefulWidget`/`setState`, zero currency symbols (`AmountFormatter.format`), full test suite passing (210/210 tests, 100%), static analysis clean (0 issues). |
| **Milestone 8:** Wallets, Credit, Debts & Repayments | `COMPLETED & VERIFIED (FLUTTER PRESENTATION & ARCHITECTURE)` | Completed and fully audited across Flutter: Dual wallet dashboards for Network and POS, transaction ledgers, debt aging summaries with buckets, debt repayment workflows with FIFO breakdown previews (informational), and strict zero client-side financial calculations. 100% compliance with Clean Architecture, pure domain models, and universal architecture tests passing. |

---

## 2. Git Authority References

- **`netsales_api` (Laravel Backend):**
  - Branch: `main` (tracking `origin/main`)
  - Working Tree: Preserved. Zero destructive git operations.
- **`netsales` (Flutter Client):**
  - Branch: `main` (tracking `origin/main`)
  - Working Tree: Preserved. All user-supplied modifications retained.

---

## 3. Pending Decisions Register

All architectural and business decisions requiring future implementation approval remain strictly tracked:

- **`DEC-01` (`PROPOSED`):** Add `role_scope` to `AuthService::me()` response.
- **`DEC-02` (`PROPOSED`):** Seed dedicated least-privilege permission matrix for `NETWORK_ACCOUNTANT` and `POS_ACCOUNTANT`.
- **`DEC-03` (`PROPOSED`):** Enforce `PaymentType` enum validation and model casting in repayment settlement.
- **`DEC-04` (`PROPOSED`):** Implement informational server-side FIFO repayment preview endpoint (`POST /pos-debts/repayments/preview`).

---

## 4. Known Project Limitations & Verification Status

1. **Active Workspace Context Boundary (DEC-01):** Active tenant and workspace context switching remains bounded by DEC-01 (`PROPOSED`). Current backend isolates tenants strictly at the route policy and membership level (`NetworkWalletPolicy`, `PosWalletPolicy`, `NetworkMembershipService`), without a global tenant-context header.
2. **Registered Device Binding Limitation:** Registered device UUID is checked against the session association as a supplementary server-side security control. It is not cryptographic hardware attestation or absolute protection against device cloning.
3. **Client-Side Credential Storage:** Raw session tokens are returned once upon authentication and persisted securely on the client in `FlutterSecureStorage`. Raw tokens are never stored in the database or written to logs.
4. **Visual QA Status:** Visual QA for live rendered UI is marked **NOT VERIFIED — REQUIRES ACTIVE EMULATOR/DEVICE** due to zero running emulators during automated headless runs.
5. **Laravel Test Suite Failures:** 3 unrelated-scope failures; pre-existing status not established. (Failures in `Milestone4CConcurrencyAndDirectInvoiceDebtTest`, `NetworkRequestReviewAndApprovalTest`, and `VoucherRequestAndInvoicingTest` pertain to Phase 4 commercial and financial calculations outside Milestone 3).

---

## 5. Next Authorized Action
Halt at the Milestone 6 completion boundary. Present delivery verification evidence and await explicit user authorization before starting **Milestone 7 (Invoicing & Accounting)**.

## Active implementation — Network/POS membership safety (2026-11-09)
- API implementation is under review in PR #10 (`feat/scoped-membership-role-safety`).
- Flutter implementation is under review in NetSales PR #20 (`feat/network-pos-team-management`).
- Role assignment is intentionally limited to the ordinary operational user role per tenant. Accountant assignment remains excluded until accountant permission sets are verified to be read-only and policy-compliant.
- PR #20 depends on the API contract and `is_owner` response change in PR #10; merge the API PR first, then the Flutter PR.
- CI and visual QA are pending; do not mark this feature fully verified until exact-head checks pass and rendered phone/tablet Light/Dark review is completed.


## Active implementation — SaaS Billing and Versioned Trial/Dunning Policies (2026-11-10)
- Active backend branch: `feat/saas-billing-multicurrency-rebuild`.
- The subscription and billing domain is being rebuilt around immutable plan/price versions, explicit currencies, invoice/payment/ledger workflows, entitlements, usage metering, and platform-only billing administration.
- Trial behavior is controlled by versioned `billing_trial_policies` / `billing_trial_policy_versions`, not `system_settings`. Resolution order: price override → plan-version assignment → platform default.
- Trial eligibility, redemption uniqueness, manual approval, first-activation start, expiry behavior, and audited adjustments are server-owned.
- Grace periods, dunning reminders, auto-suspension, and pre-renewal alerts are separately governed by versioned dunning policies.
- `system_settings` no longer owns the `SUBSCRIPTION` category or its four legacy keys. The System Settings API rejects attempts to recreate the retired category.
- Existing plan versions are migrated to equivalent immutable trial policy versions; plans with zero trial days remain non-trial.
- Tests and GitHub Actions are intentionally deferred until the end of the approved implementation plan. No test or CI success is claimed in this state entry.


### Billing integration hardening — 2026-11-10
- Platform default trial is intentionally disabled; trial activation must be explicitly configured.
- Existing non-zero legacy plan trial durations map to dedicated policy versions. Existing subscriptions receive policy snapshots during migration.
- Ineligible trial customers may still purchase the paid subscription without a trial.
- First-activation trials use RESERVED redemption state and activate atomically on the first entitlement lookup after any required manual approval.
- Trial expiry behavior is enforced: EXPIRE closes the trial; CREATE_INVOICE begins conversion billing.
- Billing reminders are delivered through the existing notification service from Outbox with a unique message UUID delivery guard.
- Manual trial approval/rejection and audited trial adjustment routes are exposed under Platform Scope.
- Automated tests, migration execution, and GitHub Actions remain pending by user instruction.


### Billing lifecycle consistency — 2026-11-10
- Invoice reminders and grace-period deadlines are now anchored to contractual invoice due dates.
- Cancellation scheduling is limited to live subscriptions with a future period; pending renewal alerts are cancelled on cancellation.
- Payment verification now uses the global Invoice -> Payment -> Treasury lock order.
- A late settlement of an initially expired invoice can restore the subscription only by starting a fresh paid period if the original period elapsed.
- The duplicate payment idempotency-key migration operation has been removed.
- Automated validation and migrations remain pending by instruction.


### Credit note and refund workflow — 2026-11-10
- Platform credit-note issuance now records invoice-line allocations, immutable amounts, accounting treatment, receivable offsets, and refundable customer credit.
- Refunds require a matching issued credit note and are limited by both payment and credit-note remaining refundable balances.
- Credit note issuance and cash settlement are separately posted to the same-currency ledger.
- Invoice API responses can expose the related credit notes and their applied/refundable/refunded balances.
- Tests, migration execution, and CI remain pending by instruction.

- Refund idempotency is checked again after invoice-level locking to handle concurrent retries safely.


### Billing implementation closeout — 2026-11-10
- Subscription billing implementation is functionally assembled on feat/saas-billing-multicurrency-rebuild / PR #18.
- Closeout hardening corrected the trial policy resolver, duplicate Outbox dispatch, payment verification controller contract, invoice credit balance accounting, customer-credit liability postings, and dunning/dashboard handling for credited invoices.
- billing_invoices.credited_minor records issued credit-note value separately from cash collections. Outstanding amount is derived as max(0, total_minor - paid_minor - credited_minor).
- Credit-note issue allocates value against receivables first and posts any overpaid amount to customer-credit liability. Refund settlement debits that liability and credits same-currency treasury.
- Verification status: NOT VERIFIED. Per user instruction, no tests, PHP lint/static analysis, migrations, or GitHub Actions have been run yet. Do not merge or treat as production-ready until the final validation gate passes and all failures are resolved.


### Deferred revenue and reconciliation closeout — 2026-11-10
- Recurring subscription invoice lines now create per-line revenue recognition schedules; daily processing recognizes the exact earned amount in integer minor units and posts balanced entries per currency.
- Setup fees and metered usage are posted to earned revenue accounts at invoice finalization; recurring subscription consideration is initially deferred. Tax is separated to account 2300.
- Credit-note accounting is inferred from recognized/unrecognized revenue and tax allocation, not chosen manually by the API caller. Refunds settle customer-credit liability against same-currency treasury.
- Voiding is blocked once a service line has recognized revenue; unstarted schedules are cancelled for an otherwise eligible void.
- Added automated revenue-recognition feature coverage. Migration `migrate:fresh --seed` passed in the workflow at SHA `2c19f61d`; Pint revealed two parse errors on a prior SHA, corrected in later commits. **Current branch head still requires an exact-SHA run completing Pint, PHPStan, the complete Laravel test suite, and all final gate checks.** PR remains Draft and unmerged.


### Per-currency reconciliation implementation — 2026-11-10
- Refund records now persist the treasury account that paid the refund, enabling exact treasury subledger matching.
- Added an internal reconciliation endpoint/service comparing AR, deferred revenue, customer-credit liability, tax payable, treasury balances, and per-currency trial balance.
- Report status is MISMATCH when balances differ or treasury allocations/ledger links are missing. Results are partitioned by currency; there is no mixed-currency aggregation.
- Added feature tests for revenue posting, automatic credit-note accounting, refund settlement, and reconciliation after full refund. Exact-head CI is required before acceptance.

## Platform Billing administration integration — 2026-11-11

- Active API review branch: feat/platform-billing-admin-contracts.
- Platform Billing exposes a cursor-paginated product catalogue and plan-version history alongside the multi-currency billing resources.
- Trial and dunning policies are independently versioned; policy-version history is read under billing.view, while create/version/publish/assign and redemption decisions remain billing.manage operations.
- Plan catalogue mutations retain the permission split: billing.create for product/plan records, billing.edit for Draft version/price/feature construction, and billing.manage for publication.
- Route declarations were reconciled to remove duplicate policy endpoints and keep policy routes inside the dedicated policies controller group.
- Verification remains PENDING: no PHP runtime, Pint, PHPStan, PHPUnit, migration run, route:list, or exact-head CI result is available for this branch. Do not mark the billing administration track accepted or production-ready until the full API workflow passes and failures are fixed.


## Active implementation track — Operational Reports & Official Documents (2026-11-13)

**Authorized branches**
- API: `feat/operational-reports-contracts` — HEAD `2d971bddd4e8f120183b1cd3c4729fb9c63722fa`.
- Flutter: `feat/invoice-document-engine-integration` — HEAD `6a5f3d4d61bcaba963bc89c1bab337ffc93222af`.
- These branches are not merged. Do not treat their code as accepted or production-ready until the final validation gate passes.

### Stage 3 — Operational report contracts
- Implemented the provider contract, registry, factory, shared filter/schema validation, and seven dedicated providers: sales, expenses, network wallet, POS wallet, repayments, debt aging, and requests.
- The API controller, summary execution, cursor-paginated results, authorization, and queued CSV export consume the registry and provider-owned row serialization.
- Removed the legacy `OperationalReportQueryService` and `OperationalReportProviderAdapter`; no compatibility adapter is retained.
- Flutter consumes the server catalog as typed `document_engine` `ReportDefinition` schemas and registers exact versions in `ReportDefinitionRegistry`. Access metadata remains separate from the definition; Laravel remains authoritative for authorization and query execution.

### Stage 4 — Operational ledger and official documents
- Wallet opening balance, currency, posting-time before/after balance snapshots, and snapshot provenance are represented in the schema and resources.
- Historical wallet rows are marked `BACKFILLED`; newly initialized wallets are marked `INITIAL`; new posting snapshots are marked `LIVE`.
- Network/POS statements include opening and closing period balances and include soft-deleted ledger rows. The period closing balance is derived from movements by effective `log_date`, while before/after snapshots are explicitly posting-time values.
- Wallet mutations validate active state and positive amounts under row locks; POS withdrawals use the configured credit limit rather than a stale positive-balance precheck.
- Operational currency is separate from Platform Billing currency. Official document creation rejects a network/POS currency mismatch rather than silently combining currencies.
- Immutable document snapshots capture source resources and a SHA-256 manifest; creator/reversal identities and expense attachment metadata are minimized before persistent storage. PDF artifacts are first-write-only, protected, hashed, and downloadable as the canonical document bytes.

### Specialized documents
- Invoice PDF is network-issued with the network as supplier and POS as recipient, A4 portrait, itemized pricing/quantity/discount, currency, contacts, request reference, creator, paid and remaining amounts.
- Repayment receipt is network-issued, A5 portrait, and includes allocations and reversal trace details.
- Expense voucher is network-branded A4 portrait with expense lines, total, creator, currency, and attachment metadata.

### Validation gate — PENDING
- No migrations have been applied to a database.
- No PHPUnit/Dart tests, PHP lint, Pint, PHPStan, Flutter analyzer, PDF extraction/visual checks, or GitHub Actions have been run for this implementation track.
- Windows, Android, and iOS PDF behavior and Arabic extraction/order remain unverified.
- Do not merge or claim production readiness until the final plan validation stage runs against the exact branch heads and all failures are resolved.
