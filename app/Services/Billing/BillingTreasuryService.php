<?php

namespace App\Services\Billing;

use App\Models\{Currency, TreasuryAccount, User};
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingTreasuryService
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private CurrencyService $currencies,
        private BillingLedgerChartService $chart
    ) {}

    public function list(array $filters = []): Collection|CursorPaginator
    {
        $query = TreasuryAccount::query()->with(['currency', 'ledgerAccount']);

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', (int) $filters['currency_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$term}%")
                ->orWhere('account_code', 'like', "%{$term}%")
                ->orWhere('institution_name', 'like', "%{$term}%"));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('treasury_account_id')->get();
        }

        return $query->orderByDesc('treasury_account_id')->cursorPaginate($perPage);
    }

    public function create(array $data, User $actor): TreasuryAccount
    {
        $this->authorization->assertPlatform($actor);
        $currency = $this->currencies->getActive((int) $data['currency_id']);

        return DB::transaction(function () use ($data, $actor, $currency): TreasuryAccount {
            $treasury = TreasuryAccount::query()->create([
                'account_code' => 'TR-'.$currency->code.'-'.Str::upper(Str::random(10)),
                'name' => trim((string) $data['name']),
                'account_type' => strtoupper((string) $data['account_type']),
                'currency_id' => $currency->currency_id,
                'institution_name' => $data['institution_name'] ?? null,
                'account_reference' => $data['account_reference'] ?? null,
                'status' => strtoupper((string) ($data['status'] ?? 'ACTIVE')),
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->chart->ensureTreasuryAccount($treasury);

            return $treasury->refresh()->load(['currency', 'ledgerAccount']);
        }, 3);
    }

    public function setStatus(int $treasuryAccountId, string $status, User $actor): TreasuryAccount
    {
        $this->authorization->assertPlatform($actor);
        $status = strtoupper(trim($status));

        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw ValidationException::withMessages(['status' => ['حالة حساب الخزينة غير صالحة.']]);
        }

        return DB::transaction(function () use ($treasuryAccountId, $status): TreasuryAccount {
            $account = TreasuryAccount::query()->lockForUpdate()->findOrFail($treasuryAccountId);
            $account->status = $status;
            $account->save();

            if ($account->ledger_account_id) {
                $account->ledgerAccount()->update(['is_active' => $status === 'ACTIVE']);
            }

            return $account->refresh()->load(['currency', 'ledgerAccount']);
        }, 3);
    }
}
