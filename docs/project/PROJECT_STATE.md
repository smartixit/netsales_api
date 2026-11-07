# NetSales — Current Project State & Authorization Snapshot

**Last Verified Date:** 2026-11-07  
**Active Working Tree Status:** M6 Procurement Requests Lifecycle Frontend Completed & Verified (201 tests passed, 0 issues)  
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
| **Milestone 7:** Invoicing & Accounting | `NOT STARTED` | Scheduled after Milestone 6 completion & approval. |
| **Milestone 8:** Wallets, Credit, Debts & Repayments | `NOT STARTED` | Scheduled after Milestone 7 completion & approval. |

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
