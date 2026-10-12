<?php

namespace App\Http\Resources\API\V1\SubscriptionPlan;

use App\Enums\PaymentType;
use App\Enums\RequestStatus;
use App\Http\Resources\API\V1\BankAccount\BankAccountResource;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\SubscriptionPlanRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlanRequest
 */
class SubscriptionPlanRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'request_id' => $this->request_id,
            'network_id' => $this->network_id,
            'plan_id' => $this->plan_id,
            'requested_by' => $this->requested_by,
            'request_uuid' => $this->request_uuid,
            'request_total_amount' => (float) $this->request_total_amount,
            'request_payment_type' => $this->request_payment_type instanceof PaymentType ? $this->request_payment_type->value : $this->request_payment_type,
            'request_payment_type_label' => $this->request_payment_type instanceof PaymentType
                ? $this->request_payment_type->label()
                : (PaymentType::tryFrom((string) $this->request_payment_type)?->label()),
            'account_id' => $this->account_id,
            'transaction_reference' => $this->transaction_reference,
            'transaction_receipt' => $this->transaction_receipt,
            'status' => $this->status instanceof RequestStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof RequestStatus
                ? $this->status->label()
                : (RequestStatus::tryFrom((string) $this->status)?->label()),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->reviewed_by,
            'review_date' => $this->review_date ? Carbon::parse($this->review_date)->toDateTimeString() : null,
            'request_note' => $this->request_note,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
            'requested_by_user' => $this->whenLoaded('requestedBy', fn () => new UserResource($this->requestedBy)),
            'reviewed_by_user' => $this->whenLoaded('reviewedBy', fn () => new UserResource($this->reviewedBy)),
            'account' => $this->whenLoaded('account', fn () => new BankAccountResource($this->account)),
        ];
    }
}
