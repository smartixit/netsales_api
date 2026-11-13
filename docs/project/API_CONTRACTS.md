# NetSales — Verified API Contracts & Serialization Standard

## 1. Verified API Serialization Standards
Laravel API endpoints use Eloquent API Resources (`app/Http/Resources/API/V1/`).
Responses provide typed raw values along with translated label fields (`*_label`).

### 1.1 Complete Verified 24-Enum Wire Catalog
The 24 backed PHP Enums (`app/Enums/`) have the following exact canonical wire values:

| # | Enum Class | Backed Type | Exact Wire Values (No Placeholders) | Wire Casing |
|---|---|---|---|---|
| 1 | `RoleKey` | `string` | `'SUPER_ADMIN'`, `'NETWORK_OWNER'`, `'NETWORK_USER'`, `'POS_AGENT'`, `'POS_USER'`, `'SUPPORT_AGENT'`, `'ACCOUNTANT'`, `'NETWORK_ACCOUNTANT'`, `'POS_ACCOUNTANT'` | UPPERCASE |
| 2 | `RoleScope` | `string` | `'PLATFORM'`, `'NETWORK'`, `'POS'` | UPPERCASE |
| 3 | `UserStatus` | `string` | `'ACTIVE'`, `'INACTIVE'`, `'PENDING'`, `'BANNED'` | UPPERCASE |
| 4 | `UserDeviceStatus` | `string` | `'ACTIVE'`, `'INACTIVE'`, `'PENDING'`, `'BANNED'` | UPPERCASE |
| 5 | `DeviceType` | `string` | `'ANDROID'`, `'IOS'`, `'WEB'`, `'WINDOWS'`, `'LINUX'`, `'MAC'`, `'UNKNOWN'` | UPPERCASE |
| 6 | `BanType` | `string` | `'TEMPORARY'`, `'PERMANENT'` | UPPERCASE |
| 7 | `OtpType` | `string` | `'RESET_PASSWORD'`, `'OTP_2FA'`, `'VERIFY_EMAIL'`, `'VERIFY_PHONE'` | UPPERCASE |
| 8 | `NetworkStatus` | `string` | `'ACTIVE'`, `'INACTIVE'`, `'PENDING'`, `'REJECTED'`, `'BANNED'` | UPPERCASE |
| 9 | `PosStatus` | `string` | `'ACTIVE'`, `'INACTIVE'`, `'PENDING'`, `'REJECTED'`, `'BANNED'` | UPPERCASE |
| 10 | `RequestStatus` | `string` | `'PENDING'`, `'APPROVED'`, `'REJECTED'`, `'CANCELLED'` | UPPERCASE |
| 11 | `SaleType` | `string` | `'CASH'`, `'CREDIT'` | UPPERCASE |
| 12 | `InvoicePaymentStatus` | `string` | `'PAID'`, `'PARTIALLY_PAID'`, `'UNPAID'` | UPPERCASE |
| 13 | `PaymentType` | `string` | `'CASH'`, `'BANK'`, `'WALLET'` | UPPERCASE |
| 14 | `WalletOperationType` | `string` | `'DEPOSIT'`, `'WITHDRAW'` | UPPERCASE |
| 15 | `BankAccountType` | `string` | `'CURRENT'`, `'SAVING'`, `'WALLET'` | UPPERCASE |
| 16 | `ProviderSubscriptionStatus`| `string` | `'ACTIVE'`, `'QUEUED'`, `'EXPIRED'`, `'CANCELLED'`, `'SUSPENDED'` | UPPERCASE |
| 17 | `NotificationType` | `string` | `'SALE_INVOICE_CREATED'`, `'SALE_INVOICE_COMPLETED'`, `'SALE_INVOICE_CANCELLED'`, `'VOUCHER_REQUEST_CREATED'`, `'VOUCHER_REQUEST_APPROVED'`, `'VOUCHER_REQUEST_REJECTED'`, `'VOUCHER_REQUEST_CANCELLED'`, `'WALLET_DEPOSIT'`, `'WALLET_WITHDRAWAL'`, `'WALLET_TRANSFER'`, `'SUPPORT_TICKET_CREATED'`, `'SUPPORT_TICKET_REPLIED'`, `'SUPPORT_TICKET_CLOSED'`, `'NETWORK_APPROVED'`, `'NETWORK_REJECTED'`, `'POS_APPROVED'`, `'POS_REJECTED'`, `'SECURITY_LOGIN_ALERT'`, `'SECURITY_DEVICE_BLOCKED'`, `'SECURITY_PASSWORD_CHANGED'`, `'SYSTEM_ANNOUNCEMENT'`, `'GENERAL_INFO'` | UPPERCASE |
| 18 | `SettingCategory` | `string` | `'GENERAL'`, `'FINANCIAL'`, `'SECURITY'`, `'NOTIFICATION'`, `'SUBSCRIPTION'`, `'SYSTEM'`, `'BACKUP'` | UPPERCASE |
| 19 | `ValueType` | `string` | `'TEXT'`, `'INTEGER'`, `'BOOLEAN'`, `'DECIMAL'`, `'JSON'`, `'DATETIME'` | UPPERCASE |
| 20 | `SmsMessageType` | `string` | `'receipt'`, `'payment'`, `'pos_withdraw'`, `'expense'`, `'sale_invoice'`, `'voucher_request'`, `'low_balance'`, `'daily_summary'`, `'monthly_summary'`, `'subscription_expiry'`, `'otp'`, `'admin_broadcast'` | lowercase |
| 21 | `BackupStatus` | `string` | `'QUEUED'`, `'RUNNING'`, `'SUCCESS'`, `'FAILED'` | UPPERCASE |
| 22 | `BackupType` | `string` | `'AUTO'`, `'MANUAL'` | UPPERCASE |
| 23 | `ScheduledTaskStatus` | `string` | `'sent'`, `'completed'`, `'failed'`, `'skipped'` | lowercase |
| 24 | `ScheduledTaskType` | `string` | `'subscriptions_expire_outdated'`, `'subscriptions_notify_expiring'`, `'reports_daily_sms'`, `'reports_monthly_sms'`, `'alerts_low_balance'`, `'backup_auto'`, `'cleanup_user_sessions'`, `'cleanup_expired_otp'`, `'security_lift_bans'`, `'system_prune_logs'` | snake_case |
| 25 | `LinkingChallengeStatus` | `string` | `'PENDING'`, `'CONSUMED'`, `'EXPIRED'`, `'REVOKED'` | UPPERCASE |

