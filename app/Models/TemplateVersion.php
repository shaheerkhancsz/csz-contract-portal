<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'clauses' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return 'v'.$this->version.' · '.$this->created_at?->format('d M Y');
    }
}
