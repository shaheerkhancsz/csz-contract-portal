<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery;

    protected $fillable = ['name', 'email', 'password', 'role', 'designation', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => 'staff', 'is_active' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Managers and admins can approve, send and cancel. */
    public function isManager(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Manager], true);
    }
}
