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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanRequest> $subscriptionPlanRequests
 * @property-read int|null $subscription_plan_requests_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount byCurrency(string $currency)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount byType(\App\Enums\BankAccountType $type)
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
 * @property-read string|null $file_url
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
 * @property int $feature_id
 * @property string $feature_name
 * @property \App\Enums\ValueType $feature_value_type
 * @property string|null $feature_value
 * @property string|null $feature_desc
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanFeature> $planFeatures
 * @property-read int|null $plan_features_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlan> $plans
 * @property-read int|null $plans_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereFeatureDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereFeatureName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereFeatureValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereFeatureValueType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feature withoutTrashed()
 */
	class Feature extends \Eloquent {}
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
 * @property string $network_uuid
 * @property string $network_name
 * @property string|null $network_profile_logo
 * @property string $network_holder_name
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkPos> $networkPos
 * @property-read int|null $network_pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $pos
 * @property-read int|null $pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWallet> $posWallets
 * @property-read int|null $pos_wallets_count
 * @property-read \App\Models\User|null $rejectedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $requests
 * @property-read int|null $requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoice> $saleInvoices
 * @property-read int|null $sale_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanRequest> $subscriptionRequests
 * @property-read int|null $subscription_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProviderSubscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicket> $supportTickets
 * @property-read int|null $support_tickets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackageDiscount> $voucherPackageDiscounts
 * @property-read int|null $voucher_package_discounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackage> $voucherPackages
 * @property-read int|null $voucher_packages_count
 * @property-read \App\Models\NetworkWallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network inDirectorate(int $directorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network inGovernorate(int $governorateId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereDirectorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereGovernorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkHolderName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkPhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkProfileLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Network whereNetworkUuid($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereNetworkPosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos wherePosId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkPos withoutTrashed()
 */
	class NetworkPos extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $wallet_id
 * @property int $network_id
 * @property string $wallet_uuid
 * @property numeric $wallet_balance
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkWalletLog> $logs
 * @property-read int|null $logs_count
 * @property-read \App\Models\Network|null $network
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWallet whereNetworkId($value)
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
 * @property \App\Enums\WalletOperationType $log_operation
 * @property numeric $log_amount
 * @property string $log_statement
 * @property \Illuminate\Support\Carbon $log_date
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\NetworkWallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog deposits()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog forWallet(int $walletId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogOperation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NetworkWalletLog whereLogStatement($value)
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
 * @property int $otp_id
 * @property int $user_id
 * @property string $otp_code
 * @property \App\Enums\OtpType $otp_type
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
 * @property string $pos_uuid
 * @property string $pos_name
 * @property string|null $pos_profile_logo
 * @property string $pos_holder_name
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NetworkPos> $networkPos
 * @property-read int|null $network_pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $networks
 * @property-read int|null $networks_count
 * @property-read \App\Models\User|null $rejectedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $requests
 * @property-read int|null $requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoice> $saleInvoices
 * @property-read int|null $sale_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicket> $supportTickets
 * @property-read int|null $support_tickets_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VoucherPackageDiscount> $voucherPackageDiscounts
 * @property-read int|null $voucher_package_discounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWallet> $wallets
 * @property-read int|null $wallets_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereDirectorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos whereGovernorateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pos wherePosHolderName($value)
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
 * @property int $wallet_id
 * @property int $network_id
 * @property int $pos_id
 * @property string $wallet_uuid
 * @property numeric $wallet_balance
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PosWalletLog> $logs
 * @property-read int|null $logs_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet forNetworkAndPos(int $networkId, int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWallet whereNetworkId($value)
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
 * @property \App\Enums\WalletOperationType $log_operation_type
 * @property numeric $log_amount
 * @property string $log_statement
 * @property \Illuminate\Support\Carbon $log_date
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\PosWallet|null $wallet
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog deposits()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog forWallet(int $walletId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogOperationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosWalletLog whereLogStatement($value)
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
 * @property int $subscription_id
 * @property int $network_id
 * @property int $plan_id
 * @property \Illuminate\Support\Carbon $subscription_start_date
 * @property \Illuminate\Support\Carbon|null $subscription_end_date
 * @property \App\Enums\ProviderSubscriptionStatus $status
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanFeatureConsumption> $consumptions
 * @property-read int|null $consumptions_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\SubscriptionPlan|null $plan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereSubscriptionEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereSubscriptionStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSubscription withoutTrashed()
 */
	class ProviderSubscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $request_id
 * @property int $network_id
 * @property int $pos_id
 * @property string $request_uuid
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\User|null $reviewedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request forPos(int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Request rejected()
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
 * @property int $item_voucher_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereItemVoucherCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestItem whereUpdatedAt($value)
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
 * @property string $invoice_uuid
 * @property string $invoice_sale_type
 * @property numeric $invoice_total_amount
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceSaleType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereInvoiceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleInvoice wherePosId($value)
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
 * @property int $plan_id
 * @property string $plan_uuid
 * @property string $plan_name
 * @property numeric $plan_price
 * @property numeric $plan_discount
 * @property int $plan_validity
 * @property string|null $plan_desc
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Feature> $features
 * @property-read int|null $features_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanFeature> $planFeatures
 * @property-read int|null $plan_features_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanRequest> $subscriptionRequests
 * @property-read int|null $subscription_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProviderSubscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanDesc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePlanValidity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan withoutTrashed()
 */
	class SubscriptionPlan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $subscription_plan_feature_id
 * @property int $plan_id
 * @property int $feature_id
 * @property string $plan_feature_value
 * @property bool $status
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanFeatureConsumption> $consumptions
 * @property-read int|null $consumptions_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Feature|null $feature
 * @property-read \App\Models\SubscriptionPlan|null $plan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature forPlan(int $planId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature wherePlanFeatureValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereSubscriptionPlanFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeature withoutTrashed()
 */
	class SubscriptionPlanFeature extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $consumption_id
 * @property int $subscription_id
 * @property int $feature_id
 * @property int $consumption_value
 * @property \Illuminate\Support\Carbon|null $last_consumption_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SubscriptionPlanFeature|null $planFeature
 * @property-read \App\Models\ProviderSubscription|null $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption forFeature(int $featureId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption forSubscription(int $subscriptionId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereConsumptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereConsumptionValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereLastConsumptionAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanFeatureConsumption whereUpdatedAt($value)
 */
	class SubscriptionPlanFeatureConsumption extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $request_id
 * @property int $network_id
 * @property int $plan_id
 * @property int $requested_by
 * @property string $request_uuid
 * @property numeric $request_total_amount
 * @property \App\Enums\PaymentType $request_payment_type
 * @property int|null $account_id
 * @property string|null $transaction_reference
 * @property string|null $transaction_receipt
 * @property \App\Enums\RequestStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property string|null $request_note
 * @property \Illuminate\Support\Carbon|null $review_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\BankAccount|null $bankAccount
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\SubscriptionPlan|null $plan
 * @property-read \App\Models\User|null $requestedBy
 * @property-read \App\Models\User|null $reviewedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest forNetwork(int $networkId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereNetworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRequestNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRequestPaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRequestTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRequestUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereReviewDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereTransactionReceipt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlanRequest withoutTrashed()
 */
	class SubscriptionPlanRequest extends \Eloquent {}
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog onlyTrashed()
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog withoutTrashed()
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
 * @property int $user_id
 * @property int $role_id
 * @property string $user_full_name
 * @property string|null $user_profile_logo
 * @property string $user_phone_number
 * @property string $user_password
 * @property string|null $user_email
 * @property bool $user_2fa_enabled
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Otp> $otps
 * @property-read int|null $otps_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProviderSubscription> $providerSubscriptions
 * @property-read int|null $provider_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Network> $rejectedNetworks
 * @property-read int|null $rejected_networks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pos> $rejectedPos
 * @property-read int|null $rejected_pos_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request> $reviewedRequests
 * @property-read int|null $reviewed_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanRequest> $reviewedSubscriptionPlanRequests
 * @property-read int|null $reviewed_subscription_plan_requests_count
 * @property-read \App\Models\Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleInvoice> $saleInvoices
 * @property-read int|null $sale_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SecurityBan> $securityBans
 * @property-read int|null $security_bans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserSession> $sessions
 * @property-read int|null $sessions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubscriptionPlanRequest> $subscriptionPlanRequests
 * @property-read int|null $subscription_plan_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupportTicketMessage> $supportTicketMessages
 * @property-read int|null $support_ticket_messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $systemLogs
 * @property-read int|null $system_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserPermission> $userPermissions
 * @property-read int|null $user_permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User banned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice primary()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereDeviceUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserDevice whereUserId($value)
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
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereLastActivityAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereLoggedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSession whereSessionId($value)
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
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Network|null $network
 * @property-read \App\Models\Pos|null $pos
 * @property-read \App\Models\VoucherPackage|null $voucherPackage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount forPackageAndPos(int $packageId, int $posId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountCash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VoucherPackageDiscount whereDiscountId($value)
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