---

## 2. Verified Resource Field Names & Discrepancies

| Resource Class | Field Name | Data Type | Backing PHP Enum | Label Provided? | Classification |
|---|---|---|---|---|---|
| `RequestResource` | `status` | `string` | `RequestStatus` | Yes (`status_label`) | True Status Badge |
| `RequestResource` | `sale_type` | `string` | `SaleType` | Yes (`sale_type_label`) | Contextual Chip |
| `SaleInvoiceResource` | `payment_status` | `string` | `InvoicePaymentStatus` | Yes (`payment_status_label`) | True Status Badge |
| `SaleInvoiceResource` | `sale_type` | `string` | `SaleType` | Yes (`sale_type_label`) | Contextual Chip |
| `NetworkResource` | `status` | `string` | `NetworkStatus` | Yes (`status_label`) | True Status Badge |
| `PosResource` | `status` | `string` | `PosStatus` | Yes (`status_label`) | True Status Badge |
| `RoleResource` | `role_key` | `string` | `RoleKey` | Yes (`role_key_label`) | Role Identifier (No scope field) |
| `NetworkWalletLogResource` | `log_operation` | `string` | `WalletOperationType` | Yes (`log_operation_label`) | Direction Indicator |
| `PosWalletLogResource` | `log_operation_type` | `string` | `WalletOperationType` | Yes (`log_operation_type_label`) | Direction Indicator |
| `PosWalletResource` | `accounting_status` | `string` | **None** (Model Method) | Yes (`accounting_status_label`) | Accounting Computed Status |
| `PosRepaymentResource` | `status` | `string` | **None** (Plain `'ACTIVE'`/`'REVERSED'`) | **No** | String Flag |
| `PosRepaymentResource` | `payment_method` | `string` | **None** (Plain string, e.g. `'CASH'`) | **No** | String Flag (DEC-03 proposed) |
| `BankAccountResource` | `status` | `boolean` | **None** (Cast as `(bool) $this->status`) | **No** | Boolean Active Flag |
| `NetworkPosResource` | `status` | `boolean` | **None** (Cast as `(bool) $this->status`) | **No** | Boolean Link Status |
| `BackupJobResource` | `status` | `string` | `BackupStatus` | Yes (`status_label`) | Backup Status Badge |
| `BackupJobResource` | `backup_type` | `string` | `BackupType` | Yes (`backup_type_label`) | Contextual Chip |
| `NetworkPosLinkingChallengeResource` | `status` | `string` | `LinkingChallengeStatus` | Yes (`status_label`) | True Status Badge |

