---
trigger: always_on
description: Financial authority, transactional boundaries, and dual-wallet cash accounting rules.
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
- Every operation modifying financial state (invoicing, wallet adjustment, cash invoice settlement) **must** run inside a database transaction (`DB::transaction(...)`).
- Balances and affected invoice records must be queried and locked using pessimistic row locking (`lockForUpdate()`) to prevent race conditions and double-settlement anomalies.

## 3. Cash-Only POS Sales
- POS sales invoices are cash-settled only. Credit sales, credit limits, debt ledgers, repayment collection, reversal, FIFO allocation, and receivable aging are not supported.
- Reject any request attempting to create or modify a POS credit sale; do not create a negative POS wallet balance through invoice issuance.
