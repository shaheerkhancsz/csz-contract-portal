<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Services\Whmcs\WhmcsClient;
use App\Services\Whmcs\WhmcsException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Client $client */
        $client = $this->record;
        $whmcs = app(WhmcsClient::class);
        $canManage = fn () => auth()->user()?->can('update', $client);

        return [
            Action::make('linkWhmcs')->label(fn () => $client->whmcs_client_id ? 'Re-link WHMCS' : 'Link WHMCS account')
                ->icon('heroicon-o-link')->color('gray')
                ->visible(fn () => $whmcs->configured() && $canManage())
                ->modalHeading('Link this client to WHMCS')
                ->modalDescription('We look the client up in WHMCS and store their WHMCS ID. Nothing else is changed or synced.')
                ->schema([
                    TextInput::make('email')->label('Client email in WHMCS')->email()->default($client->email)
                        ->helperText('Leave as-is to match by email, or use the WHMCS ID field below instead.'),
                    TextInput::make('whmcs_id')->label('Or WHMCS client ID')->numeric()->minValue(1),
                ])
                ->action(fn (array $data) => $this->linkWhmcs($client, $whmcs, $data)),

            Action::make('unlinkWhmcs')->label('Unlink WHMCS')->icon('heroicon-o-link-slash')->color('warning')
                ->visible(fn () => $client->whmcs_client_id && $canManage())
                ->requiresConfirmation()->modalDescription('Remove the stored WHMCS ID. The WHMCS account itself is not affected.')
                ->action(function () use ($client) {
                    $old = $client->whmcs_client_id;
                    $client->update(['whmcs_client_id' => null]);
                    ActivityLog::record('whmcs_unlinked', $client, 'Unlinked WHMCS client #'.$old);
                    Notification::make()->success()->title('WHMCS account unlinked')->send();
                }),

            EditAction::make(),
        ];
    }

    private function linkWhmcs(Client $client, WhmcsClient $whmcs, array $data): void
    {
        $id = (int) ($data['whmcs_id'] ?? 0);
        $email = trim((string) ($data['email'] ?? ''));

        if ($id <= 0 && $email === '') {
            Notification::make()->warning()->title('Enter an email or a WHMCS client ID')->send();

            return;
        }

        try {
            $match = $id > 0 ? $whmcs->findClientById($id) : $whmcs->findClientByEmail($email);
        } catch (WhmcsException $e) {
            Notification::make()->danger()->title('Could not reach WHMCS')->body($e->getMessage())->persistent()->send();

            return;
        }

        if (! $match) {
            Notification::make()->warning()->title('No matching WHMCS client found')
                ->body('Check the email or ID in WHMCS and try again.')->send();

            return;
        }

        $clash = Client::where('whmcs_client_id', $match->id)->whereKeyNot($client->getKey())->first();
        if ($clash) {
            Notification::make()->danger()->title('That WHMCS client is already linked')
                ->body('WHMCS #'.$match->id.' is linked to '.$clash->company_name.'. Unlink it there first.')->persistent()->send();

            return;
        }

        $client->update(['whmcs_client_id' => $match->id]);
        ActivityLog::record('whmcs_linked', $client, 'Linked to WHMCS '.$match->label());
        Notification::make()->success()->title('Linked to WHMCS')->body($match->label())->send();
    }
}
