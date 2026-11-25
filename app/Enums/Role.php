<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including templates, users and settings',
            self::Manager => 'Approve and send contracts, set fees outside template limits, cancel contracts',
            self::Staff => 'Draft contracts from templates, edit drafts, manage clients',
        };
    }
}
