# NetSales — Current Project State & Authorization Snapshot

**Last Verified Date:** 2026-11-04  
**Active Working Tree Status:** M4 QR Linking Verified & M5 Targeted Backend Security Remediation Completed  
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
| **Milestone 5:** Packages & Contractual Pricing | `BACKEND SECURITY REMEDIATION COMPLETED (HALTED AT M5.1 BOUNDARY)` | Targeted backend security remediation addressing Voucher Packages and Contractual Discounts tenant isolation, POS privacy, active commercial link enforcement, and stateless controller resolution verified (14 tests, 103 assertions). Frontend package catalog UI and full M5 features halted awaiting explicit user authorization. |
| **Milestone 6:** Procurement Requests Lifecycle | `NOT STARTED` | Scheduled after Milestone 5 completion & approval. |
| **Milestone 7:** Invoicing & Accounting | `NOT STARTED` | Scheduled after Milestone 6 completion & approval. |
| **Milestone 8:** Wallets, Credit, Debts & Repayments | `NOT STARTED` | Scheduled after Milestone 7 completion & approval. |

---

## 2. Git Authority References

- **`netsales_api` (Laravel Backend):**
  - Branch: `main` (tracking `origin/main`)
  - HEAD Commit: `c79877c53edbbcafb709f514b515054ed54f1340`
  - Commit Message: `docs: log custom widget audit and apply Pint formatting fixes` (2026-11-03 20:05:59 +0300)
  - Working Tree: Clean (`nothing to commit, working tree clean`).
- **`netsales` (Flutter Client):**
  - Branch: `main` (tracking `origin/main`)
  - Current Local HEAD Commit: `23bff7b3288cb09b09bb93315698e711f54cf170`
  - Commit Message: `feat(commercial): complete M4 commercial entities & enforce project-wide custom widget compliance` (2026-11-03 20:07:26 +0300)
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
