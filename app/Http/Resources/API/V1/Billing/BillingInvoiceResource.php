<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Models\BillingCreditNote;
use App\Models\BillingInvoice;
use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingInvoice
 */
class BillingInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) $currency->decimal_places;
        $code = $currency->code;
        $invoiceAttributes = $this->resource->getAttributes();
        $hasPendingPaymentAggregate = array_key_exists('pending_payment_minor', $invoiceAttributes);
        $pendingPaymentMinor = (int) ($invoiceAttributes['pending_payment_minor'] ?? 0);
        $remainingMinor = (int) $this->remaining_minor;
        $availableToSubmitMinor = $hasPendingPaymentAggregate
            ? max(0, $remainingMinor - $pendingPaymentMinor)
            : 0;
        $canSubmitPayment = $hasPendingPaymentAggregate && in_array($this->status, [
            BillingInvoiceStatus::OPEN,
            BillingInvoiceStatus::PARTIALLY_PAID,
            BillingInvoiceStatus::PARTIALLY_CREDITED,
        ], true) && $availableToSubmitMinor > 0;

        return [
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice_number,
            'billing_account_id' => $this->billing_account_id,
            'billing_subscription_id' => $this->billing_subscription_id,
            'invoice_type' => $this->invoice_type,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'currency' => new CurrencyResource($currency),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'issued_on' => $this->issued_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'subtotal_minor' => (int) $this->subtotal_minor,
            'subtotal' => (new Money((int) $this->subtotal_minor, $code))->toDecimalString($places),
            'discount_minor' => (int) $this->discount_minor,
            'discount' => (new Money((int) $this->discount_minor, $code))->toDecimalString($places),
            'tax_minor' => (int) $this->tax_minor,
            'tax' => (new Money((int) $this->tax_minor, $code))->toDecimalString($places),
            'total_minor' => (int) $this->total_minor,
            'total' => (new Money((int) $this->total_minor, $code))->toDecimalString($places),
            'paid_minor' => (int) $this->paid_minor,
            'credited_minor' => (int) $this->credited_minor,
            'paid' => (new Money((int) $this->paid_minor, $code))->toDecimalString($places),
            'credited' => (new Money((int) $this->credited_minor, $code))->toDecimalString($places),
            'remaining_minor' => (int) $this->remaining_minor,
            'remaining' => (new Money((int) $this->remaining_minor, $code))->toDecimalString($places),
            'pending_payment_minor' => $hasPendingPaymentAggregate ? $pendingPaymentMinor : null,
            'pending_payment' => $hasPendingPaymentAggregate
                ? (new Money($pendingPaymentMinor, $code))->toDecimalString($places)
                : null,
            'available_to_submit_minor' => $hasPendingPaymentAggregate ? $availableToSubmitMinor : null,
            'available_to_submit' => $hasPendingPaymentAggregate
                ? (new Money($availableToSubmitMinor, $code))->toDecimalString($places)
                : null,
            'can_submit_payment' => $canSubmitPayment,
            'customer_snapshot' => $this->customer_snapshot,
            'pricing_snapshot' => $this->pricing_snapshot,
            'memo' => $this->memo,
            'lines' => BillingInvoiceLineResource::collection($this->whenLoaded('lines')),
            'credit_notes' => $this->whenLoaded('creditNotes', fn () => $this->creditNotes->map(fn (BillingCreditNote $note): array => [
                'credit_note_id' => $note->credit_note_id,
                'credit_note_number' => $note->credit_note_number,
                'status' => $note->status,
                'total_minor' => (int) $note->total_minor,
                'applied_to_receivable_minor' => (int) $note->applied_to_receivable_minor,
                'refundable_minor' => (int) $note->refundable_minor,
                'refunded_minor' => (int) $note->refunded_minor,
                'total' => (new Money((int) $note->total_minor, $code))->toDecimalString($places),
                'applied_to_receivable' => (new Money((int) $note->applied_to_receivable_minor, $code))->toDecimalString($places),
                'refundable' => (new Money((int) $note->refundable_minor, $code))->toDecimalString($places),
                'refunded' => (new Money((int) $note->refunded_minor, $code))->toDecimalString($places),
                'issued_at' => $note->issued_at?->toDateTimeString(),
            ])),
            'created_at' => $this->created_at?->toDateTimeString(),
            'finalized_at' => $this->finalized_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
        ];
    }
}
