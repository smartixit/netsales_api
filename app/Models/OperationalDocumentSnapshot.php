<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OperationalDocumentSnapshot extends Model
{
    protected $table = 'operational_document_snapshots';
    protected $primaryKey = 'snapshot_id';

    protected $fillable = [
        'snapshot_uuid',
        'network_id',
        'pos_id',
        'document_type',
        'document_id',
        'document_version',
        'document_reference',
        'template_id',
        'template_version',
        'schema_version',
        'currency_code',
        'source_snapshot',
        'snapshot_sha256',
        'artifact_path',
        'artifact_sha256',
        'artifact_size',
        'issued_at',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $snapshot): void {
            $allowed = ['artifact_path', 'artifact_sha256', 'artifact_size', 'updated_at'];
            $changed = array_diff(array_keys($snapshot->getDirty()), $allowed);
            if ($changed !== []) {
                throw new LogicException(
                    'Official document snapshot source and issuance metadata are immutable.'
                );
            }

            if ($snapshot->getOriginal('artifact_path') !== null &&
                $snapshot->isDirty(['artifact_path', 'artifact_sha256', 'artifact_size'])) {
                throw new LogicException(
                    'An issued official document artifact cannot be replaced.'
                );
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Official document snapshots cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'network_id' => 'integer',
            'pos_id' => 'integer',
            'document_id' => 'integer',
            'document_version' => 'integer',
            'schema_version' => 'integer',
            'source_snapshot' => 'array',
            'artifact_size' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'pos_id', 'pos_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
