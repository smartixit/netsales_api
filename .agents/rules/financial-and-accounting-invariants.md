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
  - POS receivables, credit-limit checks, and aging bucket calculations.
  - Initial payment allocation and wallet/accounting entries at invoice issuance.
- Flutter may collect an initial payment amount where the API contract permits it, but must never calculate or override authoritative totals, paid/remaining balances, payment status, credit capacity, wallet balances, or ledger entries.

## 2. Transaction Boundaries and Atomic Execution
- Every operation modifying financial state (invoicing, wallet adjustment, CASH/CREDIT invoice issuance and settlement) **must** run inside a database transaction (`DB::transaction(...)`).
- Balances and affected invoice records must be queried and locked using pessimistic row locking (`lockForUpdate()`) to prevent race conditions and double-settlement anomalies.

## 3. POS CASH and CREDIT Sales
- POS sale invoices and procurement requests support CASH and CREDIT according to the agreed API contract.
- CASH invoices are fully settled at issuance. CREDIT invoices may be unpaid, partially paid through an allowed initial payment, or fully covered by the POS wallet balance.
- Laravel validates the configured POS credit limit and computes paid_amount, remaining_amount, payment_status, wallet mutations, and accounting entries atomically using integer minor units.
- Repayment collection, reversal, repayment-to-invoice allocation, and FIFO collection workflows are removed and must not be reintroduced.
- Receivables and aging are invoice-level reporting concerns and are not the repayment collection domain.
