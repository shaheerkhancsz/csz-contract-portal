<?php

namespace App\Filament\Resources\Templates\Pages;

use App\Filament\Resources\Templates\TemplateResource;
use App\Models\ActivityLog;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTemplate extends EditRecord
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $version = $this->record->publishVersionIfChanged($this->data['change_note'] ?? null);
        if ($version) {
            ActivityLog::record('template_version', $this->record, 'Saved version '.$version->version);
            Notification::make()->success()->title('Saved as version '.$version->version)
                ->body('New contracts use this version. Existing contracts keep the version they were drafted from.')->send();
        }
        $this->data['change_note'] = null;
    }
}
