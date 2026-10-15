<?php

namespace App\Http\Resources\API\V1\Pos;

use App\Enums\WalletOperationType;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\PosWalletLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosWalletLog
 */
class PosWalletLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'log_id' => $this->log_id,
            'wallet_id' => $this->wallet_id,
            'log_operation_type' => $this->log_operation_type instanceof WalletOperationType ? $this->log_operation_type->value : $this->log_operation_type,
            'log_operation_type_label' => $this->log_operation_type instanceof WalletOperationType
                ? $this->log_operation_type->label()
                : (WalletOperationType::tryFrom((string) $this->log_operation_type)?->label()),
            'log_amount' => (float) $this->log_amount,
            'log_statement' => $this->log_statement,
            'log_date' => $this->log_date ? Carbon::parse($this->log_date)->toDateTimeString() : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'wallet' => $this->whenLoaded('wallet', fn () => new PosWalletResource($this->wallet)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
        ];
    }
}
