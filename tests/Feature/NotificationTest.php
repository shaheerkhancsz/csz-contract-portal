<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Template;
use App\Models\User;
use App\Notifications\ContractApproved;
use App\Notifications\ContractReturned;
use App\Notifications\ContractSubmitted;
use App\Support\Settings;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    /** Draft a contract as staff (mirrors PortalTest). */
    private function draft(): Contract
    {
        $this->actingAs($this->staff);
        $client = Client::create(['company_name' => 'Z-Houses', 'address' => 'Clifton', 'city' => 'Karachi', 'contact_name' => 'Ali']);
        $t = Template::where('code', 'WMA')->first();
        Livewire::test(CreateContract::class)
            ->fillForm(['client_id' => $client->id])
            ->fillForm(['template_id' => $t->id])
            ->fillForm(['field_values.website_url' => 'https://z-houses.com/', 'start_date' => '2026-12-01'])
            ->call('create')
            ->assertHasNoFormErrors();

        return Contract::latest('id')->first();
    }

    public function test_submit_notifies_managers_but_not_the_submitter(): void
    {
        Notification::fake();
        $c = $this->draft();
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');

        Notification::assertSentTo($this->admin, ContractSubmitted::class);
        Notification::assertSentTo($this->manager, ContractSubmitted::class);
        Notification::assertNotSentTo($this->staff, ContractSubmitted::class);
    }

    public function test_approve_notifies_the_drafter(): void
    {
        $c = $this->draft();
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');

        Notification::fake();
        $this->actingAs($this->manager);
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('approve');

        Notification::assertSentTo($this->staff, ContractApproved::class);
        Notification::assertNotSentTo($this->manager, ContractApproved::class);
    }

    public function test_return_notifies_the_drafter(): void
    {
        $c = $this->draft();
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');

        Notification::fake();
        $this->actingAs($this->manager);
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('return', ['note' => 'Fix the fee']);

        Notification::assertSentTo($this->staff, ContractReturned::class);
    }

    public function test_master_switch_off_sends_nothing(): void
    {
        Settings::setMany(['notifications_enabled' => '0']);
        Notification::fake();

        $c = $this->draft();
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');
        $this->actingAs($this->manager);
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('return', ['note' => 'nope']);

        Notification::assertNothingSent();
    }

    public function test_per_event_toggle_off_skips_manager_emails(): void
    {
        Settings::setMany(['notify_managers_on_submit' => '0']);
        Notification::fake();

        $c = $this->draft();
        Livewire::test(ViewContract::class, ['record' => $c->id])->callAction('submit');

        Notification::assertNothingSent();
    }

    public function test_email_views_render_with_branding(): void
    {
        $c = $this->draft();

        $cases = [
            ['emails.contract-submitted', ['contract' => $c, 'submittedBy' => 'Sam Staff', 'url' => 'http://portal.test', 'subject' => 's']],
            ['emails.contract-approved', ['contract' => $c, 'approvedBy' => 'Mia Manager', 'url' => 'http://portal.test', 'subject' => 's']],
            ['emails.contract-returned', ['contract' => $c, 'returnedBy' => 'Mia Manager', 'note' => 'Please fix the fee', 'url' => 'http://portal.test', 'subject' => 's']],
        ];
        foreach ($cases as [$view, $data]) {
            $html = view($view, $data)->render();
            $this->assertStringContainsString($c->agreement_no, $html);
            $this->assertStringContainsString('Creative Solution Zone', $html);
        }

        $returned = view('emails.contract-returned', ['contract' => $c, 'returnedBy' => 'M', 'note' => 'Please fix the fee', 'url' => 'http://portal.test', 'subject' => 's'])->render();
        $this->assertStringContainsString('Please fix the fee', $returned);
    }
}
