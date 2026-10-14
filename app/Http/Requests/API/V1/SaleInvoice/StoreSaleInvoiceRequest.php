<?php

namespace App\Http\Requests\API\V1\SaleInvoice;

use App\Enums\PaymentType;
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
            'invoice_sale_type' => [
                'nullable',
                Rule::enum(PaymentType::class),
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
            'items.*.voucher_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'items.*.voucher_count' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.item_discount' => [
                'nullable',
                'numeric',
                'between:0,100',
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

    public function getSaleType(): PaymentType
    {
        $type = $this->input('invoice_sale_type');

        return $type ? PaymentType::tryFrom(strtoupper((string) $type)) ?? PaymentType::CASH : PaymentType::CASH;
    }

    /**
     * @return array<int, array{package_id: int, voucher_price: float, voucher_count: int, item_discount?: float}>
     */
    public function getItems(): array
    {
        return (array) $this->input('items', []);
    }
}
