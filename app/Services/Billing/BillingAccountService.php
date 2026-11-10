<?php

namespace App\Services\Billing;

use App\Enums\BillingSubscriptionStatus;
use App\Models\{BillingAccount, BillingInvoice, BillingSubscription, Network, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingAccountService
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private CurrencyService $currencies
    ) {}

    public function forNetwork(int $networkId, User $actor): BillingAccount
    {
        $this->authorization->assertNetworkOwner($actor, $networkId);

        return BillingAccount::query()
            ->with(['defaultCurrency', 'contacts'])
            ->where('network_id', $networkId)
            ->orderBy('billing_account_id')
            ->firstOrFail();
    }

    public function createForNetwork(int $networkId, int $currencyId, User $actor): BillingAccount
    {
        $this->authorization->assertNetworkOwner($actor, $networkId);
        $currency = $this->currencies->getActive($currencyId);

        return DB::transaction(function () use ($networkId, $currency, $actor): BillingAccount {
            $network = Network::query()->lockForUpdate()->find($networkId);

            if (! $network || ! $network->isActive() || (int) $network->owner_id !== (int) $actor->user_id) {
                throw ValidationException::withMessages(['network_id' => ['الشبكة غير متاحة لمالك الحساب الحالي.']]);
            }

            $account = BillingAccount::query()->firstOrCreate(
                ['network_id' => $networkId, 'default_currency_id' => $currency->currency_id],
                [
                    'account_number' => 'BILL-'.str_pad((string) $networkId, 8, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(4)),
                    'default_currency_id' => $currency->currency_id,
                    'status' => 'ACTIVE',
                    'created_by' => $actor->user_id,
                    'legal_name' => $network->network_name,
                    'billing_email' => $network->network_email,
                    'billing_address' => $network->network_address,
                ]
            );

            return $account->load(['defaultCurrency', 'contacts']);
        }, 3);
    }

    public function changeDefaultCurrency(int $accountId, int $currencyId, User $actor): BillingAccount
    {
        $currency = $this->currencies->getActive($currencyId);

        return DB::transaction(function () use ($accountId, $currency, $actor): BillingAccount {
            $account = BillingAccount::query()->lockForUpdate()->findOrFail($accountId);
            $this->authorization->assertNetworkOwner($actor, (int) $account->network_id);

            $hasCommercialHistory = BillingSubscription::query()
                ->where('billing_account_id', $accountId)
                ->exists()
                || BillingInvoice::query()->where('billing_account_id', $accountId)->exists();

            if ($hasCommercialHistory) {
                throw ValidationException::withMessages([
                    'currency_id' => ['لا يمكن تغيير عملة حساب الفوترة بعد وجود اشتراكات أو فواتير.'],
                ]);
            }

            $account->default_currency_id = $currency->currency_id;
            $account->save();

            return $account->load('defaultCurrency');
        }, 3);
    }
}
