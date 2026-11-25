<?php

namespace App\Filament\Resources\Clients;

use App\Models\Client;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'company_name';

    /** Reused by the "new client" pop-up in the contract builder. */
    public static function formFields(): array
    {
        return [
            Section::make('Company')->columns(2)->schema([
                TextInput::make('company_name')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('contact_name')->label('Contact person'),
                TextInput::make('contact_designation')->label('Designation'),
                TextInput::make('email')->email(),
                TextInput::make('phone')->tel(),
                TextInput::make('website')->url()->placeholder('https://'),
            ]),
            Section::make('Address and tax')->columns(2)->schema([
                TextInput::make('address')->columnSpanFull(),
                TextInput::make('city'),
                TextInput::make('country')->default('Pakistan'),
                TextInput::make('ntn')->label('NTN'),
                TextInput::make('strn')->label('STRN'),
                Toggle::make('withholds_tax')->label('Client deducts withholding tax')->default(true)
                    ->helperText('Most companies in Pakistan deduct income tax when paying for IT services.'),
            ]),
            Section::make('Links to other systems')->columns(3)->collapsed()->schema([
                TextInput::make('whmcs_client_id')->label('WHMCS client ID')->numeric(),
                TextInput::make('zoho_customer_id')->label('Zoho Books customer ID'),
                TextInput::make('odoo_partner_id')->label('Odoo partner ID'),
            ]),
            Textarea::make('notes')->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formFields());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('company_name')->weight('bold'),
                TextEntry::make('contact_name')->label('Contact')->placeholder('—')
                    ->suffix(fn (Client $c) => $c->contact_designation ? ', '.$c->contact_designation : ''),
                TextEntry::make('email')->copyable()->placeholder('—'),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('website')->url(fn ($state) => $state)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('address')->state(fn (Client $c) => $c->fullAddress())->placeholder('—'),
                TextEntry::make('ntn')->label('NTN')->placeholder('—'),
                TextEntry::make('strn')->label('STRN')->placeholder('—'),
                IconEntry::make('withholds_tax')->label('Deducts WHT')->boolean(),
                TextEntry::make('whmcs_client_id')->label('WHMCS account')->placeholder('Not linked')
                    ->state(fn (Client $c) => $c->whmcs_client_id ? 'Linked · #'.$c->whmcs_client_id : null)
                    ->url(fn (Client $c) => $c->whmcs_client_id ? app(\App\Services\Whmcs\WhmcsClient::class)->adminClientUrl($c->whmcs_client_id) : null)
                    ->openUrlInNewTab(),
                TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('contact_name')->label('Contact')->searchable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('city')->toggleable(),
                TextColumn::make('contracts_count')->counts('contracts')->label('Contracts')->sortable(),
                TextColumn::make('created_at')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('company_name')
            ->filters([TrashedFilter::make()])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['company_name', 'contact_name', 'email'];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ContractsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
