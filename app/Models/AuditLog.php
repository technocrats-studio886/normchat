<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'metadata_json',
        'created_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Catat satu aksi. $target boleh model (tipe & id diambil otomatis) atau null.
     */
    public static function record(
        string $action,
        ?int $actorId,
        ?Model $target = null,
        array $metadata = [],
        ?int $groupId = null
    ): self {
        return static::create([
            'group_id' => $groupId ?? ($target instanceof Group ? $target->id : null),
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'metadata_json' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
