<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('designation')->maxLength(255),
                Select::make('role')->options(Role::class)->required()->default(Role::Staff->value)
                    ->helperText(fn ($state) => ($r = $state instanceof Role ? $state : Role::tryFrom((string) $state)) ? $r->description() : null)->live(),
                TextInput::make('password')->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->minLength(10)
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : 'At least 10 characters.'),
                Toggle::make('is_active')->label('Can log in')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                IconColumn::make('app_authentication_secret')->label('2FA')->boolean()
                    ->state(fn (User $u) => filled($u->app_authentication_secret)),
                TextColumn::make('last_login_at')->since()->label('Last login'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
