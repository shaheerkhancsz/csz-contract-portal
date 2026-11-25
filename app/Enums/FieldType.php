<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FieldType: string implements HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Money = 'money';
    case Percent = 'percent';
    case Date = 'date';
    case Url = 'url';
    case Email = 'email';
    case Select = 'select';
    case List = 'list';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Money => 'Amount (PKR)',
            self::Percent => 'Percentage',
            self::Date => 'Date',
            self::Url => 'Website address',
            self::Email => 'Email',
            self::Select => 'Choice from a list',
            self::List => 'Bullet list (one item per line)',
        };
    }
}
