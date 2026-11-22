# Invoice payment fields remain part of the invoice domain

## Scope boundary

Removing the standalone POS debt and repayment domain does not remove invoice payment data. These are separate responsibilities.

Every sale invoice continues to expose and persist:

- `invoice_total_amount`: authoritative invoice total.
- `paid_amount`: amount settled against this invoice.
- `remaining_amount`: outstanding amount on this invoice.
- `payment_status`: authoritative invoice payment state.
- `is_fully_paid`: derived by the API resource from the persisted remaining amount.

The canonical serialization is `SaleInvoiceResource`. Its payment fields are part of the invoice API contract and must remain available to invoice details, lists, and consumers such as Flutter.

## Current cash-only POS policy

POS invoices and procurement-generated invoices are cash-settled only. Therefore, when an invoice is successfully created under the current policy:

- `paid_amount = invoice_total_amount`
- `remaining_amount = 0`
- `payment_status = PAID`
- `is_fully_paid = true`

The client must not submit or override authoritative payment amounts. Laravel calculates and persists these values. Prohibiting client-supplied `paid_amount` is a financial-authority safeguard; it is not permission to remove the field from the invoice model, resource, API response, tests, or Flutter invoice model/UI.

## Explicitly removed functionality

The following belong to the retired standalone POS debt/repayment domain and are not restored by preserving invoice payment fields:

- POS repayment collection and reversal records.
- Repayment-to-invoice allocation records and FIFO allocation.
- POS debt aging and outstanding-debt workflows.
- Repayment routes, screens, reports, and document snapshots.

## Merge invariant

Any change removing or renaming `paid_amount`, `remaining_amount`, `payment_status`, or `is_fully_paid` from the invoice contract is out of scope and must block this removal PR. Tests must assert that a cash invoice response still contains all four fields with mutually consistent values.
