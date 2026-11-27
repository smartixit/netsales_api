# NetSales — Canonical Architecture & Business Decision Log

This is an **append-only** decision register. Decisions record architectural choices, product boundaries, and technical standards.
Valid statuses are strictly:
- `PROPOSED`: Evaluated and documented; awaiting user review. Implementation is prohibited.
- `APPROVED`: Explicitly approved by the user. Implementation authorized.
- `REJECTED`: Explicitly rejected by the user. Must not be implemented.
- `SUPERSEDED`: Replaced by a subsequent approved decision.

---

## Decision Record Index

| ID | Date | Subject | Status | User Decision Date |
|---|---|---|---|---|
| **GOV-01** | 2026-11-02 | Persistent Agent Governance Foundation Adoption | **`APPROVED`** | 2026-11-02 |
| **M1-BASE** | 2026-11-02 | Milestone 1 Discovery & Blueprint Working Baseline | **`APPROVED`** | 2026-11-02 |
| **DEC-01** | 2026-11-02 | RoleScope and Active Tenant Context in Auth Contract | **`PROPOSED`** | Pending Review |
| **DEC-02** | 2026-11-02 | Dedicated Scoped Permissions for Network & POS Accountants | **`PROPOSED`** | Pending Review |
| **DEC-03** | 2026-11-02 | Strict PaymentType Enum Validation and Model Cast in Repayments | **`SUPERSEDED`** | Repayment domain removed by DEC-05 |
| **DEC-04** | 2026-11-02 | Server-Side Informational FIFO Repayment Preview Endpoint | **`REJECTED`** | Repayment domain removed by DEC-05 |
| **BILL-TRIAL-01** | 2026-11-10 | Versioned Trial and Dunning Policy Engine | **`APPROVED`** | 2026-11-10 |
| **DEC-PROC-01** | 2026-11-17 | POS procurement display-only estimate from signed pricing snapshots | **`APPROVED`** | 2026-11-17 |
| **DEC-INV-01** | 2026-11-19 | Strict Deletion Blocking on Insufficient Network Balance for Cash Invoices | **`APPROVED`** | 2026-11-19 |
| **DEC-QR-01** | 2026-11-22 | Network invitations must target one exact POS | **`SUPERSEDED`** | 2026-11-21 |
| **DEC-QR-02** | 2026-11-22 | Use POS commercial business code as the sole QR request identifier | **`APPROVED`** | 2026-11-22 |

---

## GOV-01: Persistent Agent Governance Foundation Adoption
- **Status:** **`APPROVED`**
- **Date:** 2026-11-02
- **Authorized Scope:** Implementation of root `AGENTS.md`, `.agents/rules/`, `.agents/skills/`, canonical documentation under `netsales_api/docs/project/`, and cross-repository synchronization mechanisms.
- **Context:** To eliminate context loss, repeated audits, and architectural drift across Antigravity sessions and model compactions.
- **Consequence:** All future sessions are governed by these persistent rules, canonical documents, and handoff protocols.

---

## M1-BASE: Milestone 1 Discovery & Blueprint Working Baseline
- **Status:** **`APPROVED`**
- **Date:** 2026-11-02
- **Authorized Scope:** Acceptance of the Milestone 1 consolidation report as the project discovery baseline for forward progress, with known limitations recorded.
- **Consequence:** Discovery phase is closed; future implementation milestones proceed from this established baseline without restarting general audits.

---

## DEC-01: RoleScope and Active Tenant Context in Auth Contract
- **Status:** **`PROPOSED`**
- **Date:** 2026-11-02
- **Subject:** Inclusion of `role_scope` in `AuthService::me()` and `RoleResource`.
- **Context:** `AuthService::me()` returns role keys and permissions, but omits `role_scope`. The client must either infer scope or receive it from the server.
- **Options:**
  - *Option A:* Enhance `AuthService::me()` and `RoleResource` to include authoritative `role_scope`.
  - *Option B:* Infer scope client-side from `role_key`.
