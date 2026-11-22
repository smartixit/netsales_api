# POS invoice payment and CREDIT invariants

## Scope boundary

Removing POS Repayment collection does not remove POS CREDIT sales, invoice receivables, initial payments, or credit-limit enforcement. Repayment collection is a separate post-invoice workflow.

Every POS sale invoice continues to expose and persist:

- `sale_type`: `CASH` or `CREDIT`.
- `invoice_total_amount`: authoritative invoice total.
- `paid_amount`: amount settled at invoice issuance, including any eligible initial partial payment and any amount covered by the POS wallet balance.
- `remaining_amount`: authoritative outstanding invoice amount.
- `payment_status`: `PAID`, `PARTIALLY_PAID`, or `UNPAID`.
- `payment_status_label` and `is_fully_paid`: API-derived presentation/state fields.

The canonical serialization is `SaleInvoiceResource`. These fields are part of the invoice API contract and must remain available to invoice details, lists, and Flutter consumers.

## CASH behavior

A CASH invoice is fully settled at issuance:

- `paid_amount = invoice_total_amount`
- `remaining_amount = 0`
- `payment_status = PAID`
- `is_fully_paid = true`

## CREDIT behavior

A CREDIT invoice may be unpaid, partially paid, or fully covered at issuance:

- The request's sale type is authoritative; approval must not silently change it.
- An optional initial `paid_amount` may be accepted on direct invoice creation or procurement approval where the endpoint contract supports it.
- The initial payment cannot be negative or exceed the invoice total.
- The backend computes the effective paid amount, remaining amount, and payment status using integer minor-unit arithmetic.
- The backend enforces the POS wallet's configured credit limit before committing any wallet, invoice, ledger, or outbox mutation.
- Only the amount actually settled at issuance is credited to the network wallet.
- The unpaid portion remains represented by the invoice receivable and POS wallet debit under the existing approved accounting rules.

Expected states:
- No initial payment and no wallet coverage: `UNPAID`, with `paid_amount = 0`.
- Partial initial/wallet coverage: `PARTIALLY_PAID`, with `0 < paid_amount < invoice_total_amount`.
- Full coverage: `PAID`, with `remaining_amount = 0`.

## Financial authority

Laravel is the sole authority for invoice totals, discounts, payment calculations, receivables, credit-limit checks, wallet mutations, and accounting entries. Flutter may collect and transmit an initial payment amount only where the endpoint contract permits it. Flutter must display the server's returned payment fields and must not calculate authoritative financial balances.

## Explicitly removed functionality

The following POS Repayment collection capabilities remain removed:

- Repayment collection and reversal records.
- Repayment-to-invoice allocation records and FIFO allocation.
- Repayment collection/reversal endpoints and workflows.
- Repayment-specific reports, screens, and document snapshots.

Preserving CREDIT does not authorize restoring any of these repayment capabilities.

## Merge invariant

Any change that removes or renames `sale_type`, `paid_amount`, `remaining_amount`, `payment_status`, `payment_status_label`, or `is_fully_paid` from the POS invoice contract is out of scope and must block the removal PR. Tests must cover CASH full payment, CREDIT unpaid, CREDIT partial payment, credit-limit acceptance/rejection, procurement CASH/CREDIT, and reversal behavior without repayment allocations.
