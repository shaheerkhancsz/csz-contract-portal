<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Services\ContractWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use RuntimeException;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string
    {
        return $this->record->agreement_no;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title.' · '.$this->record->client?->company_name;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** Managers, or the staff member who drafted it, may send/cancel the signing link. */
    private function canSend(Contract $c): bool
    {
        $user = auth()->user();

        return $user->isManager() || $c->created_by === $user->id;
    }

    private function run(callable $fn, string $success): void
    {
        try {
            $fn(app(ContractWorkflow::class));
            Notification::make()->success()->title($success)->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Could not do that')->body($e->getMessage())->persistent()->send();
        }
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        /** @var Contract $c */
        $c = $this->record;
        $user = auth()->user();

        return [
            Action::make('submit')->label('Submit for approval')->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $c->status === ContractStatus::Draft)
                ->requiresConfirmation()->modalDescription('A manager will review the contract before it goes to the client. You cannot edit it while it waits, unless it is sent back.')
                ->action(fn () => $this->run(fn ($w) => $w->submit($c), 'Submitted for approval')),

            Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => $user->can('approve', $c))
                ->requiresConfirmation()->modalHeading('Approve this contract?')
                ->modalDescription('The text is frozen, the final PDF is saved with a tamper-check hash, and the status becomes Sent. Send the PDF to the client for signing.')
                ->action(fn () => $this->run(fn ($w) => $w->approve($c), 'Approved. The final PDF is saved under Documents.')),

            Action::make('return')->label('Send back')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn () => $user->can('approve', $c))
                ->schema([Textarea::make('note')->label('What needs to change?')->required()])
                ->action(fn (array $data) => $this->run(fn ($w) => $w->returnToDraft($c, $data['note']), 'Sent back to draft')),

            EditAction::make()->visible(fn () => $user->can('update', $c)),

            Action::make('pdf')->label(fn () => $c->frozen_html ? 'Download PDF' : 'Preview PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->url(fn () => route('files.contract.pdf', $c))->openUrlInNewTab(),

            Action::make('sendForSigning')->label('Send for e-signing')->icon('heroicon-o-envelope')->color('success')
                ->visible(fn () => $c->status === ContractStatus::Sent && ! $c->activeSigningRequest() && $this->canSend($c))
                ->requiresConfirmation()->modalHeading('Email the client a signing link?')
                ->modalDescription(fn () => 'We will email '.($c->client?->email ?: 'the client').' a secure link to review and sign this contract online. They confirm their identity with a one-time email code.')
                ->disabled(fn () => blank($c->client?->email))
                ->action(fn () => $this->run(fn ($w) => $w->createSigningRequest($c), 'Signing link emailed to the client')),

            ActionGroup::make([
                Action::make('copySigningLink')->label('Signing link')->icon('heroicon-o-link')
                    ->visible(fn () => (bool) $c->activeSigningRequest())
                    ->modalHeading('Signing link')
                    ->modalDescription(fn () => new \Illuminate\Support\HtmlString('Share this link with the client if the email did not arrive:<br><br><code style="word-break:break-all;">'.e($c->activeSigningRequest()?->signingUrl()).'</code>'))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close'),

                Action::make('resendSigning')->label('Resend signing link')->icon('heroicon-o-arrow-path')
                    ->visible(fn () => (bool) $c->activeSigningRequest())
                    ->requiresConfirmation()->modalDescription(fn () => 'Re-send the signing email to '.$c->activeSigningRequest()?->email.'.')
                    ->action(fn () => $this->run(fn ($w) => $w->resendSigningRequest($c->activeSigningRequest()), 'Signing email re-sent')),

                Action::make('voidSigning')->label('Cancel signing link')->icon('heroicon-o-no-symbol')->color('warning')
                    ->visible(fn () => (bool) $c->activeSigningRequest() && $this->canSend($c))
                    ->requiresConfirmation()->modalDescription('The current link stops working. You can send a new one afterwards.')
                    ->action(fn () => $this->run(fn ($w) => $w->voidSigningRequest($c->activeSigningRequest()), 'Signing link cancelled')),

                Action::make('signed')->label('Upload signed copy')->icon('heroicon-o-pencil-square')
                    ->visible(fn () => $c->status === ContractStatus::Sent)
                    ->modalDescription('Upload the copy signed by both sides (PDF or a scan). The contract becomes Signed, and Active from its start date.')
                    ->schema([
                        FileUpload::make('file')->label('Signed copy')->required()->disk('local')->directory('contracts/'.$c->id.'/signed')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(20480)
                            ->storeFileNamesIn('original_name')->visibility('private'),
                        DatePicker::make('signed_on')->label('Date signed')->default(now())->required()->native(false),
                    ])
                    ->action(fn (array $data) => $this->run(
                        fn ($w) => $w->markSigned($c, $data['file'], $data['original_name'] ?? null, $data['signed_on']),
                        'Signed copy saved'
                    )),

                Action::make('newVersion')->label('Use latest template version')->icon('heroicon-o-arrow-path')
                    ->visible(fn () => $c->status === ContractStatus::Draft && $c->template->current_version_id !== $c->template_version_id)
                    ->requiresConfirmation()
                    ->modalDescription(fn () => 'This draft uses version '.$c->templateVersion->version.' of the template; the latest is version '.$c->template->currentVersion?->version.'. Values you already entered are kept.')
                    ->action(function () use ($c) {
                        $c->update(['template_version_id' => $c->template->current_version_id]);
                        ActivityLog::record('updated', $c, 'Moved to template version '.$c->template->currentVersion->version);
                        Notification::make()->success()->title('Now using the latest template version')->send();
                    }),

                Action::make('duplicate')->label('Duplicate as new draft')->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->modalDescription('Creates a new draft with the same client, values and clauses, using the latest template version. Handy for a renewal or a similar client.')
                    ->action(function () use ($c) {
                        $t = $c->template;
                        $start = $c->end_date->copy()->addDay();
                        $new = $c->replicate(['agreement_no', 'status', 'frozen_html', 'approved_by', 'approved_at', 'submitted_at', 'signed_at', 'cancelled_at', 'review_note', 'agreement_date']);
                        $new->fill([
                            'agreement_no' => Contract::nextAgreementNumber($t),
                            'status' => ContractStatus::Draft,
                            'template_version_id' => $t->current_version_id,
                            'start_date' => $start,
                            'end_date' => Contract::computeEndDate($start, $c->term_months),
                            'created_by' => auth()->id(),
                            'parent_contract_id' => $c->id,
                        ])->save();
                        ActivityLog::record('created', $new, 'Draft created as a copy of '.$c->agreement_no);
                        $this->redirect(ContractResource::getUrl('edit', ['record' => $new]));
                    }),

                Action::make('cancel')->label('Cancel contract')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn () => $user->can('cancel', $c) && ! in_array($c->status, [ContractStatus::Cancelled, ContractStatus::Expired, ContractStatus::Renewed]))
                    ->schema([Textarea::make('reason')->required()])
                    ->action(fn (array $data) => $this->run(fn ($w) => $w->cancel($c, $data['reason']), 'Contract cancelled')),

                Action::make('delete')->label('Delete draft')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn () => $user->can('delete', $c))
                    ->requiresConfirmation()
                    ->action(function () use ($c) {
                        ActivityLog::record('deleted', $c, 'Draft deleted');
                        $c->delete();
                        $this->redirect(ContractResource::getUrl('index'));
                    }),
            ])->label('More')->button()->color('gray'),
        ];
    }
}
