# NetSales — Current Project State & Authorization Snapshot

**Last Verified Date:** 2026-11-03  
**Active Working Tree Status:** Milestone 2 Foundation Complete & Verified  
**Canonical Repository:** `smartixit/netsales_api`  
**Client Repository:** `smartixit/netsales`  

---

## 1. Milestone & Authorization Tracking

| Milestone / Task | Status | Authorization Boundary |
|---|---|---|
| **Milestone 1:** Product Discovery & Blueprint | `ACCEPTED AS WORKING BASELINE` | Accepted with known limitations for forward progress. |
| **Governance Foundation:** Persistent Memory & Rules | `COMPLETED & VERIFIED` | User approved; full invariant rules, skills, and doc suite active. |
| **Milestone 2:** Shell Navigation & Workspace Isolation | `COMPLETED & VERIFIED` | **USER APPROVED — IMPLEMENT MILESTONE 2**. Implemented, tested, and verified. |
| **Milestone 3:** Procurement Requests Lifecycle | `NOT STARTED` | Pending explicit user approval to begin Milestone 3. |
| **Milestone 4:** Invoicing, Wallets & Debt Settlements | `NOT STARTED` | Pending Milestone 3 completion & approval. |

---

## 2. Git Authority References

- **`netsales_api` (Laravel Backend):**
  - Branch: `main`
  - HEAD Commit: `1e182ce9c2c805ecd62218586a13ae5cfa60fed1`
  - Working Tree: Clean (Preserved with zero backend modifications)
- **`netsales` (Flutter Client):**
  - Branch: `main`
  - HEAD Commit: `b9e1f33ede30fd6852d1555d763db4133a51a61d`
  - Working Tree: Milestone 2 files implemented and verified (`flutter analyze` and `flutter test` passing with 0 errors).

---

## 3. Pending Decisions Register

All architectural and business decisions requiring future implementation approval remain strictly `PROPOSED`:

- **`DEC-01` (`PROPOSED`):** Add `role_scope` to `AuthService::me()` response.
- **`DEC-02` (`PROPOSED`):** Seed dedicated least-privilege permission matrix for `NETWORK_ACCOUNTANT` and `POS_ACCOUNTANT`.
- **`DEC-03` (`PROPOSED`):** Enforce `PaymentType` enum validation and model casting in repayment settlement.
- **`DEC-04` (`PROPOSED`):** Implement informational server-side FIFO repayment preview endpoint (`POST /pos-debts/repayments/preview`).

---

## 4. Known Project Limitations (Preserved from Baseline)
1. `RoleResource` currently omits `scope` field; `AuthService::me()` omits `role_scope`.
2. `RolePermissionSeeder` omits explicit assignments for `NETWORK_ACCOUNTANT` and `POS_ACCOUNTANT`.
3. `CollectRepaymentRequest` validates `payment_method` as an unconstrained string, not using `Rule::enum(PaymentType::class)`.
4. No FIFO repayment preview endpoint currently exists in the Laravel API.
5. In Flutter, old components `dashboard_trend_window.dart` and `custom_pos_selector_field.dart` were permanently removed per user directives.

---

## 5. Next Authorized Action
Halt at the Milestone 2 approval boundary. Present the complete Milestone 2 handoff report to the user and await explicit authorization before starting **Milestone 3 (Procurement Requests Lifecycle)**.
