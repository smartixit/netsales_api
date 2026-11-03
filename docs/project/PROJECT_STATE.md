# NetSales — Current Project State & Authorization Snapshot

**Last Verified Date:** 2026-11-03  
**Active Working Tree Status:** Milestone 2 Foundation Delivered & Reconciled  
**Canonical Repository:** `smartixit/netsales_api`  
**Client Repository:** `smartixit/netsales`  

---

## 1. Milestone & Authorization Tracking

| Milestone / Task | Status | Authorization Boundary |
|---|---|---|
| **Milestone 1:** Product Discovery & Blueprint | `ACCEPTED AS WORKING BASELINE` | Accepted with known limitations for forward progress. |
| **Governance Foundation:** Persistent Memory & Rules | `COMPLETED & VERIFIED` | User approved; full invariant rules, skills, and doc suite active. |
| **Milestone 2:** Clean Flutter Foundation, GetX Architecture, Workspace Isolation & Adaptive Navigation | `ACCEPTED AS WORKING BASELINE` | Implemented, tested, and reconciled. Client routing only; server remains sole security boundary. |
| **Milestone 3:** Identity & Access Foundation | `IN PROGRESS` | User authorized. Establishing unified session lifecycle, devices management, and test suite. |
| **Milestone 4:** Commercial Entities — Networks, POS, Relationships | `NOT STARTED` | Scheduled after Milestone 3 completion & approval. |
| **Milestone 5:** Packages & Contractual Pricing | `NOT STARTED` | Scheduled after Milestone 4 completion & approval. |
| **Milestone 6:** Procurement Requests Lifecycle | `NOT STARTED` | Scheduled after Milestone 5 completion & approval. |
| **Milestone 7:** Invoicing & Accounting | `NOT STARTED` | Scheduled after Milestone 6 completion & approval. |
| **Milestone 8:** Wallets, Credit, Debts & Repayments | `NOT STARTED` | Scheduled after Milestone 7 completion & approval. |

---

## 2. Git Authority References

- **`netsales_api` (Laravel Backend):**
  - Branch: `main`
  - HEAD Commit: `1e182ce9c2c805ecd62218586a13ae5cfa60fed1`
  - Working Tree: Clean (Preserved with zero backend modifications)
- **`netsales` (Flutter Client):**
  - Branch: `main`
  - Current Local HEAD Commit: `8742ef9b2787bebab287d34604f813b2e3f796dc`
  - Tracking: `origin/main` (Local commits include Milestone 2 feature implementation `7f9ddf93132344d8686b66dcbc379ed2d082c6bd`)
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
1. `WorkspaceGuard` and `WorkspaceService` provide client-side workspace routing and presentation only; the authoritative security boundary remains 100% on the Laravel API.
2. Visual QA for live rendered UI is marked **NOT VERIFIED — REQUIRES ACTIVE EMULATOR/DEVICE** as no running Android emulator or mobile device was connected during execution.
3. In `main.dart`, global orientation is locked to portrait (`portraitUp`, `portraitDown`), so tablet landscape rendering is not enabled by default.
4. Pre-existing non-workspace compilation issues in `lib/core/services/app_crypto_service.dart`, `lib/features/auth/`, and `lib/features/onboarding/` exist outside the Milestone 2 workspace feature.

---

## 5. Next Authorized Action
Halt at the Milestone 2 closeout boundary. Present the final reconciliation report and await user authorization before starting **Milestone 3 (Identity & Access Foundation)**.
