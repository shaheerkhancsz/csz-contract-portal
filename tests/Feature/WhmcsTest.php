<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Client;
use App\Models\User;
use App\Services\Whmcs\WhmcsClient;
use App\Services\Whmcs\WhmcsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhmcsTest extends TestCase
{
    use RefreshDatabase;

    private function configureWhmcs(): void
    {
        config([
            'services.whmcs.url' => 'https://whmcs.test',
            'services.whmcs.identifier' => 'id',
            'services.whmcs.secret' => 'sec',
            'services.whmcs.admin_url' => 'https://whmcs.test/admin',
        ]);
    }

    private function fakeFound(int $id = 123): void
    {
        Http::fake(['https://whmcs.test/*' => Http::response([
            'result' => 'success',
            'userid' => $id,
            'client' => [
                'id' => $id,
                'firstname' => 'Ali',
                'lastname' => 'Raza',
                'companyname' => 'Z-Houses',
                'email' => 'client@z-houses.test',
                'address1' => 'Clifton',
                'city' => 'Karachi',
                'country' => 'PK',
                'phonenumber' => '123',
            ],
        ], 200)]);
    }

    public function test_configured_reflects_env(): void
    {
        $this->assertFalse(app(WhmcsClient::class)->configured());
        $this->configureWhmcs();
        $this->assertTrue(app(WhmcsClient::class)->configured());
    }

    public function test_find_by_email_maps_the_client(): void
    {
        $this->configureWhmcs();
        $this->fakeFound(55);

        $match = app(WhmcsClient::class)->findClientByEmail('client@z-houses.test');

        $this->assertNotNull($match);
        $this->assertSame(55, $match->id);
        $this->assertSame('Z-Houses', $match->company);
        $this->assertStringContainsString('#55', $match->label());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/includes/api.php')
            && $request['action'] === 'GetClientsDetails'
            && $request['responsetype'] === 'json');
    }

    public function test_not_found_returns_null(): void
    {
        $this->configureWhmcs();
        Http::fake(['https://whmcs.test/*' => Http::response(['result' => 'error', 'message' => 'Client Not Found'], 200)]);

        $this->assertNull(app(WhmcsClient::class)->findClientByEmail('nobody@x.test'));
    }

    public function test_api_error_throws(): void
    {
        $this->configureWhmcs();
        Http::fake(['https://whmcs.test/*' => Http::response(['result' => 'error', 'message' => 'Authentication Failed'], 200)]);

        $this->expectException(WhmcsException::class);
        app(WhmcsClient::class)->findClientByEmail('x@x.test');
    }

    public function test_transport_failure_throws(): void
    {
        $this->configureWhmcs();
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->expectException(WhmcsException::class);
        app(WhmcsClient::class)->findClientById(1);
    }

    public function test_link_action_stores_id_and_logs(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => Role::Admin]));
        $this->configureWhmcs();
        $this->fakeFound(123);
        $client = Client::create(['company_name' => 'Z-Houses', 'email' => 'client@z-houses.test']);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->callAction('linkWhmcs', ['email' => 'client@z-houses.test']);

        $this->assertSame(123, (int) $client->fresh()->whmcs_client_id);
        $this->assertDatabaseHas('activity_log', ['action' => 'whmcs_linked', 'subject_id' => $client->id]);
    }

    public function test_link_action_no_match_leaves_unlinked(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => Role::Admin]));
        $this->configureWhmcs();
        Http::fake(['https://whmcs.test/*' => Http::response(['result' => 'error', 'message' => 'Client Not Found'], 200)]);
        $client = Client::create(['company_name' => 'Z-Houses', 'email' => 'client@z-houses.test']);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->callAction('linkWhmcs', ['email' => 'client@z-houses.test']);

        $this->assertNull($client->fresh()->whmcs_client_id);
    }

    public function test_link_action_blocks_duplicate(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => Role::Admin]));
        $this->configureWhmcs();
        $this->fakeFound(123);
        Client::create(['company_name' => 'Other Co', 'whmcs_client_id' => 123]);
        $client = Client::create(['company_name' => 'Z-Houses', 'email' => 'client@z-houses.test']);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->callAction('linkWhmcs', ['email' => 'client@z-houses.test']);

        $this->assertNull($client->fresh()->whmcs_client_id);
    }

    public function test_unlink_action_clears_id(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => Role::Admin]));
        $client = Client::create(['company_name' => 'Z-Houses', 'whmcs_client_id' => 50]);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->callAction('unlinkWhmcs');

        $this->assertNull($client->fresh()->whmcs_client_id);
        $this->assertDatabaseHas('activity_log', ['action' => 'whmcs_unlinked', 'subject_id' => $client->id]);
    }

    public function test_link_action_hidden_when_not_configured(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => Role::Admin]));
        $client = Client::create(['company_name' => 'Z-Houses']);

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->assertActionHidden('linkWhmcs');
    }
}
