<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SigningStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';     // link sent, email not yet verified
    case Verified = 'verified';   // OTP confirmed, viewing / ready to sign
    case Signed = 'signed';       // completed
    case Voided = 'voided';       // cancelled by staff

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting signature',
            self::Verified => 'Identity verified',
            self::Signed => 'Signed',
            self::Voided => 'Voided',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Verified => 'warning',
            self::Signed => 'success',
            self::Voided => 'gray',
        };
    }

    /** Still usable by the client (not finished or cancelled). Expiry is checked separately. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Verified], true);
    }
}
