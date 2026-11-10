<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BillingPaymentProof extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_payment_proofs';
    protected $primaryKey = 'payment_proof_id';

    protected $fillable = [
        'payment_id', 'file_path', 'original_name', 'mime_type',
        'file_size', 'sha256', 'uploaded_by', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'uploaded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('إثبات الدفع المرفق غير قابل للتعديل؛ أضف إثباتاً إضافياً مستقلاً.');
        });

        static::deleting(function (): void {
            throw new LogicException('إثباتات الدفع لا تحذف من سجل التدقيق.');
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BillingPayment::class, 'payment_id', 'payment_id');
    }
}