---

## 3. Procurement & Debt Routes Verification
- **Request Modification Preview:** `POST /api/v1/requests/{id}/preview-modification` exists. Validates item modifications and returns calculated order preview before approval.
- **FIFO Repayment Collection:** `POST /api/v1/pos-debts/repayments` performs atomic FIFO allocation in `DebtRepaymentService`.
- **FIFO Repayment Preview:** **No preview route currently exists** in the active API. DEC-04 remains `PROPOSED`.

---

## 4. M4 POS–Network Linking Challenge API Contracts (QR Workflow)

### 4.1 Endpoints Specification

| Method | Endpoint | Authorization / Scope | Rate Limit | Purpose |
|---|---|---|---|---|
| `POST` | `/api/v1/network-pos/challenges` | `can:networks.edit` | `throttle:api-write` | Generate encrypted QR challenge invitation for Network |
| `POST` | `/api/v1/network-pos/challenges/preview` | `can:pos.view` | `throttle:api-write` | Decrypt and preview challenge metadata by POS |
| `POST` | `/api/v1/network-pos/challenges/confirm` | `can:pos.edit` | `throttle:api-write` | Atomically confirm relationship and consume challenge |
| `DELETE` | `/api/v1/network-pos/challenges/{uuid}` | `can:networks.edit` | `throttle:api-write` | Revoke active challenge by network owner |

### 4.2 Request & Response Serialization Schemas

#### 1. `POST /api/v1/network-pos/challenges`
- **Request Body (`CreateNetworkPosLinkingChallengeRequest`):**
  - `network_id` (`integer`, required): Network issuing the challenge.
  - `pos_id` (`integer`, optional): Specific targeted POS ID.
  - `pos_uuid` (`string/uuid`, optional): Specific targeted POS UUID.
  - `duration_minutes` (`integer`, optional, default: 15, min: 5, max: 60).
- **Response (201 Created):** Wraps `NetworkPosLinkingChallengeResource`.
  - `data.challenge_uuid` (`string/uuid`): Public reference ID.
  - `data.payload` (`string`): Asymmetrically sealed envelope prefixed with `NTS_LINK_V1_`.
  - `data.status` (`string`): `'PENDING'`.
  - `data.status_label` (`string`): `'قيد الانتظار'`.
  - `data.expires_at` (`string`): `Y-m-d H:i:s` (`Asia/Aden` server timezone).

#### 2. `POST /api/v1/network-pos/challenges/preview`
- **Request Body (`PreviewNetworkPosLinkingChallengeRequest`):**
  - `payload` (`string`, required): Scanned QR payload (`NTS_LINK_V1_...`).
  - `pos_id` (`integer`, optional): Current active POS ID.
  - `pos_uuid` (`string/uuid`, optional): Current active POS UUID.
- **Response (200 OK):** Wraps `NetworkPosLinkingPreviewResource`.
  - `data.challenge_uuid` (`string/uuid`)
  - `data.network` (`NetworkResource`): Supplier Network details.
  - `data.pos` (`PosResource`): Verified POS details.
  - `data.expires_at` (`string`): Formatted expiration time.
  - `data.is_targeted` (`boolean`): Whether challenge was targeted to a specific POS.

#### 3. `POST /api/v1/network-pos/challenges/confirm`
- **Request Body (`ConfirmNetworkPosLinkingChallengeRequest`):**
  - `challenge_uuid` (`string/uuid`, required): UUID from preview.
  - `pos_id` (`integer`, optional): POS ID confirming link.
  - `pos_uuid` (`string/uuid`, optional): POS UUID confirming link.
- **Response (201 Created):** Wraps `NetworkPosResource`.
  - `data.network_pos_id` (`integer`)
  - `data.network_id` (`integer`)
  - `data.pos_id` (`integer`)
  - `data.status` (`boolean`): `true`.

#### 4. `DELETE /api/v1/network-pos/challenges/{uuid}`
- **Response (200 OK):** Standard success response confirming revocation.

