<?php
namespace App\Services\Billing;
use App\Interfaces\CurrencyRepositoryInterface;
use App\Models\Currency;
use Illuminate\Validation\ValidationException;
class CurrencyService {
 public function __construct(private CurrencyRepositoryInterface $currencies) {}
 public function listActive() { return $this->currencies->getActive(); }
 public function getActive(int $id): Currency {
  return $this->currencies->findActiveById($id) ?? throw ValidationException::withMessages(['currency_id'=>['العملة غير موجودة أو غير مفعلة.']]);
 }
 public function create(array $data): Currency {
  $code=strtoupper(trim((string)($data['code']??'')));
  if(!preg_match('/^[A-Z]{3}$/',$code)){throw ValidationException::withMessages(['code'=>['يجب أن يكون رمز العملة مكوناً من ثلاثة أحرف كبيرة وفق ISO 4217.']]);}
  if($this->currencies->findByCode($code)){throw ValidationException::withMessages(['code'=>['رمز العملة مسجل مسبقاً.']]);}
  $data['code']=$code;
  return $this->currencies->create($data);
 }
 public function update(int $id,array $data): Currency {
  $currency=$this->currencies->findActiveById($id) ?? throw ValidationException::withMessages(['currency_id'=>['العملة غير موجودة أو غير مفعلة.']]);
  unset($data['code'],$data['decimal_places']);
  return $this->currencies->update($currency,$data);
 }
}