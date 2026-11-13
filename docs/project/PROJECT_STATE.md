# NetSales — Current Project State & Authorization Snapshot

**Last Updated Date:** 2026-11-14  
**Active Working Tree Status:** Billing self-service/search pagination, subscription and Billing Reports integration, Operational Reports scope isolation, and official-document source hardening — implementation work continues; final validation remains pending  
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
- API: `feat/operational-reports-contracts` — HEAD `7ac484747b0d69dbacb608cb45ab34d707a8e3dc`.
- Flutter: `feat/invoice-document-engine-integration` — HEAD `60703dfe1193ca28a452207c98657f8d4e1e2012`.
- These branches are not merged. Do not treat their code as accepted or production-ready until the final validation gate passes.

**Branch relationship to main (2026-11-13):**
- API branch is 268 commits ahead and 2 behind `main` (diverged).
- Flutter branch is 326 commits ahead and 5 behind `main` (diverged).
- No pull request was found for either active feature branch at the time of inspection. Do not merge directly without synchronizing and reconciling the missing base commits.

### Stage 3 — Operational report contracts
- Implemented the provider contract, registry, factory, shared filter/schema validation, and seven dedicated providers: sales, expenses, network wallet, POS wallet, repayments, debt aging, and requests.
- The API controller, summary execution, cursor-paginated results, authorization, and queued CSV export consume the registry and provider-owned row serialization.
- Removed the legacy `OperationalReportQueryService` and `OperationalReportProviderAdapter`; no compatibility adapter is retained.
- Flutter consumes the seven known report types from the server catalog as typed `document_engine` `ReportDefinition` schemas and registers exact versions in `ReportDefinitionRegistry`. Access metadata remains separate from the definition; Laravel remains authoritative for authorization and query execution.
- API now accepts filters only inside the schema-validated nested `filters` object; the retired flat filter fields are prohibited. All seven provider serializers use the same `serializeExportRow()` contract for result rows and CSV.
- Remaining limitation: Flutter's current report use cases/controller still map a fixed set of known filters and its `OperationalReportType` enum is finite. The generic schema studio is not integrated into the operational reports screen yet; adding arbitrary new report definitions without client code changes is not complete.

### Stage 4 — Operational ledger and official documents
- Wallet opening balance, currency, posting-time before/after balance snapshots, and snapshot provenance are represented in the schema and resources.
- Historical wallet rows are marked `BACKFILLED`; newly initialized wallets are marked `INITIAL`; new posting snapshots are marked `LIVE`.
- Network/POS statements include opening and closing period balances and include soft-deleted ledger rows. The period closing balance is derived from movements by effective `log_date`, while before/after snapshots are explicitly posting-time values.
- Wallet mutations validate active state and positive amounts under row locks; POS withdrawals use the configured credit limit rather than a stale positive-balance precheck.
- Wallet balance mutation, posting-time snapshots, report opening/closing calculations, and debt aging use shared exact decimal arithmetic in integer minor units. POS wallet creation requires the existing network wallet currency; wallet statements, SMS notifications, report PDFs, and official snapshots no longer silently substitute the platform base currency or a hard-coded YER fallback.
- Operational currency is separate from Platform Billing currency. Official document creation rejects a network/POS currency mismatch rather than silently combining currencies.
- Immutable document snapshots capture source resources and a SHA-256 manifest; creator/reversal identities and expense attachment metadata are minimized before persistent storage. PDF artifacts are first-write-only, protected, hashed, and downloadable as the canonical document bytes.

### Specialized documents
- Invoice PDF is network-issued with the network as supplier and POS as recipient, A4 portrait, itemized pricing/quantity/discount, currency, contacts, request reference, creator, paid and remaining amounts.
- Repayment receipt is network-issued, A5 portrait, and includes allocations and reversal trace details.
- Expense voucher is network-branded A4 portrait with expense lines, total, creator, currency, and attachment metadata.
- Remaining document/report gap: operational report PDF currently prints an executive summary, not the complete row-level wallet statement/report; row-level export is CSV. A per-report reusable template family and visual validation remain outstanding.

### Reports module and preset integration gaps
- `document_engine` contains the generic `ReportConfigurationStudio` widget and versioned `SavedReportPreset` value object with package tests. NetSales' `OperationalReportsScreen` does not currently host the studio, and no API preset persistence model, migration, or save/list/update/delete contract exists. These components are reusable foundations, not a completed saved-reports feature.
- Current report result UI continues to use NetSales custom buttons and the existing filter/list/summary widgets. No row-level PDF statement output has been proven for wallets.

