<?php

namespace App\Filament\Resources\Templates\Pages;

use App\Filament\Resources\Templates\TemplateResource;
use App\Models\ActivityLog;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplate extends CreateRecord
{
    protected static string $resource = TemplateResource::class;

    protected function afterCreate(): void
    {
        $this->record->publishVersionIfChanged($this->data['change_note'] ?? 'First version');
        ActivityLog::record('template_created', $this->record, 'Template created');
    }
}