- **Recommendation:** Option A.
- **Security Qualification:** Server-provided `role_scope` improves client routing convenience, but does NOT prevent client spoofing. Server-side authorization on each endpoint remains the sole security boundary.
- **Implementation Status:** Not authorized. Remains `PROPOSED`.

---

## DEC-02: Dedicated Scoped Permissions for Network & POS Accountants
- **Status:** **`PROPOSED`**
- **Date:** 2026-11-02
- **Subject:** Seeding explicit least-privilege permissions for `NETWORK_ACCOUNTANT` and `POS_ACCOUNTANT`.
- **Context:** `RolePermissionSeeder.php` currently assigns permissions only to the platform `ACCOUNTANT` role, omitting scoped accountants.
- **Options:**
  - *Option A:* Seed explicit scoped permissions for Network and POS accountants (read-only financial statements, invoice audits, debt logs).
  - *Option B:* Fallback to general `ACCOUNTANT` role.
- **Recommendation:** Option A (Least privilege).
- **Implementation Status:** Not authorized. Remains `PROPOSED`.

---

## DEC-03: Strict PaymentType Enum Validation and Model Cast in Repayments
- **Status:** **`PROPOSED`**
- **Date:** 2026-11-02
- **Subject:** Validating `payment_method` with `Rule::enum(PaymentType::class)` and casting in `PosRepayment`.
- **Context:** `CollectRepaymentRequest` validates `payment_method` as an unconstrained string, and `PosRepayment` model does not cast it.
- **Options:**
  - *Option A:* Enforce `Rule::enum(PaymentType::class)` in FormRequest and add cast to `PaymentType::class` in model.
  - *Option B:* Retain flexible string validation.
- **Recommendation:** Option A to ensure database ledger integrity.
- **Implementation Status:** Not authorized. Remains `PROPOSED`.

---

## DEC-04: Server-Side Informational FIFO Repayment Preview Endpoint
- **Status:** **`PROPOSED`**
- **Date:** 2026-11-02
- **Subject:** Adding `POST /api/v1/pos-debts/repayments/preview` in a future backend milestone.
- **Context:** No preview route currently exists for debt repayments in Laravel. Flutter cannot calculate authoritative FIFO allocations.
- **Options:**
  - *Option A:* Implement server-side preview endpoint in future backend milestone.
  - *Option B:* Collect repayments without preview.
- **Recommendation:** Option A.
- **Concurrency Qualification:** Any future server-side preview is strictly informational and non-binding because concurrent transactions can alter invoice balances between preview and collection. The final settlement is determined atomically inside the collection transaction under row locks.
- **Implementation Status:** Not authorized. Remains `PROPOSED`.


| SUB-AUTH-001 | 2026-11-09 | Enforce the seeded subscription permission matrix at Laravel route boundaries; do not rely on Flutter route guards or hidden controls. Preserve network-workspace request submission and quota operations outside platform-only permissions. | APPROVED |

## SUB-AUTH-001 — Subscription API Authorization
- Plan and feature catalogue reads require `subscriptions.view`.
- Plan creation requires `subscriptions.create`; plan updates and feature catalogue mutations require `subscriptions.edit`; soft/force delete and plan status toggling require `subscriptions.delete`.
- Subscription request reads require `subscriptions.view`; approval and rejection require their respective `subscriptions.approve` and `subscriptions.reject` permissions.
- Provider subscription reads and consumption detail require `subscriptions.view`; extension requires `subscriptions.edit`.
- Provider request submission, request cancellation, network-scoped active-subscription lookup, feature consumption, and early activation retain their operational routes and are not assigned platform subscription permissions by this change.


---

