<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $table = 'activity_log';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subject(): MorphTo { return $this->morphTo(); }

    public static function record(string $action, ?Model $subject = null, ?string $description = null, array $properties = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
