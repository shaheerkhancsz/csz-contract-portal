<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Templates\Pages\EditTemplate;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Template;
use App\Models\User;
use App\Services\ContractRenderer;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => Role::Admin]);
        $this->manager = User::create(['name' => 'Manager', 'email' => 'm@x.test', 'password' => 'secret-password', 'role' => Role::Manager]);
        $this->staff = User::create(['name' => 'Staff', 'email' => 's@x.test', 'password' => 'secret-password', 'role' => Role::Staff]);
    }

    private function client(): Client
    {
        return Client::create(['company_name' => 'Z-Houses', 'address' => 'Clifton', 'city' => 'Karachi', 'contact_name' => 'Ali']);
    }

    private function draft(): Contract
    {
        $this->actingAs($this->staff);
        $client = $this->client();
        $t = Template::where('code', 'WMA')->first();
        Livewire::test(CreateContract::class)
            ->fillForm(['client_id' => $client->id])
            ->fillForm(['template_id' => $t->id])
            ->fillForm(['field_values.website_url' => 'https://z-houses.com/', 'start_date' => '2026-12-01'])
            ->call('create')
            ->assertHasNoFormErrors();

        return Contract::latest('id')->first();
    }

    public function test_pages_load_for_admin(): void
    {
        $this->actingAs($this->admin);
        $c = $this->draft();
        $this->actingAs($this->admin);
        foreach (['/', '/contracts', '/contracts/create', '/contracts/'.$c->id, '/contracts/'.$c->id.'/edit', '/clients', '/clients/create', '/clients/'.$c->client_id,
            '/templates', '/templates/create', '/templates/1/edit', '/settings', '/users', '/activity-logs', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_staff_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->staff);
        $this->get('/settings')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/templates/1/edit')->assertForbidden();
        $this->get('/contracts')->assertOk();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/contracts')->assertRedirect('/login');
        $this->get('/files/letterhead')->assertRedirect('/login');
    }

    public function test_create_contract_from_template(): void
    {
        $c = $this->draft();
        $this->assertSame('CSZ-WMA-'.now()->year.'-0001', $c->agreement_no);
        $this->assertSame(ContractStatus::Draft, $c->status);
        $this->assertSame('2027-11-30', $c->end_date->toDateString());
        $this->assertEquals(7500, (float) $c->fee);
        $this->assertSame('Weekly', $c->field_values['backup_frequency']);
        $this->assertTrue($c->clause_flags['security']);
        $this->assertSame([], app(\App\Services\ContractWorkflow::class)->missingValues($c));
        $html = app(ContractRenderer::class)->render($c);
        $this->assertStringContainsString('https://z-houses.com/', $html);
        $this->assertStringContainsString('PKR 8,250', $html);
        $this->assertStringNotContainsString('[[', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_staff_fee_limits_and_manager_override(): void
    {
        $t = Template::where('code', 'WMA')->first();
        $t->update(['min_fee' => 5000]);
        $client = $this->client();
        $this->actingAs($this->staff);
        Livewire::test(CreateContract::class)
            ->fillForm(['client_id' => $client->id])->fillForm(['template_id' => $t->id])
            ->fillForm(['field_values.website_url' => 'https://x.test', 'fee' => 1000])
            ->call('create')->assertHasFormErrors(['fee']);
        $this->actingAs($this->manager);
        Livewire::test(CreateContract::class)
            ->fillForm(['client_id' => $client->id])->fillForm(['template_id' => $t->id])
            ->fillForm(['field_values.website_url' => 'https://x.test', 'fee' => 1000])
            ->call('create')->assertHasNoFormErrors();
    }

    public function test_full_workflow_and_pdf(): void
    {
        $c = $this->draft();

        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');
        $this->assertSame(ContractStatus::AwaitingApproval, $c->fresh()->status);

        // staff cannot approve or edit while waiting
        Livewire::test(ViewContract::class, ['record' => $c->id])->assertActionHidden('approve');
        $this->get('/contracts/'.$c->id.'/edit')->assertForbidden();

        $this->actingAs($this->manager);
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('return', ['note' => 'Fix the fee']);
        $this->assertSame(ContractStatus::Draft, $c->fresh()->status);
        $this->assertSame('Fix the fee', $c->fresh()->review_note);

        $this->actingAs($this->staff);
        Livewire::test(EditContract::class, ['record' => $c->id])->fillForm(['fee' => 8000])->call('save')->assertHasNoFormErrors();
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');

        $this->actingAs($this->manager);
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('approve');
        $c->refresh();
        $this->assertSame(ContractStatus::Sent, $c->status);
        $this->assertNotNull($c->frozen_html);
        $this->assertStringContainsString('PKR 8,000', $c->frozen_html);
        $doc = $c->documents()->first();
        $this->assertSame('sent', $doc->kind);

        $res = $this->get('/files/contracts/'.$c->id.'/pdf');
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($doc->sha256, hash('sha256', $res->getContent()));
        $this->get('/files/documents/'.$doc->id)->assertOk();

        // Template changes never touch the approved text
        $t = $c->template;
        $t->update(['body' => '<p>Changed</p>']);
        $t->publishVersionIfChanged();
        $this->assertStringContainsString('PKR 8,000', app(ContractRenderer::class)->render($c->fresh()));

        $this->assertGreaterThanOrEqual(5, $c->activities()->count());
    }

    public function test_status_refresh(): void
    {
        $c = $this->draft();
        $c->update(['status' => ContractStatus::Signed, 'start_date' => now()->subMonths(11), 'end_date' => now()->addDays(10)]);
        $this->artisan('contracts:refresh-statuses')->assertSuccessful();
        $this->assertSame(ContractStatus::Expiring, $c->fresh()->status);
        $c->update(['end_date' => now()->subDay()]);
        $this->artisan('contracts:refresh-statuses');
        $this->assertSame(ContractStatus::Expired, $c->fresh()->status);
    }

    public function test_template_edit_creates_new_version(): void
    {
        $this->actingAs($this->admin);
        $t = Template::where('code', 'HOST')->first();
        Livewire::test(EditTemplate::class, ['record' => $t->id])
            ->fillForm(['body' => $t->body.'<p>Extra clause.</p>', 'change_note' => 'Added clause'])
            ->call('save')->assertHasNoFormErrors();
        $t->refresh();
        $this->assertSame(2, $t->currentVersion->version);
        $this->assertSame('Added clause', $t->currentVersion->change_note);
        // saving without changes does not add a version
        Livewire::test(EditTemplate::class, ['record' => $t->id])->call('save');
        $this->assertSame(2, $t->fresh()->versions()->count());
    }

    public function test_create_client(): void
    {
        $this->actingAs($this->staff);
        Livewire::test(CreateClient::class)->fillForm(['company_name' => 'Acme (Pvt) Ltd', 'email' => 'a@acme.pk'])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('clients', ['company_name' => 'Acme (Pvt) Ltd', 'created_by' => $this->staff->id]);
    }

    public function test_renderer_clauses(): void
    {
        $r = new ContractRenderer;
        $body = '<p>[[if a]]</p><p>A on</p><p>[[else]]</p><p>A off</p><p>[[endif]]</p><ul><li>x[[if b]] and b[[endif]]</li><li>[[if c]]C[[endif]]</li></ul><p>{{v}}</p><p>{{missing_one}}</p>';
        $out = $r->renderBody($body, ['v' => '<table><tr><td>t</td></tr></table>'], ['a' => false, 'b' => true, 'c' => false]);
        $this->assertStringContainsString('A off', $out);
        $this->assertStringNotContainsString('A on', $out);
        $this->assertStringContainsString('<li>x and b</li>', $out);
        $this->assertStringNotContainsString('<li></li>', $out);
        $this->assertStringContainsString('<table>', $out);
        $this->assertStringNotContainsString('<p><table>', $out);
        $this->assertSame(['missing_one' => 'missing_one'], $r->missing);
        $merge = $r->renderBody('<p>Fee <span data-type="mergeTag" data-id="v">{{ v }}</span></p>', ['v' => 'PKR 1'], []);
        $this->assertSame('<p>Fee PKR 1</p>', $merge);
    }

    public function test_two_factor_required_when_enabled(): void
    {
        putenv('PORTAL_REQUIRE_2FA=true');
        $_ENV['PORTAL_REQUIRE_2FA'] = $_SERVER['PORTAL_REQUIRE_2FA'] = 'true';
        $this->refreshApplication();
        $this->artisan('migrate');
        $u = User::create(['name' => 'X', 'email' => 'x@x.test', 'password' => 'secret-password', 'role' => Role::Staff]);
        $res = $this->actingAs($u)->get('/contracts');
        $res->assertRedirect();
        $this->assertStringContainsString('multi-factor', $res->headers->get('Location'));
        putenv('PORTAL_REQUIRE_2FA=false');
        $_ENV['PORTAL_REQUIRE_2FA'] = $_SERVER['PORTAL_REQUIRE_2FA'] = 'false';
    }
}
