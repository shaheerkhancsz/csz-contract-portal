<?php

namespace App\Filament\Resources\Templates\RelationManagers;

use App\Models\ActivityLog;
use App\Models\TemplateVersion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Html;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';
    protected static ?string $title = 'Version history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->prefix('v')->weight('bold')
                    ->description(fn (TemplateVersion $v) => $v->id === $this->getOwnerRecord()->current_version_id ? 'Current' : null),
                TextColumn::make('change_note')->placeholder('—')->wrap(),
                TextColumn::make('author.name')->label('By')->placeholder('—'),
                TextColumn::make('created_at')->dateTime('d M Y, H:i')->label('Saved'),
            ])
            ->recordActions([
                Action::make('view')->icon('heroicon-o-eye')->modalSubmitAction(false)->modalWidth('5xl')
                    ->modalHeading(fn (TemplateVersion $v) => 'Version '.$v->version)
                    ->schema(fn (TemplateVersion $v) => [Html::make(new HtmlString('<div class="prose max-w-none dark:prose-invert">'.$v->body.'</div>'))]),
                Action::make('restore')->icon('heroicon-o-arrow-uturn-left')->requiresConfirmation()
                    ->visible(fn (TemplateVersion $v) => auth()->user()->isAdmin() && $v->id !== $this->getOwnerRecord()->current_version_id)
                    ->modalDescription('This copies the old text, fields and clauses into a new version. Nothing is deleted.')
                    ->action(function (TemplateVersion $v) {
                        $t = $this->getOwnerRecord();
                        $t->update(['body' => $v->body, 'fields' => $v->fields, 'clauses' => $v->clauses]);
                        $new = $t->publishVersionIfChanged('Restored from version '.$v->version);
                        ActivityLog::record('template_version', $t, 'Restored version '.$v->version);
                        Notification::make()->success()->title($new ? 'Restored as version '.$new->version : 'Already the same as the current version')->send();
                        $this->redirect(\App\Filament\Resources\Templates\TemplateResource::getUrl('edit', ['record' => $t]));
                    }),
            ]);
    }
}
