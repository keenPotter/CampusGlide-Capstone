<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function () {
            throw new LogicException('Audit log records are append-only.');
        });

        static::deleting(function () {
            throw new LogicException('Audit log records are append-only.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
