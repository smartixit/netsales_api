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
| **DEC-03** | 2026-11-02 | Strict PaymentType Enum Validation and Model Cast in Repayments | **`PROPOSED`** | Pending Review |
| **DEC-04** | 2026-11-02 | Server-Side Informational FIFO Repayment Preview Endpoint | **`PROPOSED`** | Pending Review |
| **BILL-TRIAL-01** | 2026-11-10 | Versioned Trial and Dunning Policy Engine | **`APPROVED`** | 2026-11-10 |

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
