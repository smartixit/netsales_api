---
trigger: always_on
description: Financial authority, transactional boundaries, dual wallets, and FIFO repayment allocation rules.
---

# Rule: Financial and Accounting Invariants

## 1. 100% Server Financial Authority
- The Laravel backend is the **sole authority** for all calculations involving:
  - Base package prices and contracted merchant discounts.
  - Procurement request totals and invoice line item amounts.
  - Wallet balances, deposit credits, and withdrawal debits.
  - POS debt balances, credit limits, and aging bucket calculations.
  - FIFO invoice repayment allocations and ledger entries.
- Flutter clients are strictly read-only presentation consumers of financial figures.

## 2. Transaction Boundaries and Atomic Execution
- Every operation modifying financial state (invoicing, wallet adjustment, debt repayment settlement) **must** run inside a database transaction (`DB::transaction(...)`).
- Balances and affected invoice records must be queried and locked using pessimistic row locking (`lockForUpdate()`) to prevent race conditions and double-settlement anomalies.

## 3. Debt Settlement and FIFO Ordering
- Repayments are authoritatively allocated to outstanding invoices in strict First-In, First-Out (FIFO) chronological order by invoice issue date (`issued_at ASC` / `invoice_id ASC`).
- Previews are strictly non-binding and informational. The final settlement allocation is determined atomically inside the collection transaction under row locks.
- Never write accounting policies or ledger adjustments that bypass the core `DebtRepaymentService` and `PosWalletLogRepository`.
