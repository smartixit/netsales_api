<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    use ExactDateSerialization;

    protected $table = 'expense_attachments';

    protected $primaryKey = 'attachment_id';

    protected $keyType = 'int';

    protected $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'expense_id',
        'attachment_name',
        'attachment_path',
        'attachment_size',
        'attachment_mime',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
        ];
    }

    // Relationships
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id', 'expense_id');
    }

    // Accessors
    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attachment_path ? asset("storage/{$this->attachment_path}") : null
        );
    }
}
