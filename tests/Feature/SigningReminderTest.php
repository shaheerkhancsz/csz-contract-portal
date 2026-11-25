<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\SigningStatus;
use App\Mail\SignatureReminderMail;
use App\Models\Client;
use App\Models\Contract;
use App\Models\SigningRequest;
use App\Models\Template;
use App\Support\Settings;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SigningReminderTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
    }

    /** Create a Sent contract plus a signing request, with overridable request fields. */
    private function makeRequest(array $overrides = [], ContractStatus $contractStatus = ContractStatus::Sent): SigningRequest
    {
        $t = Template::where('code', 'WMA')->first();
        $client = Client::create(['company_name' => 'Z-Houses '.(++$this->seq), 'email' => 'client@z-houses.test']);
        $c = Contract::create([
            'agreement_no' => 'CSZ-WMA-2026-'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'title' => 'Test Agreement',
            'client_id' => $client->id,
            'template_id' => $t->id,
            'template_version_id' => $t->current_version_id,
            'status' => $contractStatus,
            'start_date' => '2026-12-01', 'end_date' => '2027-11-30',
            'fee' => 7500, 'billing_cycle' => 'monthly', 'currency' => 'PKR',
        ]);

        return $c->signingRequests()->create(array_merge([
            'token' => SigningRequest::newToken(),
            'email' => 'client@z-houses.test',
            'status' => SigningStatus::Pending,
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    public function test_reminder_sent_when_overdue(): void
    {
        Mail::fake();
        $req = $this->makeRequest();

        $this->travel(config('portal.signing.reminder_after_days') + 1)->days();
        $this->artisan('contracts:signing-reminders')->assertSuccessful();

        Mail::assertQueued(SignatureReminderMail::class, 1);
        $req->refresh();
        $this->assertSame(1, (int) $req->reminders_sent);
        $this->assertNotNull($req->last_reminded_at);
    }

    public function test_no_reminder_before_due(): void
    {
        Mail::fake();
        $this->makeRequest();

        $this->travel(1)->days();   // well under reminder_after_days
        $this->artisan('contracts:signing-reminders');

        Mail::assertNotQueued(SignatureReminderMail::class);
    }

    public function test_stops_after_max(): void
    {
        Mail::fake();
        $this->makeRequest(['reminders_sent' => config('portal.signing.reminder_max'), 'last_reminded_at' => now()->subDays(30)]);

        $this->travel(30)->days();
        $this->artisan('contracts:signing-reminders');

        Mail::assertNotQueued(SignatureReminderMail::class);
    }

    public function test_interval_is_respected_between_reminders(): void
    {
        Mail::fake();
        $req = $this->makeRequest(['reminders_sent' => 1, 'last_reminded_at' => now()]);

        // One day later: not due (interval is longer).
        $this->travel(1)->days();
        $this->artisan('contracts:signing-reminders');
        Mail::assertNotQueued(SignatureReminderMail::class);

        // Past the interval: due again.
        $this->travel(config('portal.signing.reminder_interval_days') + 1)->days();
        $this->artisan('contracts:signing-reminders');
        Mail::assertQueued(SignatureReminderMail::class, 1);
        $this->assertSame(2, (int) $req->fresh()->reminders_sent);
    }

    public function test_signed_or_closed_requests_are_skipped(): void
    {
        Mail::fake();
        $this->makeRequest(['status' => SigningStatus::Signed]);       // already signed
        $this->makeRequest(['status' => SigningStatus::Voided]);       // link cancelled
        $this->makeRequest([], ContractStatus::Signed);               // contract no longer awaiting

        $this->travel(30)->days();
        $this->artisan('contracts:signing-reminders');

        Mail::assertNotQueued(SignatureReminderMail::class);
    }

    public function test_expired_link_is_not_reminded(): void
    {
        Mail::fake();
        $this->makeRequest(['expires_at' => now()->subDay()]);

        $this->artisan('contracts:signing-reminders');
        Mail::assertNotQueued(SignatureReminderMail::class);
    }

    public function test_master_switch_off_sends_nothing(): void
    {
        Mail::fake();
        Settings::setMany(['notifications_enabled' => '0']);
        $this->makeRequest();

        $this->travel(30)->days();
        $this->artisan('contracts:signing-reminders');

        Mail::assertNotQueued(SignatureReminderMail::class);
    }

    public function test_reminder_view_renders(): void
    {
        $req = $this->makeRequest();
        $html = view('emails.signing-reminder', [
            'contract' => $req->contract,
            'url' => 'http://portal.test',
            'expiresAt' => $req->expires_at,
            'subject' => 's',
        ])->render();

        $this->assertStringContainsString($req->contract->agreement_no, $html);
        $this->assertStringContainsString('Creative Solution Zone', $html);
    }
}
