<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Template;
use Filament\Resources\Pages\CreateRecord;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;
    protected static ?string $title = 'New contract';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $template = Template::findOrFail($data['template_id']);
        $data['template_version_id'] ??= $template->current_version_id;
        $data = ContractResource::prepareForSave($data);
        $data['agreement_no'] = Contract::nextAgreementNumber($template);
        $data['status'] = ContractStatus::Draft;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        ActivityLog::record('created', $this->record, 'Draft created');
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->record]);
    }
}
