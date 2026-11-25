<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocument extends Model
{
    protected $guarded = ['id'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'sent' => 'Approved PDF (as sent)',
            'signed_upload' => 'Signed copy (uploaded)',
            'signed' => 'Signed PDF',
            default => ucfirst($this->kind),
        };
    }
}
