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

---

## 3. Procurement & Debt Routes Verification
- **Request Modification Preview:** `POST /api/v1/requests/{id}/preview-modification` exists. Validates item modifications and returns calculated order preview before approval.
- **FIFO Repayment Collection:** `POST /api/v1/pos-debts/repayments` performs atomic FIFO allocation in `DebtRepaymentService`.
- **FIFO Repayment Preview:** **No preview route currently exists** in the active API. DEC-04 remains `PROPOSED`.
