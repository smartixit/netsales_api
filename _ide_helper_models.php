<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $action_type_id
 * @property string $action_type_key
 * @property string $action_type_name
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType byKey(string $key)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereActionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereActionTypeKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereActionTypeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActionType withoutTrashed()
 */
	class ActionType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $backup_job_id
 * @property string $job_uuid
 * @property \App\Enums\BackupType $backup_type
 * @property \App\Enums\BackupStatus $status
 * @property string|null $stage
 * @property string|null $file_name
 * @property int|null $file_size_bytes
 * @property string|null $archive_sha256
 * @property string|null $drive_file_id
 * @property string|null $drive_web_view_link
 * @property string|null $drive_folder_id
 * @property array<array-key, mixed>|null $manifest_summary
 * @property array<array-key, mixed>|null $extra
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $creator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob stale(\DateTimeInterface $threshold)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob status(\App\Enums\BackupStatus|string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob successful()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereArchiveSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereBackupJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereBackupType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereDriveFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereDriveFolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereDriveWebViewLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereDurationMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereExtra($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereFileSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereJobUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereManifestSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupJob withoutTrashed()
 */
	class BackupJob extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $account_id
 * @property string $bank_name
 * @property string|null $bank_logo
 * @property string $account_holder_name
 * @property string $account_number
 * @property \App\Enums\BankAccountType $account_type
 * @property string $account_currency
 * @property numeric $account_balance
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount byCurrency(string $currency)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount byType(\App\Enums\BankAccountType|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountHolderName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereBankLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount withoutTrashed()
 */
	class BankAccount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $billing_account_id
 * @property int $network_id
 * @property string $account_number
 * @property string $legal_name
 * @property string|null $billing_email
 * @property string|null $billing_address
 * @property int $default_currency_id
 * @property int $payment_terms_days
 * @property-read Network $network
 * @property-read Currency $defaultCurrency
 * @property string|null $tax_number
 * @property string $status
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingAccountContact> $contacts
 * @property-read int|null $contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingInvoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPayment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingSubscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereBillingAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereBillingEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereDefaultCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereLegalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount wherePaymentTermsDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereTaxNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccount withoutTrashed()
 */
	class BillingAccount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $contact_id
 * @property int $billing_account_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_primary
 * @property bool $receives_invoices
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingAccount|null $billingAccount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereReceivesInvoices($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAccountContact whereUpdatedAt($value)
 */
	class BillingAccountContact extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $billing_job_id
 * @property string $job_type
 * @property string $aggregate_type
 * @property int $aggregate_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property int $attempt_count
 * @property string|null $last_error
 * @property array<array-key, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereAggregateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereAggregateType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereAttemptCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereBillingJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereJobType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingAutomationJob whereUpdatedAt($value)
 */
	class BillingAutomationJob extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $credit_note_id
 * @property int $invoice_id
 * @property int $billing_account_id
 * @property int $currency_id
 * @property string $status
 * @property string $reason
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $applied_to_receivable_minor
 * @property int $refundable_minor
 * @property int $refunded_minor
 * @property-read BillingInvoice $invoice
 * @property-read Currency $currency
 * @property-read Collection<int, BillingCreditNoteLine> $lines
 * @property string $credit_note_number
 * @property string $idempotency_key
 * @property string $accounting_treatment
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingAccount|null $billingAccount
 * @property-read int|null $lines_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingRefund> $refunds
 * @property-read int|null $refunds_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereAccountingTreatment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereAppliedToReceivableMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereCreditNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereCreditNoteNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereRefundableMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereRefundedMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereSubtotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereTaxMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereTotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNote whereUpdatedAt($value)
 */
	class BillingCreditNote extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $credit_note_line_id
 * @property int $credit_note_id
 * @property int $invoice_line_id
 * @property int $quantity
 * @property int $unit_amount_minor
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property-read BillingCreditNote $creditNote
 * @property-read BillingInvoiceLine $invoiceLine
 * @property string $description
 * @property int $quantity_micros
 * @property int $amount_minor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereCreditNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereCreditNoteLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereInvoiceLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereQuantityMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingCreditNoteLine whereUpdatedAt($value)
 */
	class BillingCreditNoteLine extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $dunning_attempt_id
 * @property int $invoice_id
 * @property int $attempt_number
 * @property string $status
 * @property string $channel
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property \Illuminate\Support\Carbon|null $attempted_at
 * @property string|null $result_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingInvoice $invoice
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereAttemptNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereAttemptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereDunningAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereResultMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningAttempt whereUpdatedAt($value)
 */
	class BillingDunningAttempt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $dunning_policy_id
 * @property string $policy_key
 * @property string $name
 * @property int|null $current_version_id
 * @property bool $is_active
 * @property-read BillingDunningPolicyVersion|null $currentVersion
 * @property string|null $description
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingDunningPolicyVersion> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereCurrentVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereDunningPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy wherePolicyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicy whereUpdatedAt($value)
 */
	class BillingDunningPolicy extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $dunning_policy_version_id
 * @property int $dunning_policy_id
 * @property string $status
 * @property int $version_number
 * @property int $grace_period_days
 * @property array<int, int> $reminder_schedule_days
 * @property array<int, int> $pre_renewal_alert_days
 * @property bool $auto_suspend_after_grace
 * @property array<int, int> $reminder_schedule_days
 * @property array<int, int> $pre_renewal_alert_days
 * @property-read BillingDunningPolicy $policy
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereAutoSuspendAfterGrace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereDunningPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereDunningPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereGracePeriodDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion wherePreRenewalAlertDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereReminderScheduleDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingDunningPolicyVersion whereVersionNumber($value)
 */
	class BillingDunningPolicyVersion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $invoice_id
 * @property string $invoice_number
 * @property string $invoice_type
 * @property int $billing_account_id
 * @property int|null $billing_subscription_id
 * @property int $currency_id
 * @property BillingInvoiceStatus $status
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $credited_minor
 * @property int $remaining_minor
 * @property array<string, mixed>|null $customer_snapshot
 * @property array<string, mixed>|null $pricing_snapshot
 * @property string|null $memo
 * @property Carbon|null $finalized_at
 * @property Carbon|null $voided_at
 * @property int|null $created_by
 * @property Carbon|null $paid_at
 * @property-read BillingAccount $billingAccount
 * @property-read Currency $currency
 * @property-read BillingSubscription|null $subscription
 * @property-read Collection<int, BillingInvoiceLine> $lines
 * @property-read Collection<int, BillingPayment> $payments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingCreditNote> $creditNotes
 * @property-read int|null $credit_notes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingDunningAttempt> $dunningAttempts
 * @property-read int|null $dunning_attempts_count
 * @property-read int|null $lines_count
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice forAccount(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereCreditedMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereCustomerSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereDiscountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereDueOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereFinalizedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereInvoiceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereIssuedOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice wherePaidMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice wherePricingSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereRemainingMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereSubtotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereTaxMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereTotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoice whereVoidedAt($value)
 */
	class BillingInvoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $invoice_line_id
 * @property int $invoice_id
 * @property int $currency_id
 * @property string $line_type
 * @property string $description
 * @property string $quantity
 * @property int $unit_amount_minor
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property array<string, mixed>|null $source_snapshot
 * @property-read BillingInvoice $invoice
 * @property-read Currency $currency
 * @property int|null $plan_version_id
 * @property int|null $price_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingPlanVersion|null $planVersion
 * @property-read \App\Models\BillingPrice|null $price
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereDiscountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereInvoiceLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereLineType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine wherePlanVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine wherePriceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereSourceSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereSubtotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereTaxMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereTotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereUnitAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingInvoiceLine whereUpdatedAt($value)
 */
	class BillingInvoiceLine extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $payment_id
 * @property string $payment_reference
 * @property string $idempotency_key
 * @property string|null $external_reference
 * @property string|null $review_note
 * @property int|null $recorded_by
 * @property int|null $verified_by
 * @property int|null $payment_intent_id
 * @property int $invoice_id
 * @property int $billing_account_id
 * @property int $currency_id
 * @property int $payment_method_id
 * @property int|null $treasury_account_id
 * @property int $amount_minor
 * @property BillingPaymentStatus $status
 * @property Carbon|null $received_at
 * @property Carbon|null $verified_at
 * @property-read BillingInvoice $invoice
 * @property-read BillingAccount $billingAccount
 * @property-read Currency $currency
 * @property-read BillingPaymentMethod $paymentMethod
 * @property-read BillingPaymentIntent|null $paymentIntent
 * @property-read TreasuryAccount|null $treasuryAccount
 * @property-read Collection<int, BillingPaymentProof> $proofs
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingPaymentIntent|null $intent
 * @property-read int|null $proofs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingRefund> $refunds
 * @property-read int|null $refunds_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereExternalReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment wherePaymentIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment wherePaymentReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereReviewNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereTreasuryAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPayment whereVerifiedBy($value)
 */
	class BillingPayment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $payment_attempt_id
 * @property int $payment_intent_id
 * @property int $attempt_number
 * @property string $status
 * @property string|null $provider_reference
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property \Illuminate\Support\Carbon|null $attempted_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingPaymentIntent $intent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereAttemptNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereAttemptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereFailureCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereFailureMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt wherePaymentAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt wherePaymentIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentAttempt whereUpdatedAt($value)
 */
	class BillingPaymentAttempt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $payment_intent_id
 * @property int $invoice_id
 * @property int $billing_account_id
 * @property int $currency_id
 * @property int $amount_minor
 * @property string $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $succeeded_at
 * @property string $intent_uuid
 * @property int $payment_method_id
 * @property string $idempotency_key
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPaymentAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read \App\Models\BillingAccount|null $billingAccount
 * @property-read \App\Models\Currency $currency
 * @property-read \App\Models\BillingInvoice $invoice
 * @property-read \App\Models\BillingPaymentMethod $paymentMethod
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPayment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereIntentUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent wherePaymentIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereSucceededAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentIntent whereUpdatedAt($value)
 */
	class BillingPaymentIntent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $payment_method_id
 * @property string $name
 * @property bool $is_active
 * @property bool $requires_proof
 * @property-read Collection<int, Currency> $currencies
 * @property string $method_key
 * @property string $method_type
 * @property array<array-key, mixed>|null $instructions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $currencies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPaymentIntent> $intents
 * @property-read int|null $intents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPayment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereInstructions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereMethodKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereMethodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereRequiresProof($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentMethod whereUpdatedAt($value)
 */
	class BillingPaymentMethod extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $payment_proof_id
 * @property int $payment_id
 * @property string $file_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property string|null $sha256
 * @property int $uploaded_by
 * @property Carbon $uploaded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingPayment $payment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof wherePaymentProofId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereUploadedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPaymentProof whereUploadedBy($value)
 */
	class BillingPaymentProof extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $plan_id
 * @property int $product_id
 * @property string $plan_key
 * @property string $name
 * @property int|null $current_version_id
 * @property bool $is_active
 * @property bool $is_public
 * @property-read BillingProduct $product
 * @property-read BillingPlanVersion|null $currentVersion
 * @property string|null $description
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPlanVersion> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereCurrentVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan wherePlanKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlan withoutTrashed()
 */
	class BillingPlan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $plan_feature_id
 * @property int $plan_version_id
 * @property string $feature_key
 * @property string $name
 * @property BillingFeatureType $feature_type
 * @property string $value
 * @property string|null $unit
 * @property string|null $aggregation
 * @property string|null $reset_period
 * @property string $overage_behavior
 * @property array<string, mixed>|null $configuration
 * @property-read BillingPlanVersion $planVersion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereAggregation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereConfiguration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereFeatureKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereFeatureType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereOverageBehavior($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature wherePlanFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature wherePlanVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereResetPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanFeature whereValue($value)
 */
	class BillingPlanFeature extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $plan_version_id
 * @property int $plan_id
 * @property int $version_number
 * @property Carbon|null $published_at
 * @property BillingPlanVersionStatus $status
 * @property int|null $trial_policy_version_id
 * @property int|null $dunning_policy_version_id
 * @property-read BillingPlan $plan
 * @property-read BillingTrialPolicyVersion|null $trialPolicyVersion
 * @property-read BillingDunningPolicyVersion|null $dunningPolicyVersion
 * @property-read Collection<int, BillingPrice> $prices
 * @property-read Collection<int, BillingPlanFeature> $features
 * @property array<array-key, mixed>|null $terms_snapshot
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $features_count
 * @property-read int|null $prices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereDunningPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion wherePlanVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereTermsSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereTrialPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPlanVersion whereVersionNumber($value)
 */
	class BillingPlanVersion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $price_id
 * @property int $plan_version_id
 * @property int $currency_id
 * @property BillingInterval $billing_interval
 * @property int $interval_count
 * @property int $unit_amount_minor
 * @property int $setup_fee_minor
 * @property int|null $trial_policy_version_id
 * @property int|null $dunning_policy_version_id
 * @property string $tax_behavior
 * @property bool $is_active
 * @property-read Currency $currency
 * @property-read BillingPlanVersion $planVersion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingDunningPolicyVersion|null $dunningPolicyVersion
 * @property-read \App\Models\BillingTrialPolicyVersion|null $trialPolicyVersion
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereBillingInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereDunningPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereIntervalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice wherePlanVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice wherePriceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereSetupFeeMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereTaxBehavior($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereTrialPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereUnitAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingPrice whereUpdatedAt($value)
 */
	class BillingPrice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $product_id
 * @property string $product_key
 * @property string $name
 * @property bool $is_active
 * @property string|null $description
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPlan> $plans
 * @property-read int|null $plans_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereProductKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingProduct withoutTrashed()
 */
	class BillingProduct extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $refund_id
 * @property string $refund_reference
 * @property string $idempotency_key
 * @property string $reason
 * @property int $processed_by
 * @property Carbon|null $processed_at
 * @property int $payment_id
 * @property int $credit_note_id
 * @property int $treasury_account_id
 * @property int $currency_id
 * @property int $amount_minor
 * @property string $status
 * @property-read BillingPayment $payment
 * @property-read BillingCreditNote $creditNote
 * @property-read Currency $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TreasuryAccount|null $treasuryAccount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereCreditNoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereRefundId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereRefundReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereTreasuryAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRefund whereUpdatedAt($value)
 */
	class BillingRefund extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $revenue_recognition_entry_id
 * @property int $revenue_schedule_id
 * @property int $journal_entry_id
 * @property-read LedgerJournalEntry $journalEntry
 * @property string $idempotency_key
 * @property Carbon $recognized_through
 * @property int $amount_minor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingRevenueRecognitionSchedule $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereRecognizedThrough($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereRevenueRecognitionEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereRevenueScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionEntry whereUpdatedAt($value)
 */
	class BillingRevenueRecognitionEntry extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $revenue_schedule_id
 * @property int $invoice_id
 * @property int|null $invoice_line_id
 * @property int $currency_id
 * @property string $revenue_account_code
 * @property int $total_minor
 * @property int $recognized_minor
 * @property Carbon|null $last_recognized_on
 * @property string $status
 * @property Carbon $service_start_on
 * @property Carbon $service_end_on
 * @property Carbon|null $last_recognized_on
 * @property string $status
 * @property-read BillingInvoice $invoice
 * @property-read BillingInvoiceLine|null $invoiceLine
 * @property-read Currency $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereInvoiceLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereLastRecognizedOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereRecognizedMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereRevenueAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereRevenueScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereServiceEndOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereServiceStartOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereTotalMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingRevenueRecognitionSchedule whereUpdatedAt($value)
 */
	class BillingRevenueRecognitionSchedule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $billing_subscription_id
 * @property int $billing_account_id
 * @property int $plan_id
 * @property int $plan_version_id
 * @property int $price_id
 * @property int $quantity
 * @property array<string, mixed>|null $trial_policy_snapshot
 * @property array<string, mixed>|null $dunning_policy_snapshot
 * @property int $currency_id
 * @property BillingSubscriptionStatus $status
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $trial_start
 * @property Carbon|null $trial_end
 * @property Carbon|null $ended_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon|null $next_billing_at
 * @property array<string, mixed>|null $metadata
 * @property-read BillingAccount $billingAccount
 * @property-read BillingPlan $plan
 * @property-read BillingPlanVersion $planVersion
 * @property-read BillingPrice $price
 * @property-read Currency $currency
 * @property-read BillingTrialRedemption|null $trialRedemption
 * @property-read Collection<int, BillingSubscriptionEvent> $events
 * @property int|null $trial_policy_version_id
 * @property int|null $dunning_policy_version_id
 * @property string $collection_method
 * @property bool $cancel_at_period_end
 * @property \Illuminate\Support\Carbon|null $canceled_at
 * @property array<array-key, mixed> $terms_snapshot
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\BillingDunningPolicyVersion|null $dunningPolicyVersion
 * @property-read int|null $events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingInvoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingSubscriptionItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\BillingTrialPolicyVersion|null $trialPolicyVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingUsageEvent> $usageEvents
 * @property-read int|null $usage_events_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription forAccount(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCancelAtPeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCanceledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCollectionMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCurrentPeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereCurrentPeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereDunningPolicySnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereDunningPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereEndedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereGracePeriodEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription wherePausedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription wherePlanVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription wherePriceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereTermsSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereTrialEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereTrialPolicySnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereTrialPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereTrialStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscription withoutTrashed()
 */
	class BillingSubscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $subscription_alert_id
 * @property int $billing_subscription_id
 * @property string $alert_type
 * @property string $status
 * @property Carbon $scheduled_at
 * @property Carbon|null $sent_at
 * @property int $dunning_policy_version_id
 * @property \Illuminate\Support\Carbon $period_end
 * @property int $alert_days
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property string|null $message_uuid
 * @property string|null $result_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingDunningPolicyVersion $policyVersion
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereAlertDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereDunningPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereMessageUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereQueuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereResultMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereSubscriptionAlertId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionAlert whereUpdatedAt($value)
 */
	class BillingSubscriptionAlert extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $subscription_event_id
 * @property int $billing_subscription_id
 * @property string $event_type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property array<string, mixed>|null $payload
 * @property int|null $actor_id
 * @property Carbon $occurred_at
 * @property int $event_id
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereFromStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionEvent whereToStatus($value)
 */
	class BillingSubscriptionEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $subscription_item_id
 * @property int $billing_subscription_id
 * @property int|null $plan_feature_id
 * @property string $item_type
 * @property string $description
 * @property numeric $quantity
 * @property int $unit_amount_minor
 * @property int $amount_minor
 * @property int $currency_id
 * @property array<array-key, mixed>|null $terms_snapshot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Currency $currency
 * @property-read \App\Models\BillingPlanFeature|null $feature
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem wherePlanFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereSubscriptionItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereTermsSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereUnitAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingSubscriptionItem whereUpdatedAt($value)
 */
	class BillingSubscriptionItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $trial_adjustment_id
 * @property int $trial_redemption_id
 * @property int $billing_subscription_id
 * @property string $adjustment_type
 * @property Carbon|null $previous_trial_end
 * @property Carbon $new_trial_end
 * @property string $reason
 * @property int $actor_id
 * @property Carbon $occurred_at
 * @property array<array-key, mixed>|null $metadata
 * @property-read \App\Models\BillingTrialRedemption $redemption
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereAdjustmentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereNewTrialEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment wherePreviousTrialEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereTrialAdjustmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialAdjustment whereTrialRedemptionId($value)
 */
	class BillingTrialAdjustment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $trial_eligibility_check_id
 * @property int $network_id
 * @property int $billing_account_id
 * @property int $price_id
 * @property int $plan_id
 * @property int|null $trial_policy_version_id
 * @property int|null $checked_by
 * @property string $eligibility_status
 * @property string|null $reason_code
 * @property string $source
 * @property \Illuminate\Support\Carbon $checked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingAccount|null $billingAccount
 * @property-read \App\Models\BillingTrialPolicyVersion|null $policyVersion
 * @property-read \App\Models\BillingPrice $price
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereCheckedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereEligibilityStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck wherePriceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereReasonCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereTrialEligibilityCheckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereTrialPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialEligibilityCheck whereUpdatedAt($value)
 */
	class BillingTrialEligibilityCheck extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $trial_policy_id
 * @property string $policy_key
 * @property string $name
 * @property int|null $current_version_id
 * @property bool $is_active
 * @property-read BillingTrialPolicyVersion|null $currentVersion
 * @property string|null $description
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingTrialPolicyVersion> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereCurrentVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy wherePolicyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereTrialPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicy whereUpdatedAt($value)
 */
	class BillingTrialPolicy extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $trial_policy_version_id
 * @property int $trial_policy_id
 * @property string $status
 * @property int $version_number
 * @property bool $is_enabled
 * @property string $duration_unit
 * @property int $duration_value
 * @property string $start_trigger
 * @property string $redemption_scope
 * @property string $eligibility_mode
 * @property bool $requires_manual_approval
 * @property string $end_behavior
 * @property string $entitlement_mode
 * @property array<string, mixed>|null $entitlement_config
 * @property int $duration_value
 * @property array<string, mixed>|null $entitlement_config
 * @property Carbon|null $published_at
 * @property-read BillingTrialPolicy $policy
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereDurationUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereDurationValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereEligibilityMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereEndBehavior($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereEntitlementConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereEntitlementMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereRedemptionScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereRequiresManualApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereStartTrigger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereTrialPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereTrialPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialPolicyVersion whereVersionNumber($value)
 */
	class BillingTrialPolicyVersion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $trial_redemption_id
 * @property int $billing_subscription_id
 * @property int $trial_policy_id
 * @property int $trial_policy_version_id
 * @property int $network_id
 * @property int $billing_account_id
 * @property int $product_id
 * @property string $redemption_scope
 * @property string $redemption_subject_key
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $reserved_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read BillingTrialPolicy $policy
 * @property-read BillingTrialPolicyVersion $policyVersion
 * @property-read BillingSubscription $subscription
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingTrialAdjustment> $adjustments
 * @property-read int|null $adjustments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereBillingAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereRedemptionScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereRedemptionSubjectKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereReservedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereTrialPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereTrialPolicyVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereTrialRedemptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingTrialRedemption whereUpdatedAt($value)
 */
	class BillingTrialRedemption extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $usage_aggregate_id
 * @property int $billing_subscription_id
 * @property int $meter_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $quantity
 * @property int $amount_minor
 * @property-read BillingUsageMeter $meter
 * @property int|null $currency_id
 * @property \Illuminate\Support\Carbon|null $last_event_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereLastEventAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereMeterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageAggregate whereUsageAggregateId($value)
 */
	class BillingUsageAggregate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $usage_event_id
 * @property int $billing_subscription_id
 * @property int $meter_id
 * @property string $idempotency_key
 * @property numeric $quantity
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 * @property array<array-key, mixed>|null $dimensions
 * @property string|null $source_type
 * @property int|null $source_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingUsageMeter $meter
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereDimensions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereMeterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageEvent whereUsageEventId($value)
 */
	class BillingUsageEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $meter_id
 * @property string $meter_key
 * @property string $name
 * @property string $unit
 * @property string $aggregation
 * @property bool $is_active
 * @property string|null $reset_period
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingUsageAggregate> $aggregates
 * @property-read int|null $aggregates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingUsageEvent> $events
 * @property-read int|null $events_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereAggregation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereMeterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereMeterKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereResetPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageMeter whereUpdatedAt($value)
 */
	class BillingUsageMeter extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $reservation_id
 * @property int $billing_subscription_id
 * @property int $meter_id
 * @property string $reservation_key
 * @property numeric $quantity
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $committed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BillingUsageMeter $meter
 * @property-read \App\Models\BillingSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereBillingSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereCommittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereMeterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereReservationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereReservationKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BillingUsageReservation whereUpdatedAt($value)
 */
	class BillingUsageReservation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $currency_id
 * @property string $code
 * @property string $name
 * @property int $decimal_places
 * @property bool $is_active
 * @property string|null $numeric_code
 * @property string|null $native_name
 * @property string|null $symbol
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingAccount> $billingAccounts
 * @property-read int|null $billing_accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPrice> $prices
 * @property-read int|null $prices_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereDecimalPlaces($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereNativeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereNumericCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereUpdatedAt($value)
 */
	class Currency extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $conversion_id
 * @property int $exchange_rate_id
 * @property int $from_currency_id
 * @property int $to_currency_id
 * @property int $source_amount_minor
 * @property int $converted_amount_minor
 * @property int $fee_amount_minor
 * @property string $rounding_mode
 * @property string $rate_snapshot
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon $converted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CurrencyExchangeRate $exchangeRate
 * @property-read \App\Models\Currency $fromCurrency
 * @property-read \App\Models\Currency $toCurrency
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereConversionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereConvertedAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereConvertedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereExchangeRateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereFeeAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereFromCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereRateSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereRoundingMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereSourceAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereToCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyConversion whereUpdatedAt($value)
 */
	class CurrencyConversion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $exchange_rate_id
 * @property int $base_currency_id
 * @property int $quote_currency_id
 * @property int $rate_numerator
 * @property int $rate_denominator
 * @property string $source
 * @property \Illuminate\Support\Carbon $effective_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|null $recorded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Currency $baseCurrency
 * @property-read \App\Models\Currency $quoteCurrency
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereBaseCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereEffectiveAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereExchangeRateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereQuoteCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereRateDenominator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereRateNumerator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyExchangeRate whereUpdatedAt($value)
 */
	class CurrencyExchangeRate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $directorate_id
 * @property int $governorate_id
 * @property string $directorate_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Governorate|null $governorate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $networks
 * @property-read int|null $networks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $pos
 * @property-read int|null $pos_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate inGovernorate(int $governorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate search(string $term)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate whereDirectorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate whereDirectorateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate whereGovernorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate withNetworksCount()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate withPosCount()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Directorate withoutTrashed()
 */
	class Directorate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $expense_id
 * @property int $network_id
 * @property string $expense_uuid
 * @property numeric $expense_total_amount
 * @property \Illuminate\Support\Carbon $expense_date
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Network|null $network
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpenseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpenseTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereExpenseUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Expense withoutTrashed()
 */
	class Expense extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $attachment_id
 * @property int $expense_id
 * @property string $attachment_path
 * @property string $attachment_name
 * @property string $attachment_mime
 * @property int $attachment_size
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Expense|null $expense
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereAttachmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereAttachmentMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereAttachmentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereAttachmentPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereAttachmentSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseAttachment whereUpdatedAt($value)
 */
	class ExpenseAttachment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $item_id
 * @property int $expense_id
 * @property numeric $item_amount
 * @property string $item_statement
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Expense|null $expense
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem forExpense(int $expenseId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereItemAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereItemStatement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExpenseItem withoutTrashed()
 */
	class ExpenseItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $governorate_id
 * @property string $governorate_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Directorate> $directorates
 * @property-read int|null $directorates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $networks
 * @property-read int|null $networks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $pos
 * @property-read int|null $pos_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate search(string $term)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate whereGovernorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate whereGovernorateName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate withDirectoratesCount()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate withNetworksCount()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate withPosCount()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Governorate withoutTrashed()
 */
	class Governorate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $tenant_type
 * @property int $tenant_id
 * @property string $endpoint
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string $status
 * @property int|null $response_code
 * @property array<array-key, mixed>|null $response_body
 * @property array<array-key, mixed>|null $response_headers
 * @property int $fencing_token
 * @property \Illuminate\Support\Carbon $lease_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereFencingToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereLeaseExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereRequestHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereResponseBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereResponseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereResponseHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereTenantType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IdempotencyKey whereUserId($value)
 */
	class IdempotencyKey extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $ledger_account_id
 * @property string $account_code
 * @property string $name
 * @property string $normal_balance
 * @property int|null $currency_id
 * @property LedgerAccountClass $account_class
 * @property bool $is_control_account
 * @property bool $is_active
 * @property-read Currency|null $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LedgerJournalLine> $lines
 * @property-read int|null $lines_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount forCurrency(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereAccountClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereIsControlAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereNormalBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereUpdatedAt($value)
 */
	class LedgerAccount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $journal_entry_id
 * @property int $currency_id
 * @property LedgerEntryStatus $status
 * @property Carbon $entry_date
 * @property-read Currency $currency
 * @property-read Collection<int, LedgerJournalLine> $lines
 * @property string $entry_number
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $idempotency_key
 * @property string $description
 * @property int|null $reversal_of_id
 * @property int|null $posted_by
 * @property \Illuminate\Support\Carbon|null $posted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $lines_count
 * @property-read LedgerJournalEntry|null $reversalOf
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry forCurrency(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereEntryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereEntryNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry wherePostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry wherePostedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereReversalOfId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalEntry whereUpdatedAt($value)
 */
	class LedgerJournalEntry extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $journal_line_id
 * @property int $journal_entry_id
 * @property int $ledger_account_id
 * @property int $currency_id
 * @property int $debit_minor
 * @property int $credit_minor
 * @property-read LedgerAccount $account
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Currency $currency
 * @property-read \App\Models\LedgerJournalEntry $entry
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereCreditMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereDebitMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereJournalLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerJournalLine whereUpdatedAt($value)
 */
	class LedgerJournalLine extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $module_id
 * @property string $module_key
 * @property string $module_name
 * @property string|null $module_icon
 * @property string|null $module_desc
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module byKey(string $key)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereModuleDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereModuleIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereModuleKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereModuleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module withoutTrashed()
 */
	class Module extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $network_id
 * @property int $owner_id
 * @property string $network_uuid
 * @property string $network_name
 * @property string|null $network_profile_logo
 * @property string $network_phone_number
 * @property string|null $network_email
 * @property int $governorate_id
 * @property int $directorate_id
 * @property string|null $network_address
 * @property int|null $approved_by
 * @property int|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property \App\Enums\NetworkStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $approvedBy
 * @property-read \App\Models\Directorate|null $directorate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read \App\Models\Governorate|null $governorate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkUser> $memberships
 * @property-read int|null $memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkPos> $networkPos
 * @property-read int|null $network_pos_count
 * @property-read \App\Models\User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $pos
 * @property-read int|null $pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWallet> $posWallets
 * @property-read int|null $pos_wallets_count
 * @property-read \App\Models\User|null $rejectedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $requests
 * @property-read int|null $requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoice> $saleInvoices
 * @property-read int|null $sale_invoices_count
 * @property-read \App\Models\NetworkSmsSetting|null $smsSetting
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicket> $supportTickets
 * @property-read int|null $support_tickets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackageDiscount> $voucherPackageDiscounts
 * @property-read int|null $voucher_package_discounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackage> $voucherPackages
 * @property-read int|null $voucher_packages_count
 * @property-read \App\Models\NetworkWallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network inDirectorate(int $directorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network inGovernorate(int $governorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network status(\App\Enums\NetworkStatus|string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereDirectorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereGovernorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkPhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkProfileLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereRejectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereRejectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network withoutTrashed()
 */
	class Network extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $audit_id
 * @property int $network_id
 * @property int $user_id
 * @property int $event_seq
 * @property string $action
 * @property int $actor_id
 * @property array<array-key, mixed>|null $old_state
 * @property array<array-key, mixed>|null $new_state
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User|null $actor
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereAuditId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereEventSeq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereNewState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereOldState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkMembershipAuditLog whereUserId($value)
 */
	class NetworkMembershipAuditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $network_pos_id
 * @property int $network_id
 * @property int $pos_id
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereNetworkPosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereUpdatedAt($value)
 */
	class NetworkPos extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $challenge_id
 * @property string $challenge_uuid
 * @property int $network_id
 * @property int|null $pos_id
 * @property int $created_by_user_id
 * @property string $challenge_nonce
 * @property string $payload_token
 * @property \App\Enums\LinkingChallengeStatus $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $consumed_at
 * @property int|null $consumed_by_user_id
 * @property int|null $consumed_by_pos_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Pos|null $consumedPos
 * @property-read \App\Models\User|null $consumer
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereChallengeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereChallengeNonce($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereChallengeUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereConsumedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereConsumedByPosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereConsumedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge wherePayloadToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPosLinkingChallenge whereUpdatedAt($value)
 */
	class NetworkPosLinkingChallenge extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $network_id
 * @property bool $is_enabled
 * @property bool $notify_receipt
 * @property bool $notify_payment
 * @property bool $notify_pos_withdraw
 * @property bool $notify_expense
 * @property bool $notify_sale_invoice
 * @property bool $notify_voucher_request
 * @property bool $notify_low_balance
 * @property bool $notify_daily_summary
 * @property bool $notify_monthly_summary
 * @property bool $use_custom_gateway
 * @property string $server_type
 * @property string $base_url
 * @property string $auth_type
 * @property string|null $api_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $username
 * @property string|null $password
 * @property string|null $device_id
 * @property int $sim_number
 * @property bool $skip_phone_validation
 * @property int $device_active_within
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Network|null $network
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereApiToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereAuthType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereBaseUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereDeviceActiveWithin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyDailySummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyExpense($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyLowBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyMonthlySummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyPosWithdraw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyReceipt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifySaleInvoice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereNotifyVoucherRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereServerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereSimNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereSkipPhoneValidation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereUseCustomGateway($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkSmsSetting whereUsername($value)
 */
	class NetworkSmsSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $network_id
 * @property int $user_id
 * @property int $role_id
 * @property string|null $role_scope
 * @property string $status
 * @property int $last_event_seq
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkMembershipAuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereLastEventSeq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereRoleScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkUser whereUserId($value)
 */
	class NetworkUser extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $wallet_id
 * @property int $network_id
 * @property string $wallet_uuid
 * @property numeric $wallet_balance
 * @property numeric $opening_balance
 * @property string $currency_code
 * @property string $opening_balance_source
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkWalletLog> $logs
 * @property-read int|null $logs_count
 * @property-read \App\Models\Network|null $network
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereOpeningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereOpeningBalanceSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereWalletBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereWalletUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet withoutTrashed()
 */
	class NetworkWallet extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $log_id
 * @property int $wallet_id
 * @property int|null $transfer_id
 * @property \App\Enums\WalletOperationType $log_operation
 * @property numeric $log_amount
 * @property numeric $balance_before
 * @property numeric $balance_after
 * @property string $currency_code
 * @property string $balance_snapshot_source
 * @property string $log_statement
 * @property \Illuminate\Support\Carbon $log_date
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\WalletTransfer|null $transfer
 * @property-read \App\Models\NetworkWallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog deposits()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog forWallet(int $walletId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereBalanceBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereBalanceSnapshotSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogOperation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogStatement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog withdrawals()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog withoutTrashed()
 */
	class NetworkWalletLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $notification_id
 * @property int $user_id
 * @property \App\Enums\NotificationType $notification_type
 * @property string $notification_title
 * @property string $notification_message
 * @property string|null $notification_image
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification read()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotificationImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotificationMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotificationTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereNotificationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification withoutTrashed()
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $snapshot_id
 * @property string $snapshot_uuid
 * @property int $network_id
 * @property int|null $pos_id
 * @property string $document_type
 * @property int $document_id
 * @property int $document_version
 * @property string $document_reference
 * @property string $template_id
 * @property string $template_version
 * @property int $schema_version
 * @property string $currency_code
 * @property array<array-key, mixed> $source_snapshot
 * @property string $snapshot_sha256
 * @property string|null $artifact_path
 * @property string|null $artifact_sha256
 * @property int|null $artifact_size
 * @property \Illuminate\Support\Carbon $issued_at
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereArtifactPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereArtifactSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereArtifactSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereDocumentReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereDocumentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereSchemaVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereSnapshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereSnapshotSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereSnapshotUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereSourceSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereTemplateVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalDocumentSnapshot whereUpdatedAt($value)
 */
	final class OperationalDocumentSnapshot extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $report_export_id
 * @property string $export_uuid
 * @property int $network_id
 * @property int|null $pos_id
 * @property int $requested_by
 * @property \App\Enums\OperationalReportType $report_type
 * @property array<array-key, mixed> $filters
 * @property \App\Enums\OperationalReportExportStatus $status
 * @property string|null $artifact_path
 * @property string|null $artifact_sha256
 * @property int|null $artifact_size
 * @property int $record_count
 * @property string|null $failure_code
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\User|null $requestedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereArtifactPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereArtifactSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereArtifactSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereExportUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereFailureCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereRecordCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereReportExportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereReportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportExport whereUpdatedAt($value)
 */
	class OperationalReportExport extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $report_preset_id
 * @property string $preset_uuid
 * @property int $user_id
 * @property int $network_id
 * @property int|null $pos_id
 * @property string $scope_key
 * @property \App\Enums\OperationalReportType $report_type
 * @property string $name
 * @property int $preset_schema_version
 * @property int $report_schema_version
 * @property array<array-key, mixed> $criteria
 * @property array<array-key, mixed> $presentation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereCriteria($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset wherePresentation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset wherePresetSchemaVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset wherePresetUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereReportPresetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereReportSchemaVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereReportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereScopeKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OperationalReportPreset whereUserId($value)
 */
	final class OperationalReportPreset extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $otp_id
 * @property int $user_id
 * @property string $otp_code
 * @property \App\Enums\OtpType $otp_type
 * @property string $device_uuid
 * @property int $otp_failure_attempts
 * @property bool $is_used
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp expiresIn()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereDeviceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereIsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereOtpCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereOtpFailureAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereOtpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereOtpType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Otp whereUserId($value)
 */
	class Otp extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $message_uuid
 * @property string $aggregate_type
 * @property int $aggregate_id
 * @property string $event_type
 * @property array<array-key, mixed> $payload
 * @property array<array-key, mixed>|null $headers
 * @property string $status
 * @property int $retry_count
 * @property int $max_retries
 * @property string|null $lease_token
 * @property \Illuminate\Support\Carbon|null $lease_expires_at
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereAggregateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereAggregateType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereLeaseExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereLeaseToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereMaxRetries($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereMessageUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereRetryCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxMessage whereUpdatedAt($value)
 */
	class OutboxMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $permission_id
 * @property int $module_id
 * @property int $action_type_id
 * @property string $permission_key
 * @property string $permission_name
 * @property string|null $permission_desc
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\ActionType|null $actionType
 * @property-read \App\Models\Module|null $module
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RolePermission> $rolePermissions
 * @property-read int|null $role_permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserPermission> $userPermissions
 * @property-read int|null $user_permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission byKey(string $key)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission byModule(int $moduleId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereActionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission wherePermissionDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission wherePermissionKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission wherePermissionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission withoutTrashed()
 */
	class Permission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $pos_id
 * @property int $owner_id
 * @property string $pos_uuid
 * @property string $pos_name
 * @property string|null $pos_profile_logo
 * @property string $pos_phone_number
 * @property string|null $pos_email
 * @property int $governorate_id
 * @property int $directorate_id
 * @property string|null $pos_address
 * @property int|null $approved_by
 * @property int|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property \App\Enums\PosStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $approvedBy
 * @property-read \App\Models\Directorate|null $directorate
 * @property-read \App\Models\Governorate|null $governorate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosUser> $memberships
 * @property-read int|null $memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkPos> $networkPos
 * @property-read int|null $network_pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $networks
 * @property-read int|null $networks_count
 * @property-read \App\Models\User|null $owner
 * @property-read \App\Models\User|null $rejectedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $requests
 * @property-read int|null $requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoice> $saleInvoices
 * @property-read int|null $sale_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicket> $supportTickets
 * @property-read int|null $support_tickets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackageDiscount> $voucherPackageDiscounts
 * @property-read int|null $voucher_package_discounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWallet> $wallets
 * @property-read int|null $wallets_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos inDirectorate(int $directorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos inGovernorate(int $governorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos linkedToNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos status(\App\Enums\PosStatus|string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereDirectorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereGovernorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosPhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosProfileLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereRejectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereRejectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos withoutTrashed()
 */
	class Pos extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $audit_id
 * @property int $pos_id
 * @property int $user_id
 * @property int $event_seq
 * @property string $action
 * @property int $actor_id
 * @property array<array-key, mixed>|null $old_state
 * @property array<array-key, mixed>|null $new_state
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User|null $actor
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereAuditId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereEventSeq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereNewState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereOldState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosMembershipAuditLog whereUserId($value)
 */
	class PosMembershipAuditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $repayment_id
 * @property string $repayment_uuid
 * @property int $network_id
 * @property int $pos_id
 * @property numeric $amount
 * @property string $payment_method
 * @property string|null $reference_number
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $repayment_date
 * @property string $status
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property int|null $reversal_repayment_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosRepaymentAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\User|null $reversedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereRepaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereRepaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereRepaymentUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereReversalReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereReversalRepaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereReversedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereReversedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepayment withoutTrashed()
 */
	class PosRepayment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $allocation_id
 * @property int $repayment_id
 * @property int $invoice_id
 * @property int $network_id
 * @property int $pos_id
 * @property numeric $allocated_amount
 * @property string $status
 * @property \Illuminate\Support\Carbon $allocated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SaleInvoice|null $invoice
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\PosRepayment|null $repayment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereAllocatedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereAllocatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereAllocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereRepaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosRepaymentAllocation whereUpdatedAt($value)
 */
	class PosRepaymentAllocation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $pos_id
 * @property int $user_id
 * @property int $role_id
 * @property string|null $role_scope
 * @property string $status
 * @property int $last_event_seq
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosMembershipAuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereLastEventSeq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereRoleScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosUser whereUserId($value)
 */
	class PosUser extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $wallet_id
 * @property int $network_id
 * @property int $pos_id
 * @property string $wallet_uuid
 * @property numeric $wallet_balance
 * @property numeric $opening_balance
 * @property string $opening_balance_source
 * @property string $currency_code
 * @property numeric|null $credit_limit
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWalletLog> $logs
 * @property-read int|null $logs_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet forNetworkAndPos(int $networkId, int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereCreditLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereOpeningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereOpeningBalanceSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereWalletBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereWalletUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet withoutTrashed()
 */
	class PosWallet extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $log_id
 * @property int $wallet_id
 * @property int|null $transfer_id
 * @property \App\Enums\WalletOperationType $log_operation_type
 * @property numeric $log_amount
 * @property numeric $balance_before
 * @property numeric $balance_after
 * @property string $balance_snapshot_source
 * @property string $currency_code
 * @property string $log_statement
 * @property \Illuminate\Support\Carbon $log_date
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\WalletTransfer|null $transfer
 * @property-read \App\Models\PosWallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog deposits()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog forWallet(int $walletId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereBalanceBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereBalanceSnapshotSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogOperationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogStatement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog withdrawals()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog withoutTrashed()
 */
	class PosWalletLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $report_run_id
 * @property string $report_run_uuid
 * @property string $scope_type
 * @property int|null $network_id
 * @property int $requested_by
 * @property string $permission_key
 * @property string $report_key
 * @property string $definition_version
 * @property string $status
 * @property array<string, mixed> $filters
 * @property string $artifact_format
 * @property string|null $artifact_path
 * @property string|null $artifact_sha256
 * @property int $artifact_bytes
 * @property int $records_count
 * @property bool $is_truncated
 * @property array<string, mixed>|null $result_metadata
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereArtifactBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereArtifactFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereArtifactPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereArtifactSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereDefinitionVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereIsTruncated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun wherePermissionKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereQueuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereRecordsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereReportKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereReportRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereReportRunUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereResultMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereScopeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportRun whereUpdatedAt($value)
 */
	class ReportRun extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $request_id
 * @property int $network_id
 * @property int $pos_id
 * @property string $request_uuid
 * @property \App\Enums\SaleType $sale_type
 * @property numeric $request_total_amount
 * @property \Illuminate\Support\Carbon $request_date
 * @property \App\Enums\RequestStatus $status
 * @property int $created_by
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $review_date
 * @property string|null $request_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\SaleInvoice|null $invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\User|null $reviewedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request forSaleType(\App\Enums\SaleType|string $saleType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request status(\App\Enums\RequestStatus|string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereRequestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereRequestNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereRequestTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereRequestUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereReviewDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereSaleType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request withoutTrashed()
 */
	class Request extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $item_id
 * @property int $request_id
 * @property int $package_id
 * @property numeric $voucher_price
 * @property int $voucher_count
 * @property numeric $item_discount
 * @property numeric|null $item_cash_discount نسبة خصم النقد المجمدة لحظة تقديم الطلب
 * @property numeric|null $item_credit_discount نسبة خصم الآجل المجمدة لحظة تقديم الطلب
 * @property numeric $item_total_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Request|null $request
 * @property-read \App\Models\VoucherPackage|null $voucherPackage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemCashDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemCreditDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereVoucherCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereVoucherPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem withoutTrashed()
 */
	class RequestItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $role_id
 * @property string $role_key
 * @property string $role_name
 * @property \App\Enums\RoleScope $scope
 * @property string|null $role_desc
 * @property bool $is_system
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RolePermission> $rolePermissions
 * @property-read int|null $role_permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role byKey(\App\Enums\RoleKey|string $roleKey)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role system()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereIsSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRoleDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRoleKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereRoleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutTrashed()
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $role_permission_id
 * @property int $role_id
 * @property int $permission_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Permission|null $permission
 * @property-read \App\Models\Role|null $role
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission forPermission(int $permissionId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission forRole(int $roleId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission whereRolePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RolePermission whereUpdatedAt($value)
 */
	class RolePermission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $invoice_id
 * @property int $network_id
 * @property int $pos_id
 * @property int|null $request_id
 * @property string $invoice_uuid
 * @property \App\Enums\SaleType $sale_type
 * @property numeric $invoice_total_amount
 * @property numeric $paid_amount
 * @property numeric $remaining_amount
 * @property \App\Enums\InvoicePaymentStatus $payment_status
 * @property \Illuminate\Support\Carbon $invoice_date
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoiceItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosRepaymentAllocation> $repaymentAllocations
 * @property-read int|null $repayment_allocations_count
 * @property-read \App\Models\Request|null $request
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice wherePaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereRemainingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereSaleType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice withoutTrashed()
 */
	class SaleInvoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $item_id
 * @property int $invoice_id
 * @property int $package_id
 * @property numeric $voucher_price
 * @property int $voucher_count
 * @property numeric $item_discount
 * @property numeric $item_total_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\SaleInvoice|null $saleInvoice
 * @property-read \App\Models\VoucherPackage|null $voucherPackage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereItemDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereItemTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereVoucherCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem whereVoucherPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoiceItem withoutTrashed()
 */
	class SaleInvoiceItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property ScheduledTaskType|string $task_type
 * @property int|null $network_id
 * @property string $scope_key
 * @property Carbon $target_date
 * @property string $schedule_slot
 * @property ScheduledTaskStatus|string $status
 * @property array|null $extra
 * @property Carbon|null $sent_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $locked_until
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Network|null $network
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun sent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun status(\App\Enums\ScheduledTaskStatus|string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun successful()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun targetDate(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereExtra($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereLockedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereScheduleSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereScopeKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereTargetDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereTaskType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduledTaskRun whereUpdatedAt($value)
 */
	class ScheduledTaskRun extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $ban_id
 * @property int|null $user_id
 * @property int|null $device_id
 * @property int $banned_by
 * @property \App\Enums\BanType $ban_type
 * @property string|null $ban_reason
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $bannedBy
 * @property-read \App\Models\UserDevice|null $device
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan activeBans()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan forDevice(int $deviceId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereBanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereBanReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereBanType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereBannedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereStartAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SecurityBan withoutTrashed()
 */
	class SecurityBan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $ticket_id
 * @property int $network_id
 * @property int $pos_id
 * @property string $ticket_uuid
 * @property string $ticket_subject
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\SupportTicketMessage|null $latestMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicketMessage> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereTicketSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereTicketUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicket withoutTrashed()
 */
	class SupportTicket extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $message_id
 * @property int $ticket_id
 * @property int $sent_by
 * @property string $message_body
 * @property \Illuminate\Support\Carbon $sent_at
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $sentBy
 * @property-read \App\Models\SupportTicket|null $ticket
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage read()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereMessageBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereSentBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage withoutTrashed()
 */
	class SupportTicketMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $log_id
 * @property int|null $user_id
 * @property string $module
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $log_date
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog forEntity(string $entityType, string|int $entityId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog forModule(string $module)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog recent(int $limit = 50)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereLogDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereUserId($value)
 */
	class SystemLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $setting_id
 * @property \App\Enums\SettingCategory $setting_category
 * @property string $setting_key
 * @property \App\Enums\ValueType $setting_value_type
 * @property string $setting_value
 * @property string|null $setting_desc
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read mixed|null $typed_value
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting byKey(string $key)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting category(\App\Enums\SettingCategory|string $category)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereSettingCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereSettingDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereSettingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereSettingKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereSettingValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereSettingValueType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemSetting withoutTrashed()
 */
	class SystemSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $treasury_account_id
 * @property string $account_code
 * @property string $name
 * @property string $status
 * @property int $currency_id
 * @property int|null $ledger_account_id
 * @property-read Currency $currency
 * @property-read LedgerAccount|null $ledgerAccount
 * @property string $account_type
 * @property string|null $institution_name
 * @property string|null $account_reference
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BillingPayment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereAccountReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereInstitutionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereTreasuryAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TreasuryAccount withoutTrashed()
 */
	class TreasuryAccount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $user_id
 * @property int|null $role_id
 * @property string|null $role_scope
 * @property string $user_full_name
 * @property string|null $user_profile_logo
 * @property string $user_phone_number
 * @property string $user_password
 * @property string|null $user_email
 * @property bool $user_2fa_enabled
 * @property bool $must_change_password
 * @property int $user_login_attempts
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \App\Enums\UserStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $approvedNetworks
 * @property-read int|null $approved_networks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $approvedPos
 * @property-read int|null $approved_pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SecurityBan> $bansIssued
 * @property-read int|null $bans_issued_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkWalletLog> $createdNetworkWalletLogs
 * @property-read int|null $created_network_wallet_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWalletLog> $createdPosWalletLogs
 * @property-read int|null $created_pos_wallet_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $createdRequests
 * @property-read int|null $created_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserDevice> $devices
 * @property-read int|null $devices_count
 * @property-read string $display_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkUser> $networkUsers
 * @property-read int|null $network_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $networks
 * @property-read int|null $networks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Otp> $otps
 * @property-read int|null $otps_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \App\Models\Pos|null $pos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosUser> $posUsers
 * @property-read int|null $pos_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $rejectedNetworks
 * @property-read int|null $rejected_networks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $rejectedPos
 * @property-read int|null $rejected_pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $reviewedRequests
 * @property-read int|null $reviewed_requests_count
 * @property-read \App\Models\Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoice> $saleInvoices
 * @property-read int|null $sale_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SecurityBan> $securityBans
 * @property-read int|null $security_bans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserSession> $sessions
 * @property-read int|null $sessions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicketMessage> $supportTicketMessages
 * @property-read int|null $support_ticket_messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $systemLogs
 * @property-read int|null $system_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserPermission> $userPermissions
 * @property-read int|null $user_permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User banned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User email(string $email)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User phone(string $phone)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User search(?string $term)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User status(\App\Enums\UserStatus|string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMustChangePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUser2faEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserPassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserPhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserProfileLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withRole(\App\Enums\RoleKey|string $roleKey)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $device_id
 * @property int $user_id
 * @property string $device_uuid
 * @property string $device_name
 * @property \App\Enums\DeviceType $device_type
 * @property string|null $fcm_token
 * @property bool $is_primary
 * @property \App\Enums\UserDeviceStatus $status
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SecurityBan> $securityBans
 * @property-read int|null $security_bans_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice banned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice clearFcmToken()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice deviceUuid(string $deviceUuid)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice forOtherUsers(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice notBanned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice primary()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereFcmToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice withFcmToken()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice withoutTrashed()
 */
	class UserDevice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $user_permission_id
 * @property int $user_id
 * @property int $permission_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Permission|null $permission
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission forPermission(int $permissionId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereUserPermissionId($value)
 */
	class UserPermission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $session_id
 * @property string $session_token
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $device_uuid
 * @property \Illuminate\Support\Carbon $logged_at
 * @property \Illuminate\Support\Carbon $last_activity_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereDeviceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereLastActivityAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereLoggedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereSessionToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereUserId($value)
 */
	class UserSession extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $package_id
 * @property int $network_id
 * @property string $package_name
 * @property string $package_color
 * @property numeric|null $package_balance
 * @property string|null $package_uptime
 * @property string|null $package_validity
 * @property int $package_voucher_count
 * @property numeric $package_voucher_price
 * @property numeric $package_default_cash_discount
 * @property numeric $package_default_credit_discount
 * @property bool $status
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackageDiscount> $discounts
 * @property-read int|null $discounts_count
 * @property-read \App\Models\Network|null $network
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestItem> $requestItems
 * @property-read int|null $request_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoiceItem> $saleInvoiceItems
 * @property-read int|null $sale_invoice_items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageDefaultCashDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageDefaultCreditDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageUptime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageValidity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageVoucherCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage wherePackageVoucherPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackage withoutTrashed()
 */
	class VoucherPackage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $discount_id
 * @property int $network_id
 * @property int $package_id
 * @property int $pos_id
 * @property numeric $discount_cash
 * @property numeric $discount_credit
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $discount_start_date
 * @property \Illuminate\Support\Carbon|null $discount_end_date
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $active_discriminator مميّز أحادي للسجلات غير المحذوفة فقط لضمان قيد فريد قطعي في MySQL 8
 * @property-read \App\Models\User|null $createdBy
 * @property-read string|null $display_end_date
 * @property-read string $validity_state
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\VoucherPackage|null $voucherPackage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount effective(?\Carbon\CarbonInterface $now = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount forPackageAndPos(int $packageId, int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereActiveDiscriminator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountCash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount withoutTrashed()
 */
	class VoucherPackageDiscount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $transfer_id
 * @property string $transfer_uuid
 * @property int $network_wallet_id
 * @property int $pos_wallet_id
 * @property numeric $amount
 * @property string $direction
 * @property int|null $original_transfer_id
 * @property bool|null $is_reversal
 * @property int|null $target_is_reversal
 * @property string $status
 * @property string|null $statement
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkWalletLog> $networkLogs
 * @property-read int|null $network_logs_count
 * @property-read \App\Models\NetworkWallet|null $networkWallet
 * @property-read WalletTransfer|null $originalTransfer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWalletLog> $posLogs
 * @property-read int|null $pos_logs_count
 * @property-read \App\Models\PosWallet|null $posWallet
 * @property-read WalletTransfer|null $reversal
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereIsReversal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereNetworkWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereOriginalTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer wherePosWalletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereStatement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereTargetIsReversal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereTransferUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletTransfer whereUpdatedAt($value)
 */
	class WalletTransfer extends \Eloquent {}
}