### Validation gate — PENDING
- No migrations have been applied to a database.
- No PHPUnit/Dart tests, PHP lint, Pint, PHPStan, Flutter analyzer, PDF extraction/visual checks, or GitHub Actions have been run for this implementation track.
- Windows, Android, and iOS PDF behavior and Arabic extraction/order remain unverified.
- Do not merge or claim production readiness until the final plan validation stage runs against the exact branch heads and all failures are resolved.


## Scheduler unification update — 2026-11-13
- Active API branch: `feat/operational-reports-contracts`.
- Current API HEAD before this state update: `ec3aec42ed8663f4ff1df05216b8f649b5e33c80`.
- Current Flutter branch reference verified during this work: `feat/invoice-document-engine-integration` at `6778572fe07294d4ad534a38277521888ba8a495`.
- Scheduler implementation is source-updated but NOT runtime-validated. It centralizes task definitions in `ScheduledTaskType`, persists exact occurrence slots and two-hour execution leases, and removes parallel recurring command declarations.
- API activity middleware triggers bounded catch-up after successful requests when Cron is unavailable. It processes at most two successfully claimed scheduled occurrences and one queued job per throttled activity tick. Completed/locked occurrences are skipped without consuming the batch limit. The fallback still requires application traffic; it cannot run in the absence of all triggers.
- Queue processing uses the configured connection and one-shot worker invocation; no resident worker is required for this fallback. `sync` and `null` connections are excluded.
- Database migration, all tests, static analysis, PDF checks, and GitHub Actions remain deferred to the final validation gate. No merge or production readiness is claimed.


## Wallet ledger and official document closure update — 2026-11-13
- Network and POS wallet model-level mutation methods now use DecimalAmount integer minor units, record exact before/after balances, and reject invalid currency codes instead of falling back to YER.
- Wallet sufficiency checks use positive minor-unit amounts. POS overdraft capacity and purchasing power are calculated in integer minor units; available overdraft is remaining credit after any negative balance, and total purchasing power is cash plus remaining credit.
- All network↔POS transfer and reversal paths validate matching ISO-style currency codes before changing balances; no implicit cross-currency conversion is allowed.
- Wallet ledger migration 000001 backfills opening balances and ordered before/after snapshots in minor units. Network/POS wallet statement providers derive period opening/closing balances from the same ledger movements and include currency/source metadata.
- Official document snapshots bind immutable source JSON, template/schema versions, currency, and SHA-256; the client renders from the server snapshot, uploads once, then downloads the server-canonical artifact and verifies its SHA-256. Invoice, repayment, and expense adapters use all seven IBM Plex Sans Arabic weights and tenant/application logo image watermarks.
- Source implementation only. Database migrations were not applied and no tests, static analysis, PDF text extraction, visual comparison, or Actions were run. Final validation remains pending.


## Invoice settlement ledger correction — 2026-11-13
- SaleInvoiceService now refuses to settle an invoice unless the locked network and POS wallets have valid matching operational currency codes.
- Cash-sale POS ledger entries now carry explicit sequential balance snapshots: withdrawal takes the balance to the temporary post-debit position, and the paired cash deposit restores the original balance. The wallet's stored balance remains unchanged; the two ledger movements net to zero without inventing two unrelated before-balances.
- POS wallet log creation validates supplied balance snapshots against operation type and amount. The optional explicit-snapshot path is reserved for balanced journal entries; ordinary entries continue deriving snapshots from the locked wallet balance.
- Source-only change; no database/test execution performed.


## Detailed wallet statement templates — 2026-11-13
- Flutter branch `feat/invoice-document-engine-integration` now contains dedicated network-wallet and POS-wallet statement templates on the shared Document Engine.
- Statement exports read every cursor page (50 records per page, capped at 1,500 rows), then re-check the newest ledger ID and wallet state. If a new movement or wallet mutation occurs during retrieval, export is aborted rather than combining inconsistent pages and balances.
- The renderer requires a valid wallet ISO currency, matching currency on every movement, opening balance, and per-entry before/after balance snapshots. It does not calculate or aggregate monetary totals in Flutter.
- Output is A4 landscape with a nine-column ledger table, opening/current balances, credit limit where relevant, image watermark using POS/network logo with application-mark fallback, Arabic IBM Plex Sans weights, page numbering, and an explicit notice when the opening balance was backfilled.
- Maximum statement size is 1,500 ledger entries per PDF. Larger ledgers are rejected without generating a partial document.
- Flutter HEAD: `f83e8dac0ec2fcbef80f6d3d4eb3383824ecaf35`. No analyzer, formatter, tests, PDF text extraction, visual checks, or Actions were run; all remain in the final validation stage.


## Saved operational report presets — 2026-11-13

