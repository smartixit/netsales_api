<?php
namespace App\Interfaces;
use App\Models\{LedgerAccount,LedgerJournalEntry};
use Illuminate\Database\Eloquent\Collection;
interface BillingLedgerRepositoryInterface {
 public function findByIdempotencyKey(string $key): ?LedgerJournalEntry;
 public function findAccountsForCurrency(int $currencyId,array $accountIds): Collection;
 public function createEntry(array $entryData,array $lines): LedgerJournalEntry;
}