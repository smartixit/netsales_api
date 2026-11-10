# NetSales — Financial Invariants & Accounting Policy Rules

## 1. Absolute Financial Authority Principles
- **Sole Source of Financial Truth:** The Laravel API backend is the sole authority for all pricing calculations, contractual package discounts, sales invoice totals, dual-wallet balances, credit limit facilities, debt aging tiers, and FIFO repayment allocations.
- **Strict Client Prohibition:** The Flutter client is strictly prohibited from calculating or projecting authoritative accounting figures. Flutter simply displays values returned directly by the API.

---

## 2. Procurement Pricing and Invoice Generation
1. **Contractual Tariff Resolution:**
   - Package base price is resolved from `voucher_packages`.
   - Contractual discount is resolved from `network_pos_package_discounts` for the specific `(network_id, pos_id, package_id)` tuple.
   - Net unit price = `package_price - contractual_discount`.
2. **Approval and Invoicing Atomicity:**
   - When a procurement `Request` is approved (`RequestStatus::APPROVED`), a corresponding `SaleInvoice` is generated atomically within a database transaction.
   - Invoicing is decoupled: `SaleInvoice` payment status (`PAID`, `PARTIALLY_PAID`, `UNPAID`) evolves independently of the Request's immutable `APPROVED` status.

---

## 3. Dual-Wallet Accounting & Credit Limit Governance
- **Network Wallet:** Represents network provider collected funds and master balance.
- **POS Wallet:** Represents POS merchant prepayment funds.
- **Wallet Mutations:** All credits (`DEPOSIT`) and debits (`WITHDRAW`) are written to `wallet_logs` with a unique transaction reference and caller user ID.
- **Credit Limit Policy:** When a POS places a credit request (`SaleType::CREDIT`), the total outstanding debt plus the requested invoice total must not exceed the POS's contracted `credit_limit`.

---

## 4. Debt Aging & FIFO Repayment Settlement
1. **Aging Tier Classification:** Outstanding unpaid invoices are categorized chronologically by issue date:
   - Current (0–30 days)
   - 31–60 days
   - 61–90 days
   - 90+ days
2. **Authoritative FIFO Allocation:**
   - Repayments are applied strictly in First-In, First-Out (FIFO) chronological order (`issued_at ASC`, `id ASC`).
   - Oldest invoices are fully settled before allocating surplus repayment amounts to subsequent invoices.
3. **Concurrency & Locking Discipline:**
   - Repayment collections must lock affected invoices and wallet balances using pessimistic row locking (`lockForUpdate()`) inside `DB::transaction(...)`.
4. **Preview Non-Binding Qualification (DEC-04):**
   - Any server-side repayment preview is strictly non-binding and informational. Because concurrent transactions can mutate invoice balances, the final settlement is determined atomically at collection time.


## Subscription Trial and Dunning Controls
Trial eligibility, redemption, and adjustments are server-side transactional controls. A unique redemption subject is enforced by the database for each policy and configured redemption scope. Published policy versions and subscription policy snapshots are immutable. Dunning grace and suspension use the version assigned to the subscription; they must never read generic `system_settings` keys.
