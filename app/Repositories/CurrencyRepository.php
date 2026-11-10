<?php
namespace App\Repositories;
use App\Interfaces\CurrencyRepositoryInterface;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
class CurrencyRepository implements CurrencyRepositoryInterface {
 public function getActive(): Collection { return Currency::query()->active()->orderBy('code')->get(); }
 public function findActiveById(int $id): ?Currency { return Currency::query()->active()->find($id); }
 public function findByCode(string $code): ?Currency { return Currency::query()->where('code',$code)->first(); }
 public function create(array $data): Currency { return Currency::query()->create($data); }
 public function update(Currency $currency,array $data): Currency { $currency->fill($data)->save(); return $currency->refresh(); }
}