<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['withholds_tax' => 'boolean'];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->country])->filter()->implode(', ');
    }

    protected static function booted(): void
    {
        static::creating(fn (Client $c) => $c->created_by ??= auth()->id());
    }
}
