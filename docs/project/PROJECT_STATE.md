# NetSales — Current Project State & Authorization Snapshot

**Last Verified Date:** 2026-11-03  
**Active Working Tree Status:** Milestone 3 Security Hardened & Fully Reconciled  
**Canonical Repository:** `smartixit/netsales_api`  
**Client Repository:** `smartixit/netsales`  

---

## 1. Milestone & Authorization Tracking

| Milestone / Task | Status | Authorization Boundary |
|---|---|---|
| **Milestone 1:** Product Discovery & Blueprint | `ACCEPTED AS WORKING BASELINE` | Accepted with known limitations for forward progress. |
| **Governance Foundation:** Persistent Memory & Rules | `COMPLETED & VERIFIED` | User approved; full invariant rules, skills, and doc suite active. |
| **Milestone 2:** Clean Flutter Foundation, GetX Architecture, Workspace Isolation & Adaptive Navigation | `ACCEPTED AS WORKING BASELINE` | Implemented, tested, and reconciled. Client routing only; server remains sole security boundary. |
| **Milestone 3:** Identity & Access Foundation | `COMPLETED & VERIFIED (WITH LIMITATIONS)` | Completed with security hardening (SHA-256 session token hashing at rest, device UUID enforcement, session revocation, absolute expiration). Multi-tenant active workspace context switching is bounded by DEC-01 (`PROPOSED`). |
| **Milestone 4:** Commercial Entities — Networks, POS, Relationships | `NOT STARTED` | Scheduled after Milestone 3 completion & approval. Strict boundary: NO credit limits or contractual pricing. |
| **Milestone 5:** Packages & Contractual Pricing | `NOT STARTED` | Scheduled after Milestone 4 completion & approval. |
| **Milestone 6:** Procurement Requests Lifecycle | `NOT STARTED` | Scheduled after Milestone 5 completion & approval. |
| **Milestone 7:** Invoicing & Accounting | `NOT STARTED` | Scheduled after Milestone 6 completion & approval. |
| **Milestone 8:** Wallets, Credit, Debts & Repayments | `NOT STARTED` | Scheduled after Milestone 7 completion & approval. |

---

## 2. Git Authority References

- **`netsales_api` (Laravel Backend):**
  - Branch: `main` (tracking `origin/main`)
  - HEAD Commit: `181464867fada7ab5a468cd6ff47f746d52257cf` (Parent: `28fbc7b318655518e286d4ac4dd1a8cc625eec60`)
  - Commit Message: `Identity & Access Foundation` (2026-11-03 15:32:12 +0300)
  - Working Tree: Security hardening changes applied locally (Deterministic SHA-256 token hashing at rest, safe migration, 10-method feature test suite passed).
- **`netsales` (Flutter Client):**
  - Branch: `main` (tracking `origin/main`)
  - Current Local HEAD Commit: `2cfd45b39534a109317d1a1198f659d8d8e3c1fa` (Parent: `60ef551ef6586de76a9963224b0114d05fae646a`)
  - Commit Message: `Final Closeout Reconciliation` (2026-11-03 16:10:20 +0300)
  - Working Tree: Clean (`nothing to commit, working tree clean`)

---

## 3. Pending Decisions Register

All architectural and business decisions requiring future implementation approval remain strictly `PROPOSED`:

- **`DEC-01` (`PROPOSED`):** Add `role_scope` to `AuthService::me()` response.
- **`DEC-02` (`PROPOSED`):** Seed dedicated least-privilege permission matrix for `NETWORK_ACCOUNTANT` and `POS_ACCOUNTANT`.
- **`DEC-03` (`PROPOSED`):** Enforce `PaymentType` enum validation and model casting in repayment settlement.
- **`DEC-04` (`PROPOSED`):** Implement informational server-side FIFO repayment preview endpoint (`POST /pos-debts/repayments/preview`).

---

## 4. Known Project Limitations & Verification Status

1. **Multi-Tenant Context Switching Boundary:** Active tenant and workspace context switching remains bounded by DEC-01 (`PROPOSED`). Current backend isolates tenants strictly at the route policy and membership level (`NetworkWalletPolicy`, `PosWalletPolicy`, `NetworkMembershipService`), but does not have a global tenant-context header.
2. **Visual QA Status:** Visual QA for live rendered UI is marked **NOT VERIFIED — REQUIRES ACTIVE EMULATOR/DEVICE** due to zero running emulators during automated headless runs.
3. **Pre-Existing Backend Test Failures:** Full Laravel test suite passes 229 tests, with 3 failures belonging to Phase 4 financial debit balances and pricing approval logic (`Milestone4CConcurrencyAndDirectInvoiceDebtTest`, `NetworkRequestReviewAndApprovalTest`, `VoucherRequestAndInvoicingTest`), completely outside Milestone 3 identity scope.
4. **Absolute Session Expiration:** Session expiration is absolute from creation time; activity tracking updates `last_activity_at` for auditing but intentionally does not slide or extend `expires_at`.

---

## 5. Next Authorized Action
Halt at the Milestone 3 closeout boundary. Present the final 13-section security-hardening and reconciliation report and await explicit user authorization before starting **Milestone 4 (Commercial Entities — Networks, POS, Relationships)**.
