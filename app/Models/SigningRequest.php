<?php

namespace App\Models;

use App\Enums\SigningStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SigningRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SigningStatus::class,
            'intent_confirmed' => 'boolean',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'signed_at' => 'datetime',
            'voided_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function sentDocument(): BelongsTo { return $this->belongsTo(ContractDocument::class, 'sent_document_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function otps(): HasMany { return $this->hasMany(SigningOtp::class)->latest('id'); }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Open (pending/verified) and not expired — the link still works. */
    public function isUsable(): bool
    {
        return $this->status->isOpen() && ! $this->isExpired();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->status === SigningStatus::Verified;
    }

    public function signingUrl(): string
    {
        return route('sign', ['token' => $this->token]);
    }
}