### 4.3 Timestamp & Date Serialization Notes
- **Current Canonical Pattern:** The NetSales API uniformly outputs timestamps via `Carbon::parse($field)->toDateTimeString()` (`Y-m-d H:i:s`) in the `Asia/Aden` timezone across all 25+ resources.
- **Client Parsing Invariant:** The Flutter client parses timestamps via `DateTime.tryParse()`, which transparently ingests both `toDateTimeString()` formats and ISO 8601 UTC strings.
- **Security Timestamp Consideration:** Timestamps inside the encrypted QR payload (`exp`) use integer epoch seconds (`$expiresAt->timestamp`) to ensure exact, timezone-agnostic expiration calculation on the server.


---

## 5. Platform Dashboard Overview — Verified API Contract

### Endpoint
- **Method:** \`GET\`
- **Path:** \`/api/v1/dashboard/overview\`
- **Authentication:** Active session (\`session.auth\`) and registered device.
- **Platform roles:** \`SUPER_ADMIN\`, \`SUPPORT_AGENT\`, \`ACCOUNTANT\` only.
- **Period query:** Optional \`period\`: \`7d\`, \`30d\` (default), \`90d\`, or \`12m\`.
- **Invalid period:** HTTP 422.
- **Operational tenant roles:** HTTP 403. This endpoint must not expose cross-tenant platform aggregates to tenant roles.

### Response envelope
The standard API envelope contains \`success\`, \`message\`, \`data\`, and \`errors\`.

\`data\` contains:
- \`generated_at\`: ISO-8601 timestamp.
- \`timezone\`: \`Asia/Aden\`.
- \`period\`: \`key\`, \`days\`, \`from\`, \`to\`.
- \`metrics\`: only metric groups authorized by the caller's permissions.
- \`charts\`: \`subscription_trend\`, \`provider_distribution\`, \`operational_activity\`.
- \`attention\`: only queues allowed by the caller's permissions.
- \`recent_activity\`: audit events for \`system_logs.view\`; support-ticket creation activity for support-only users.

### Permission-aware metric groups
| Permission | Metric group | Source |
|---|---|---|
| \`networks.view\` | \`metrics.networks\` | Non-soft-deleted \`networks\`, grouped by actual status |
| \`pos.view\` | \`metrics.pos\` | Non-soft-deleted \`pos\`, grouped by actual status |
| \`subscriptions.view\` | \`metrics.subscriptions\` | \`provider_subscriptions\` and actual date/status fields |
| \`support.view\` | \`metrics.support\` | \`support_tickets\` and unread \`support_ticket_messages\` |
| \`sales.view\` | \`metrics.sales\` | Authoritative \`sale_invoices\` amounts within selected period |
| \`expenses.view\` | \`metrics.expenses\` | Authoritative \`expenses\` amounts within selected period |
| \`system_logs.view\` | Activity chart and recent audit activity | \`system_logs\` |

Financial amount fields are serialized as decimal strings from SQL aggregates. The current schema has no currency dimension; no currency conversion or client-side financial calculation is performed.

### Attention queues
- \`pending_networks\`: records with \`networks.status = PENDING\`.
- \`pending_pos\`: records with \`pos.status = PENDING\`.
- \`expiring_subscriptions\`: \`ACTIVE\` provider subscriptions ending within the next seven days.
- \`pending_subscription_requests\`: \`PENDING\` subscription-plan requests.

Each queue returns an authoritative total \`count\` and at most five preview \`items\`. Each preview item contains \`id\`, \`title\`, \`subtitle\`, \`route_key\`, \`occurred_at\`, and \`due_at\`.

### Semantic constraints
- The support-ticket schema currently has no ticket lifecycle/status column. The API therefore exposes \`total_tickets\` and \`unread_messages\`; it does **not** label all tickets as open.
- The API returns only existing persisted data. Empty chart series and empty queues are valid real results, not synthetic samples.
- Recent audit events are returned only when \`system_logs.view\` is granted. Support agents without that permission receive only recent support-ticket creation activity.

## Network and POS membership role discovery (2026-11-09)
- `GET /api/v1/networks/{networkId}/members/assignable-roles` returns active `NETWORK_USER` only after Network owner authorization.
- `GET /api/v1/pos/{posId}/members/assignable-roles` returns active `POS_USER` only after POS owner/agent authorization.
- Member list records include `is_owner`, computed from the authoritative tenant `owner_id`.
- Membership create and role-change operations accept only the matching operational user role. Owner, accountant, platform, cross-scope, inactive and unknown roles are rejected.
- The global `GET /api/v1/roles` remains platform role reference data and must not be used for mobile tenant membership role selection.


## Network Workspace Overview — Operational Mobile Contract

- Method/path: `GET /api/v1/networks/{networkId}/workspace-overview`.
- Authentication: active `session.auth` session and an operational workspace account.
- Authorization: the caller must own the target Network or hold an `ACTIVE` `network_users` membership, pass the Network view policy, and hold `networks.view` in that exact network scope. Platform Dashboard overview is not reused by mobile.
- Optional `period`: `7d`, `30d` (default), `90d`, `12m`; invalid values return HTTP 422.
- Response includes `generated_at`, `timezone` (`Asia/Aden`), `period`, the selected `network`, permission-filtered `metrics`, and up to five `recent_pending_requests`.
- Metric groups are omitted when the caller lacks the corresponding permission:
  - `points_of_sale`: `pos.view`; counts active/inactive Network–POS links for the selected network.
  - `requests` and `recent_pending_requests`: `requests.view`; status counts and a small pending queue including the server-calculated `request_total_amount` and request date.
  - `packages`: `voucher_packages.view`; active/inactive package counts.
  - `sales`: `sales.view`; server aggregates of invoice count, invoice total, paid and outstanding amounts for the selected date range.
  - `expenses`: `expenses.view`; network-only expense count and total for the selected date range.
  - `wallet`: `network_wallets.view`; current server wallet balance and active status, or `null` if no wallet is allocated.
- Every query is scoped by the route's `networkId`. Financial values are aggregated only in Laravel and serialized as two-decimal strings. Flutter must not derive or aggregate these values.


## Authentication — Operational Workspace Roles

Successful login, successful two-factor verification, and `GET /api/v1/auth/me` include a top-level `workspace_roles` array. Each item contains:

- `scope`: `NETWORK` or `POS`.
- `tenant_id`: the network_id or pos_id to which the role applies.
- `role_key` and `role_name`: the operational role assigned through the scoped membership.

The global `user.role` remains reserved for global/platform-scoped roles. Network and POS roles are stored only in `network_users` and `pos_users`, respectively; registration must not place a tenant role in `users.role_id`.

The top-level `permissions` list is a union of active scoped-role permissions for client-side capability display. It is not an authorization decision. Every protected endpoint must continue to verify the permission against the exact requested tenant using server-side scoped authorization. A user may have more than one workspace role, including roles in both scopes; the client chooses the workspace while the server validates each tenant independently.


## Platform Billing — Trial and Dunning Policies
All endpoints below are nested under `/api/v1/platform/billing` and protected by Platform Scope authorization:
- `GET policies/trials`, `GET policies/dunning`: list policies and their current versions.
- `POST policies/trials`, `POST policies/dunning`: create a policy with its first draft version.
- `POST policies/trials/{policyId}/versions`, `POST policies/dunning/{policyId}/versions`: create a new draft version.
- `POST policies/trial-versions/{versionId}/publish`, `POST policies/dunning-versions/{versionId}/publish`: publish a draft as the current immutable version.
- `PUT policies/plan-versions/{versionId}`, `PUT policies/prices/{priceId}`: assign published policy versions to a draft plan version or draft price.
- `GET policies/trial-redemptions/pending`: cursor-paginated list of trial requests awaiting Platform Scope review.
- `POST policies/trial-redemptions/{redemptionId}/approve`: approve a trial awaiting manual approval.
- `POST policies/trial-redemptions/{redemptionId}/reject`: reject a pending trial request with a mandatory reason.
- `POST policies/subscriptions/{subscriptionId}/trial-adjustments`: extend, shorten, or end an active trial with mandatory reason and audit actor.
- `POST policies/subscriptions/{subscriptionId}/trial-adjustments`: extend, shorten, or end an active trial with mandatory reason and audit actor.


## Credit Notes and Refund Settlement
All endpoints are under `/api/v1/platform/billing` and require Platform Scope plus `billing.approve`:
- `POST invoices/{invoiceId}/credit-notes`: issue an immutable credit note against one or more original invoice lines. Requires an idempotency key, reason, and positive line amounts; accounting treatment is derived automatically per invoice line from revenue recognition state and proportional tax allocation. The service prevents line-level and invoice-level over-crediting, offsets open receivables first, and reserves only the overpaid portion as refundable customer credit.
- `POST payments/{paymentId}/refunds`: settle an issued credit note against a verified payment. Requires `credit_note_id`, `treasury_account_id`, `amount_minor`, `reason`, and `idempotency_key`. The amount must fit both the payment's remaining refundable amount and the credit note's remaining refundable amount. The refund persists the treasury account used, debits customer-credit liability (2200), and credits that same-currency treasury account.
- Invoice resources expose issued credit notes when loaded, including receivable-applied, refundable, and refunded amounts.


## Subscription Billing — Invoice Settlement and Currency Contract
- Every invoice and all its invoice lines use exactly one currency. Payments, credit notes, refunds, treasury accounts, and journal entries must match that invoice currency; cross-currency settlement requires an explicit recorded currency conversion.
- Monetary API amounts are returned as integer minor units plus formatted decimal strings derived from the currency decimal-place definition. Clients must not use binary floating-point arithmetic for financial decisions.
- Invoice balances are maintained independently as `total_minor`, `paid_minor`, `credited_minor`, and `remaining_minor`. `credited_minor` is the total issued credit-note value; `remaining_minor = max(0, total_minor - paid_minor - credited_minor)`.
- Credit-note issuance first allocates against open receivables. Any amount exceeding the remaining receivable is recorded as a customer-credit liability in ledger account `2200`; cash refunds debit that liability and credit the same-currency treasury account.
- `PARTIALLY_CREDITED` invoices may still accept payment for their remaining balance. `CREDITED` means the invoice obligation was fully offset by credit notes. A paid invoice can also have a subsequent credit note, creating a refundable customer credit.
- This contract is implementation documentation only until the final automated validation and migration gate passes.


## Deferred Revenue Recognition Contract
- Invoice finalization classifies `SUBSCRIPTION` recurring lines as deferred revenue (2100), `SETUP_FEE` lines as immediately earned setup/service revenue (4120), and `METERED_USAGE` lines as earned usage revenue (4110). One-time subscription prices are recognized immediately. Unsupported invoice line types are rejected rather than guessed.
- Invoice line tax is posted separately to tax payable (2300); it is not recognized as subscription revenue. Every invoice journal balances in the invoice currency.
- Each recurring subscription invoice line creates one immutable-identity recognition schedule (`billing_revenue_recognition_schedules`) with service dates, currency, revenue account, total amount, recognized amount, and status. Daily catch-up uses cumulative integer allocation; the final service day recognizes the exact residual, eliminating rounding loss.
- `billing:recognize-revenue` runs daily in `Asia/Aden` and is safe to rerun. It debits deferred revenue (2100) and credits subscription revenue (4100), with a deterministic ledger idempotency key and an auditable recognition entry.
- Credit notes allocate the original line value proportionally between tax, unearned subscription revenue, and already-recognized revenue. They reduce the recognition schedule's remaining service obligation and post the appropriate debit to deferred revenue (2100), subscription revenue (4100), setup revenue (4120), usage revenue (4110), and tax payable (2300), as applicable. Clients and reviewers cannot choose a manual accounting treatment.
- An invoice with any recognized revenue cannot be voided. Corrections after service delivery must use the credit-note workflow so revenue history is not erased.


## Financial Reconciliation Contract
- `GET /api/v1/platform/billing/reconciliation` is Platform Scope only and requires `billing.view`. Optional `currency_id` limits the result to one currency; omitting it returns separate currency groups and never combines unlike currencies.
- Each currency group contains per-control-account ledger/subledger balances and `difference_minor` for Accounts Receivable (1100), Deferred Revenue (2100), Customer Credit Liability (2200), and Tax Payable (2300), plus trial balance totals.
- Treasury accounts are reconciled individually by comparing their general-ledger asset balance with verified payments assigned to that treasury less completed refunds paid from that same treasury. Every refund stores `treasury_account_id`.
- Any verified payment or completed refund missing a treasury link is exposed in warnings and makes the currency group require attention. Treasury accounts missing their ledger link are also exposed.
- `MATCH` means all internal ledger/subledger checks balance exactly in minor units. It is not a substitute for matching external bank statements; no bank-statement import is currently represented by this endpoint.


## Operational Reports Provider Contract — 2026-11-13 (Implementation Pending Final Validation)

### Catalog and provider ownership
- `GET /api/v1/reports/catalog?network_id={id}[&pos_id={id}]` returns `schema_version` and a `reports[]` catalog.
- Every report definition includes a stable `id`, `schema_version`, `title_key`, localized `label`/`description`, view/export permission keys, `supported_scopes`, `pos_selection`, filter definitions, result columns, metric slots, chart slots, template references, execution policy, client row limit, chart availability, and export formats.
- Catalog entries add `available` and, when unavailable, `unavailable_reason` for the requested workspace context. This is display guidance only; every execution/export request is independently authorized on the server.
- Each report has one registered `OperationalReportProvider`. It owns its query, full-result summary, filter options, export columns, and row serialization. The provider registry rejects duplicate providers and missing report types.
- Supported report IDs: `sales`, `expenses`, `network_wallet_statement`, `pos_wallet_statement`, `repayments`, `debt_aging`, and `requests`.

### Execution and export routes
- `GET /api/v1/reports` returns a cursor-paginated result page with Laravel cursor metadata. `per_page` and `cursor` are transport pagination parameters; report filters are restricted to those declared by the selected provider.
- `GET /api/v1/reports/summary` returns provider-owned aggregates and chart series computed over the complete filtered result set, not merely the current page.
- `POST /api/v1/reports/exports` queues a CSV export. Status and download are available through `GET /api/v1/reports/exports/{exportUuid}` and `GET /api/v1/reports/exports/{exportUuid}/download`.
- CSV column identifiers, order, and row serialization are owned by the provider. Exports use cursor iteration, formula-injection protection, a 50,000-row limit, private protected storage, SHA-256, and seven-day expiry.
- Access checks use the exact network or POS membership role scope and the provider's permission key. A user with both memberships is authorized through whichever supported scope has both active membership and the required permission. Platform Scope roles are not a substitute for Network/POS workspace permissions.

### Wallet statement ledger fields
- `network_wallets` and `pos_wallets` expose `opening_balance`, `opening_balance_source`, and `currency_code`.
- `network_wallet_logs` and `pos_wallet_logs` expose `balance_before`, `balance_after`, `balance_snapshot_source`, and `currency_code`.
- Snapshot provenance values used by the implementation: `BACKFILLED` for reconstructed legacy rows, `INITIAL` for a newly initialized wallet opening balance, and `LIVE` for new posting-time movement snapshots.
- `balance_before`/`balance_after` mean the balance immediately before/after the posting operation; they are not an effective-date running balance for backdated transactions. Period opening/closing balances are derived from ledger movements filtered by `log_date`.
- Wallet statements include soft-deleted ledger entries and soft-deleted wallets for audit continuity. They include effective transaction date and posting timestamp as separate fields.
- Operational currency is independent of Platform Billing currencies. Documents spanning a network and POS wallet reject differing currency codes rather than combining unlike currencies.

### Immutable operational document snapshots
- `POST /api/v1/documents/snapshots` accepts `document_type`, `document_id`, `network_id`, optional `pos_id`, `template_id`, and `template_version`.
- Supported document types/template versions:
  - `sale_invoice` → `netsales.invoice.executive@1.0.0`
  - `pos_repayment` → `netsales.repayment.receipt.executive@1.0.0`
  - `expense` → `netsales.expense.voucher.executive@1.0.0`
- The response contains the immutable source payload, snapshot UUID/version, schema/template versions, currency, source SHA-256, issue timestamp, and artifact-ready state.
- `POST /api/v1/documents/snapshots/{snapshotUuid}/artifact` accepts a PDF artifact. The server validates the PDF signature, stores it on the protected disk, and records SHA-256 and size. First successful artifact wins; a retry cannot replace it.
- `GET /api/v1/documents/snapshots/{snapshotUuid}/download` rechecks current tenant membership and permission before streaming the canonical PDF with private/no-store headers.
- Snapshot source and issuance metadata cannot be updated or deleted through the Eloquent model. Creator/reversal identities are minimized to user ID and display name; expense attachment paths and URLs are omitted from permanent source snapshots.

**Validation status:** This contract describes the current source implementation on `feat/operational-reports-contracts`. It has not yet passed the final migration/runtime, Pint, PHPStan, PHPUnit, PDF extraction/visual, or exact-HEAD GitHub Actions gate. Do not treat it as a verified production contract until that gate passes.
