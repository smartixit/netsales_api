<?php

namespace App\Services\Billing;

use App\Interfaces\CurrencyRepositoryInterface;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    public function __construct(
        private CurrencyRepositoryInterface $currencies,
        private BillingAuthorizationService $authorization
    ) {}

    public function listActive()
    {
        return $this->currencies->getActive();
    }

    public function getActive(int $id): Currency
    {
        return $this->currencies->findActiveById($id)
            ?? throw ValidationException::withMessages([
                'currency_id' => ['العملة غير موجودة أو غير مفعلة.'],
            ]);
    }

    public function create(array $data, User $actor): Currency
    {
        $this->authorization->assertPlatform($actor);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => ['يجب أن يكون رمز العملة مكوناً من ثلاثة أحرف كبيرة وفق ISO 4217.'],
            ]);
        }

        if ($this->currencies->findByCode($code)) {
            throw ValidationException::withMessages(['code' => ['رمز العملة مسجل مسبقاً.']]);
        }

        $decimalPlaces = filter_var($data['decimal_places'] ?? null, FILTER_VALIDATE_INT);
        if ($decimalPlaces === false || $decimalPlaces < 0 || $decimalPlaces > 4) {
            throw ValidationException::withMessages([
                'decimal_places' => ['عدد المنازل العشرية يجب أن يكون بين صفر وأربع منازل.'],
            ]);
        }

        $data['code'] = $code;
        $data['decimal_places'] = $decimalPlaces;

        return $this->currencies->create($data);
    }

    public function update(int $id, array $data, User $actor): Currency
    {
        $this->authorization->assertPlatform($actor);

        $currency = $this->currencies->findActiveById($id)
            ?? throw ValidationException::withMessages([
                'currency_id' => ['العملة غير موجودة أو غير مفعلة.'],
            ]);

        unset($data['code'], $data['numeric_code'], $data['decimal_places']);

        return $this->currencies->update($currency, $data);
    }
}
