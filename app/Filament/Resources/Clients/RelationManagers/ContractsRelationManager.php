<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';
    protected static ?string $title = 'Contracts';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agreement_no')->label('Agreement no.'),
                TextColumn::make('title'),
                TextColumn::make('status')->badge(),
                TextColumn::make('start_date')->date('d M Y'),
                TextColumn::make('end_date')->date('d M Y'),
                TextColumn::make('fee')->money(fn (Contract $c) => $c->currency)->suffix(fn (Contract $c) => ' '.$c->billing_cycle?->per()),
            ])
            ->headerActions([
                Action::make('new')->label('New contract')->icon('heroicon-o-plus')
                    ->url(fn () => ContractResource::getUrl('create', ['client' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordUrl(fn (Contract $c) => ContractResource::getUrl('view', ['record' => $c]));
    }
}
