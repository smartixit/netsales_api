<?php
namespace App\Interfaces;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
interface CurrencyRepositoryInterface {
 public function getActive(): Collection;
 public function findActiveById(int $id): ?Currency;
 public function findByCode(string $code): ?Currency;
 public function create(array $data): Currency;
 public function update(Currency $currency,array $data): Currency;
}