## BILL-TRIAL-01: Versioned Trial Policy Engine and Removal from System Settings
- **Status:** **`APPROVED`**
- **Date:** 2026-11-10
- **User Decision Date:** 2026-11-10
- **Context:** Subscription trial duration and dunning behavior must not be global key/value settings. Commercial policy needs eligibility, versioning, plan/price overrides, redemption controls, approval, and auditable adjustments.
- **Decision:** Use dedicated immutable published versions for trial and dunning policies. Resolution order is price override, plan-version assignment, then active platform default. Snapshot the resolved versions on every subscription. Trial eligibility and redemption are enforced server-side and transactionally. Trial extensions and early termination require a reason and an actor audit record. Dunning grace, suspension, reminders, and pre-renewal alerts are governed by a separate dunning policy.
- **System Settings:** Remove the `SUBSCRIPTION` category and its trial/grace/suspension/expiry-alert keys. System Settings must reject creation, update, and lookup/filter operations for the retired category. Policy management is available only under Platform Scope billing APIs.
- **Compatibility:** Existing plan-version trial durations are migrated to equivalent immutable policy versions; zero-day plans remain explicitly non-trial. The platform default is disabled with zero duration, so newly created plans do not receive an unintended free trial. Existing subscription records receive explicit trial and dunning policy snapshots during migration.
- **Consequence:** Changing a policy affects only future subscriptions/assignments; existing subscriptions continue using their stored snapshots.


### Billing collection timing and lock order — 2026-11-10
- Dunning reminder offsets are measured from the invoice due date, not invoice creation time.
- Grace periods are measured from the due date, then extended by the policy's configured number of days.
- Billing lock order for invoice collection is Invoice -> Payment -> Treasury; all collection paths must preserve this order.
- Period-end cancellation is only available while an ACTIVE/TRIALING service period is still in the future. Immediate cancellation revokes any reserved/active trial redemption and suppresses queued renewal alerts.
- Settlement of an invoice after the originally billed period elapsed does not grant retroactive service time; the paid period restarts at settlement.


### Credit notes and cash refund accounting — 2026-11-10
- A cash refund must never be issued without an issued credit note authorizing the customer's credit.
- Credit notes first offset open accounts receivable; only any remaining amount backed by net verified collections becomes refundable customer credit.
- Credit-note amounts are allocated to original invoice lines and cannot exceed either the uncredited source line amount or the remaining invoice amount.
- Credit-note issuance debits deferred revenue or recognized revenue reversal according to an explicit Platform Scope accounting treatment and credits Accounts Receivable.
- Cash settlement of refundable credit debits Accounts Receivable/customer credit and credits a treasury account in the same currency.
- Credit notes, refunds, and their accounting journal entries are immutable after posting; corrections require a new financial document/reversal.

- Refund requests revalidate idempotency after acquiring the invoice lock; invoice-level serialization is the concurrency boundary for refunds on that invoice.

## DEC-PROC-01: POS procurement display-only estimate from signed pricing snapshots
- **Status:** **`APPROVED`**
- **Date:** 2026-11-17
- **User Decision Date:** 2026-11-17
- **Scope:** POS new-procurement package cards and cart summary only.
- **Decision:** Permit Flutter to calculate a non-binding estimate from Laravel-issued, POS/network/package-bound pricing snapshot inputs (integer minor-unit base price and basis-point discount). Recalculate locally on quantity/payment-type changes without network requests. Do not send or persist client totals. New request creation sends each item's signed snapshot token; Laravel validates current tariff/discount state and computes authoritative totals transactionally.
- **Expiry:** Snapshot lifetime is 30 minutes. Missing, expired, or stale snapshots block submission and require refreshing the package catalogue.
- **Explicit exclusions:** No client-side accounting or financial calculations outside this narrowly scoped POS procurement display estimate. Existing server quote/preview flows for request modification remain unchanged.

---

## DEC-INV-01: Strict Deletion Blocking on Insufficient Network Balance for Cash Invoices
- **Status:** **`APPROVED`**
- **Date:** 2026-11-19
- **User Decision Date:** 2026-11-19
- **Subject:** Policy when deleting a cash invoice (`CASH`) where the supplying network wallet lacks sufficient balance to refund the cash.
- **Context:** Deleting a cash invoice requires reversing the financial cash collection from the network's wallet to maintain accounting integrity. If the network wallet does not have enough funds:
  - *Option A (Negative Overdraft):* Allow the network wallet balance to go negative (`-$amount`). This violates the core accounting invariant that networks are creditors and suppliers, not borrowers, and creates uncollectible platform risk.
  - *Option B (Strict Blocking - نظام المنع الصارم):* Block invoice deletion (`DomainException`) until the network wallet is credited or replenished with sufficient balance to refund the cash.
