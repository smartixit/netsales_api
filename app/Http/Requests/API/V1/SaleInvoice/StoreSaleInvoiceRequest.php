<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Enums\SaleType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleInvoiceRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'pos_id' => [
                'required',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
            ],
            'sale_type' => [
                'nullable',
                Rule::enum(SaleType::class),
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.package_id' => [
                'required',
                'integer',
                Rule::exists('voucher_packages', 'package_id')->whereNull('deleted_at'),
            ],
            // NOTE: voucher_price is NO LONGER accepted from client (backend uses catalog price).
            'items.*.voucher_count' => [
                'required',
                'integer',
                'min:1',
            ],
            // NOTE: item_discount is NO LONGER accepted from client (backend uses effective discount).
            'paid_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->input('network_id');
    }

    public function getPosId(): int
    {
        return (int) $this->input('pos_id');
    }

    public function getSaleType(): SaleType
    {
        $type = $this->input('sale_type');

        return $type ? SaleType::tryFrom(strtoupper((string) $type)) ?? SaleType::CASH : SaleType::CASH;
    }

    public function getPaidAmount(): ?float
    {
        return $this->has('paid_amount') && $this->input('paid_amount') !== null
            ? (float) $this->input('paid_amount')
            : null;
    }

    /**
     * @return array<int, array{package_id: int, voucher_price: float, voucher_count: int, item_discount?: float}>
     */
    public function getItems(): array
    {
        return (array) $this->input('items', []);
    }
}
