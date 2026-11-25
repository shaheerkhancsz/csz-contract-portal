<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'clauses' => 'array',
            'is_active' => 'boolean',
            'default_billing_cycle' => BillingCycle::class,
            'default_fee' => 'decimal:2',
            'min_fee' => 'decimal:2',
            'max_fee' => 'decimal:2',
            'default_renewal_increase' => 'decimal:2',
            'default_late_fee' => 'decimal:2',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'current_version_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Save the working copy as a new version when the body, fields or clauses changed.
     * Contracts keep pointing at the version they were drafted from.
     */
    public function publishVersionIfChanged(?string $note = null): ?TemplateVersion
    {
        $current = $this->currentVersion;
        if ($current
            && $current->body === $this->body
            && json_encode($current->fields ?? []) === json_encode($this->fields ?? [])
            && json_encode($current->clauses ?? []) === json_encode($this->clauses ?? [])) {
            return null;
        }

        $version = $this->versions()->create([
            'version' => (int) $this->versions()->max('version') + 1,
            'body' => (string) $this->body,
            'fields' => $this->fields ?? [],
            'clauses' => $this->clauses ?? [],
            'change_note' => $note,
            'created_by' => auth()->id(),
        ]);

        $this->forceFill(['current_version_id' => $version->id])->saveQuietly();
        $this->setRelation('currentVersion', $version);

        return $version;
    }
}