- **Decision:** **Option B (`نظام المنع الصارم`)**. Networks are strictly forbidden from having negative wallet balances or overdrafts. If the network wallet balance is lower than the cash invoice total, deletion is rejected with an authoritative Arabic error message.
- **Consequence:** Preserves the fundamental financial invariant that network wallets can never become debtors, avoiding unbacked balance deductions.


## DEC-QR-01: Network invitations must target one exact POS
- **Status:** **APPROVED**
- **Date:** 2026-11-22
- **User Decision Date:** 2026-11-21
- **Scope:** Network-to-POS commercial relationship invitation via encrypted QR.
- **Decision:** A Network invitation must be bound to exactly one Network and one explicitly resolved POS. The issuer enters the POS commercial business code; the API resolves only that exact POS after checking the issuer's active Network authorization, target POS activity, and absence of an existing link. No public POS directory and no untargeted invitation are allowed.
- **Security:** The encrypted challenge binds Network ID, POS ID, challenge UUID, nonce, and exact expiry. Preview/confirmation revalidate the same POS binding. The status endpoint exposes only lifecycle status, label, and expiry; it never returns the payload or target identifiers.
- **Consequence:** Both pos_id and pos_uuid are mutually exclusive target inputs and exactly one is required. pos_uuid on this wire contract is the formatted POS commercial business code, not a generic RFC UUID.
- **Supersession note (2026-11-22):** The request-selector consequence above is superseded by DEC-QR-02. The exact-target security invariant remains in force; only the public request identifier changed to pos_uuid exclusively.


## DEC-QR-02: Use POS commercial business code as the sole QR request identifier
- **Status:** **`APPROVED`**
- **Date:** 2026-11-22
- **User Decision Date:** 2026-11-22
- **Supersedes:** DEC-QR-01's mutually-exclusive `pos_id` / `pos_uuid` request-selector rule. The exact-target, single-Network/single-POS security invariant remains unchanged.
- **Subject:** Canonical POS identity for Network–POS encrypted QR invitation creation, preview, and confirmation.
- **Decision:** `pos_uuid` is the only accepted POS identifier in all three request contracts. In this workflow it means the formatted POS commercial business code (for example `POS-YYMM-00001-AB12`), not an RFC UUID. `pos_id` is prohibited in these requests and must be rejected even when `pos_uuid` is also present.
- **Internal boundary:** Laravel resolves the business code to the internal POS model and continues using the database `pos_id` for foreign keys, challenge binding, row relationships, and transactional integrity. Response projections may retain a narrowly scoped `pos_id` where required by the existing response contract; this does not make it a request selector.
- **Validation convention:** The three FormRequests use their ordinary `rules()` declarations and the same project validation conventions. Do not introduce `withValidator()` for this feature. Missing `pos_uuid` is rejected by its required rule; submitted `pos_id` is rejected by the `missing` rule.
- **Flutter contract:** Create, preview, and confirmation UseCases/DataSources send only `posUuid`; the POS scanner flow obtains the active POS commercial code from the authorized POS entity and retains internal `posId` only for local context consistency checks and unrelated endpoints that require it.
- **Security:** The server remains authoritative for target resolution, activity, membership, tenant authorization, challenge binding, replay prevention, and expiry. No public POS directory or untargeted challenge is introduced.
- **Consequence:** All canonical API and Flutter contract references, feature tests, and implementation call sites must agree on this single request identifier. No compatibility alias or fallback to `pos_id` is permitted.


