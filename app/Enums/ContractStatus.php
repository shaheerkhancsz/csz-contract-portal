<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';
    case AwaitingApproval = 'awaiting_approval';
    case Sent = 'sent';
    case Signed = 'signed';
    case Active = 'active';
    case Expiring = 'expiring';
    case Renewed = 'renewed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingApproval => 'Awaiting approval',
            self::Sent => 'Sent',
            self::Signed => 'Signed',
            self::Active => 'Active',
            self::Expiring => 'Expiring',
            self::Renewed => 'Renewed',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::AwaitingApproval => 'warning',
            self::Sent => 'info',
            self::Signed, self::Active => 'success',
            self::Expiring => 'warning',
            self::Renewed => 'primary',
            self::Expired, self::Cancelled => 'danger',
        };
    }

    /** Statuses whose text is frozen and can no longer be edited. */
    public function isLocked(): bool
    {
        return ! in_array($this, [self::Draft, self::AwaitingApproval], true);
    }
}
