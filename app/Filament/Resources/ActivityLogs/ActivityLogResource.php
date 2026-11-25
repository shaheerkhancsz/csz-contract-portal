<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\Contract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 30;
    protected static ?string $navigationLabel = 'Activity log';
    protected static ?string $modelLabel = 'activity';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y, H:i')->label('When')->sortable(),
                TextColumn::make('user.name')->label('Who')->placeholder('System'),
                TextColumn::make('action')->badge()->color('gray'),
                TextColumn::make('subject')->label('Record')->state(function (ActivityLog $a) {
                    $s = $a->subject;

                    return match (true) {
                        $s instanceof Contract => $s->agreement_no,
                        $s !== null => class_basename($s).' #'.$s->getKey(),
                        default => '—',
                    };
                }),
                TextColumn::make('description')->wrap()->searchable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('User')->relationship('user', 'name'),
                SelectFilter::make('action')->options(fn () => ActivityLog::query()->distinct()->pluck('action', 'action')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }
}
