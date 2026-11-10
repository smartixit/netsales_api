<?php
namespace App\Repositories;
use App\Interfaces\BillingLedgerRepositoryInterface;
use App\Models\{LedgerAccount,LedgerJournalEntry};
use Illuminate\Database\Eloquent\Collection;
class BillingLedgerRepository implements BillingLedgerRepositoryInterface {
 public function findByIdempotencyKey(string $key): ?LedgerJournalEntry { return LedgerJournalEntry::query()->with('lines.account')->where('idempotency_key',$key)->first(); }
 public function findAccountsForCurrency(int $currencyId,array $accountIds): Collection {
  return LedgerAccount::query()->where('currency_id',$currencyId)->whereIn('ledger_account_id',$accountIds)->where('is_active',true)->lockForUpdate()->get();
 }
 public function createEntry(array $entryData,array $lines): LedgerJournalEntry {
  $entry=LedgerJournalEntry::query()->create($entryData);
  $entry->lines()->createMany($lines);
  return $entry->load(['currency','lines.account']);
 }
}