<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Preview PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->url(fn () => route('files.contract.pdf', $this->record))->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['template_id'], $data['template_version_id']); // fixed once drafted

        return ContractResource::prepareForSave($data, $this->record);
    }

    protected function afterSave(): void
    {
        ActivityLog::record('updated', $this->record, 'Draft edited');
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }
}
