<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';
    case OneOff = 'one_off';
    case Milestones = 'milestones';
    case PerTerm = 'per_term';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Annual => 'Annual',
            self::OneOff => 'One-off',
            self::Milestones => 'Milestones',
            self::PerTerm => 'Per term',
            self::None => 'No billing',
        };
    }

    /** Wording used after an amount, e.g. "PKR 7,500 per month". */
    public function per(): string
    {
        return match ($this) {
            self::Monthly => 'per month',
            self::Quarterly => 'per quarter',
            self::Annual => 'per year',
            self::PerTerm => 'per term',
            default => '',
        };
    }

    public function isRecurring(): bool
    {
        return in_array($this, [self::Monthly, self::Quarterly, self::Annual], true);
    }
}