## DEC-05: Remove POS Credit, Debt, and Repayment Domain
- **Status:** SUPERSEDED
- **Decision:** This broad removal decision is superseded by DEC-06. It must not be used to remove CREDIT invoice support or credit-limit controls.
- **Rationale:** The user's later explicit instruction narrowed the requested removal to the POS Repayment collection domain only.
- **Boundary:** Retain the approved CASH/CREDIT invoice and receivables behavior defined by DEC-06.


## DEC-06: Remove POS Repayment Collection Domain Only
- **Status:** APPROVED
- **Date:** 2026-11-22
- **Decision:** Remove POS repayment collection, reversal, repayment records, invoice allocations/FIFO allocation, repayment-specific routes, screens, reports, and repayment document snapshots only. Preserve POS CASH and CREDIT sale types, initial partial payment at invoice creation/approval, invoice payment fields, POS credit-limit configuration/enforcement, and accounting/wallet settlement rules required by CREDIT invoices.
- **Rationale:** The user's explicit correction is to delete Repayment, not to disable credit sales or receivables. Invoice-level payment state and initial payment are part of the invoice lifecycle and are not repayment collection.
- **Financial authority:** Laravel remains the sole authority for invoice totals, paid/remaining amounts, payment status, credit-limit validation, wallet mutation, and accounting entries. Flutter may collect an initial paid amount where the API contract permits it, but must not calculate authoritative balances.
- **Boundary:** No POS repayment collection/allocation/reversal workflow may be restored. Do not interpret this decision as permission to remove CREDIT, credit limits, partial initial payments, receivables aging, or invoice reversal semantics unrelated to repayment records.


## DEC-SESSION-SCHED-01: Allow operational app activity ticks without exposing scheduler administration
- **Status:** APPROVED
- **Date:** 2026-11-26
- **User Authorization:** The user explicitly instructed implementation after reviewing the proposed separation between operational activity ticks and Platform administration.
- **Decision:** Keep `POST /api/v1/scheduled-tasks/catchup` as the application's activity-tick endpoint, but authorize operational callers with the distinct `scheduled_tasks.run_activity_tick` capability and a server-side active Network/POS membership check. Operational callers execute only the existing bounded `runActivityTick()` service method; they cannot force execution or select a task. Platform administration remains gated by `scheduled_tasks.run_catchup` and retains the full `runAllCatchupTasks(force, task)` behavior. Scheduler listings and statistics remain Platform-only.
- **Session contract:** Session middleware emits stable `SESSION_CREDENTIAL_MISSING`, `SESSION_DEVICE_ID_MISSING`, and generic `SESSION_INVALID` codes. Unknown, expired, revoked, and device-mismatched credentials share the generic terminal code to avoid revealing token existence. Missing credentials are not proof that a stored client session has expired.
- **Client safety:** The Flutter client terminates only the matching current session generation on `SESSION_INVALID`. Requests from earlier generations cannot terminate a newer login. Background scheduler and FCM completions must not restore local state after their originating session has ended.
- **Consequence:** Keep the bounded operation and administrative catch-up distinct. Never grant `scheduled_tasks.run_catchup` to operational roles or infer administrative authority from request body fields.


## DEC-MEMBERSHIP-VALIDATION-01: Share Network/POS membership request validation

- **Status:** IMPLEMENTED IN DRAFT PR #49; exact-head verification pending after documentation-only updates.
- **Date:** 2026-11-27
- **Decision:** Use the same four Laravel FormRequests for Network and POS membership creation, role changes, status changes, and removal. Keep input validation at the HTTP boundary; keep actor authorization, tenant scoping, assignable-role rules, and membership invariants in the existing middleware/services.
- **Compatibility:** Preserve existing endpoint paths, request field names, response shapes, database schema, and financial behavior. The refactor must not broaden permissions or bypass service-level checks.
- **Verification boundary:** Laravel CI passed for the code commit `f6fb20852fcc89c201bc6dc81befadc0e1b4e11a`. The current PR HEAD includes subsequent documentation-only commits and must receive an exact-head CI result before merge.
