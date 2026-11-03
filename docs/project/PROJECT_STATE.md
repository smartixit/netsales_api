# NetSales — Current Project State & Authorization Snapshot

**Last Verified Date:** 2026-11-03  
**Active Working Tree Status:** Milestone 3 Final Acceptance Verified & Reconciled  
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
| **Milestone 4:** Commercial Entities — Networks, POS, Relationships | `NOT STARTED` | Scheduled after Milestone 3 completion & approval. Strict boundary: NO credit limits or contractual pricing. |
| **Milestone 5:** Packages & Contractual Pricing | `NOT STARTED` | Scheduled after Milestone 4 completion & approval. |
| **Milestone 6:** Procurement Requests Lifecycle | `NOT STARTED` | Scheduled after Milestone 5 completion & approval. |
| **Milestone 7:** Invoicing & Accounting | `NOT STARTED` | Scheduled after Milestone 6 completion & approval. |
| **Milestone 8:** Wallets, Credit, Debts & Repayments | `NOT STARTED` | Scheduled after Milestone 7 completion & approval. |

---

## 2. Git Authority References

- **`netsales_api` (Laravel Backend):**
  - Branch: `main` (tracking `origin/main`)
  - HEAD Commit: `87afb29f9fe6f8078e220ee020832988d82611f3` (Parent: `181464867fada7ab5a468cd6ff47f746d52257cf`)
  - Commit Message: `Fix issues of session_token` (2026-11-03 16:38:50 +0300)
  - Working Tree: 4 files modified locally (`app/Http/Controllers/API/V1/AuthController.php`, `app/Models/UserSession.php`, `app/Repositories/UserSessionRepository.php`, `tests/Feature/IdentityAndAccessFoundationTest.php`).
- **`netsales` (Flutter Client):**
  - Branch: `main` (tracking `origin/main`)
  - Current Local HEAD Commit: `2cfd45b39534a109317d1a1198f659d8d8e3c1fa` (Parent: `60ef551ef6586de76a9963224b0114d05fae646a`)
  - Commit Message: `Final Closeout Reconciliation` (2026-11-03 16:10:20 +0300)
  - Working Tree: Clean (`nothing to commit, working tree clean`).

---

## 3. Pending Decisions Register

All architectural and business decisions requiring future implementation approval remain strictly `PROPOSED`:

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
Halt at the Milestone 3 closeout boundary. Present the consolidated final acceptance report and await explicit user authorization before starting **Milestone 4 (Commercial Entities — Networks, POS, Relationships)**.