- Added per-user saved report presets scoped to network or POS workspace and report type. The database enforces unique names within user + network + scope + report type.
- Presets persist only typed criteria and presentation choices (visible columns, sort order, grouping, template/version, locale, page profile, metrics/charts). They do not store report rows, permissions, tokens, signed URLs, or tenant authorization grants.
- API endpoints: `GET /reports/presets`, `POST /reports/presets`, `PUT /reports/presets/{presetUuid}`, and `DELETE /reports/presets/{presetUuid}`. All require `operational.scope`, authenticated ownership, current report authorization, and current provider schema validation.
- Preset schema version is `1`; report schema version must match the current provider definition. Criteria and presentation fields are validated against the provider allowlists before persistence. Preset records are owned by the user and cascade on user/network deletion.
- Report catalog template references now match the type-specific executive PDF template IDs.


## Scheduler catch-up and saved report preset closure — 2026-11-13

- The scheduled-task ledger now has a schema migration for `scope_key`, `schedule_slot`, `started_at`, `finished_at`, and `locked_until`, replacing the old daily-only unique key with `(task_type, scope_key, target_date, schedule_slot)`. Existing runs are backfilled; legacy low-balance runs map to the first daily slot.
- `SmartSchedulerCatchupService` remains the sole task registry/execution engine. Activity ticks process a bounded number of due occurrences and opportunistically drain one queued job using the configured queue connection and `queue:work --once`; the Laravel scheduler invokes the same engine when Cron exists.
- The twice-daily low-balance task now contributes both due occurrences for the current day, each with its own durable slot and per-network idempotency record.
- The activity fallback requires an incoming application request. If there is no Cron, no queue worker, and no application traffic, no PHP process exists to execute background work; this is a platform constraint, not something the application can bypass.
- Saved report presets are stored as schema-versioned typed criteria and presentation configuration, owned by the authenticated user and scoped to network/POS + report type. The Flutter Report Studio now loads, saves, updates, applies, and deletes these presets; stale schema presets are not silently migrated.
- No tests, migrations, static analysis, PDF extraction, or GitHub Actions have been run; they remain deferred to the final validation stage.


## Active implementation track — Billing self-service, subscriptions, and Billing Reports (2026-11-14)

**Authorized working branches**
- API: `feat/billing-self-service-contracts` — HEAD before this state update: `7c8d462d20aa953408de093881f96bf7f9db436`.
- Flutter: `feat/subscriptions-network-module` — HEAD before this state update: `189a8319b5a167d3f474910fd52c6ed817a2a9be`.
- No open PR was found for either branch at the time of the check. No PR or merge was created in this work.

### Billing self-service API
- Network-owner account access is checked before every collection query. Search predicates are grouped inside the authorized account query and use parameter bindings.
- Plans, subscriptions, invoices, payments, credit notes, and refunds support bounded cursor pagination; the collection endpoints validate search/status inputs and pass explicit cursors to their services.
- Server search supports the corresponding human reference/identifier fields and related plan/invoice/payment descriptors without retrieving complete collections to filter on the client.
- Subscription details return a bounded first event page and invoice page; event and usage endpoints have cursor support.

### Flutter Billing workspace
- Six growing Billing collections own independent cursor state, first-page loading/error state, debounced search, and status criteria. The selected filters are sent with every initial page and subsequent cursor page.
- Account/network changes cancel pending search timers and clear prior-scope filter state. Stale first-page and load-more responses are rejected by revision, account, network, and page-generation checks.
- Shared `lib/widgets/lists/custom_collection_filter_bar.dart` and `CustomCursorLoadMore` are used instead of feature-local or stock Material controls. The workspace is composed from independent account, plan, subscription, entitlement, invoice, and financial sections.
- Financial list rows use server decimal amount strings and nested currency metadata; no client-side minor-unit conversion or cross-currency aggregation is introduced.

### Billing Reports and Operational Reports
- Billing Reports uses the shared Report Configuration Studio and separate widgets for criteria, PDF presentation, visible columns, summaries/charts, and row results. Search preview cancels a pending debounce to avoid duplicate requests.
- Cursor traversal for interactive results and PDF export rejects repeated cursors and stale scope/report revisions.
- Operational Reports invalidates catalogue, preset, CSV export, export-status, and PDF actions when scope/report criteria change; older scope responses cannot update the active workspace.

### Verification gate — PENDING
- Source changes only. No database migrations were executed.
- No PHPUnit, PHP lint/Pint/PHPStan, Dart/Flutter tests, formatter/analyzer, PDF text extraction/order checks, visual QA, or GitHub Actions were run in this work.
- Do not create or merge PRs, and do not mark the feature production-ready, until all implementation-plan requirements are closed and the exact branch heads pass the final verification gate.
