<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Enums\SigningStatus;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Livewire\SignContract;
use App\Mail\ContractSignedMail;
use App\Mail\SignatureRequestMail;
use App\Mail\SigningOtpMail;
use App\Models\Client;
use App\Models\Contract;
use App\Models\SigningRequest;
use App\Models\Template;
use App\Models\User;
use App\Notifications\ContractSigned;
use App\Services\ContractWorkflow;
use App\Services\SigningService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SigningTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        $this->staff = User::create(['name' => 'Staff', 'email' => 's@x.test', 'password' => 'secret-password', 'role' => Role::Staff]);
        $this->manager = User::create(['name' => 'Manager', 'email' => 'm@x.test', 'password' => 'secret-password', 'role' => Role::Manager]);
    }

    /** A contract approved and in the Sent state, with a client that has an email. */
    private function sentContract(): Contract
    {
        $this->actingAs($this->staff);
        $client = Client::create(['company_name' => 'Z-Houses', 'email' => 'client@z-houses.test', 'contact_name' => 'Ali', 'address' => 'Clifton', 'city' => 'Karachi']);
        $t = Template::where('code', 'WMA')->first();
        Livewire::test(CreateContract::class)
            ->fillForm(['client_id' => $client->id])
            ->fillForm(['template_id' => $t->id])
            ->fillForm(['field_values.website_url' => 'https://z-houses.com/', 'start_date' => '2026-12-01'])
            ->call('create')->assertHasNoFormErrors();
        $c = Contract::latest('id')->first();

        $wf = app(ContractWorkflow::class);
        $wf->submit($c);
        $this->actingAs($this->manager);
        $wf->approve($c->fresh());

        return $c->fresh();
    }

    private function request(Contract $c): SigningRequest
    {
        $this->actingAs($this->manager);

        return app(ContractWorkflow::class)->createSigningRequest($c);
    }

    /** Issue an OTP and return the plaintext code captured from the email. */
    private function issueAndCaptureCode(SigningRequest $req): string
    {
        $code = null;
        app(SigningService::class)->issueOtp($req);
        Mail::assertSent(SigningOtpMail::class, function (SigningOtpMail $m) use (&$code, $req) {
            if ($m->request->is($req)) {
                $code = $m->code;
            }

            return true;
        });

        return $code;
    }

    public function test_send_for_signing_pins_document_and_emails_the_client(): void
    {
        Mail::fake();
        Notification::fake();
        $c = $this->sentContract();

        $req = $this->request($c);

        $sent = $c->documents()->where('kind', 'sent')->first();
        $this->assertSame(SigningStatus::Pending, $req->status);
        $this->assertSame(64, strlen($req->token));
        $this->assertSame($sent->id, $req->sent_document_id);
        $this->assertSame($sent->sha256, $req->sent_sha256);
        $this->assertTrue($req->expires_at->isFuture());
        Mail::assertQueued(SignatureRequestMail::class);
    }

    public function test_send_for_signing_blocked_without_client_email(): void
    {
        Mail::fake();
        Notification::fake();
        $c = $this->sentContract();
        $c->client->update(['email' => null]);

        $this->actingAs($this->manager);
        $this->expectException(RuntimeException::class);
        app(ContractWorkflow::class)->createSigningRequest($c->fresh());
    }

    public function test_otp_verify_marks_request_verified(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());

        $code = $this->issueAndCaptureCode($req);
        app(SigningService::class)->verifyOtp($req, $code);

        $req->refresh();
        $this->assertSame(SigningStatus::Verified, $req->status);
        $this->assertNotNull($req->verified_at);
    }

    public function test_wrong_code_is_rejected_and_counts_attempts(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());
        $this->issueAndCaptureCode($req);

        try {
            app(SigningService::class)->verifyOtp($req, '000000');
            $this->fail('Expected rejection');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not correct', $e->getMessage());
        }
        $this->assertSame(1, $req->otps()->latest('id')->first()->attempts);
    }

    public function test_otp_locks_after_max_attempts(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());
        $code = $this->issueAndCaptureCode($req);

        $max = config('portal.signing.otp_max_attempts');
        for ($i = 0; $i < $max; $i++) {
            try {
                app(SigningService::class)->verifyOtp($req, '000000');
            } catch (RuntimeException) {
            }
        }
        // Even the correct code is now refused until a new one is issued.
        $this->expectExceptionMessage('Too many wrong attempts');
        app(SigningService::class)->verifyOtp($req, $code);
    }

    public function test_otp_expires(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());
        $code = $this->issueAndCaptureCode($req);

        $this->travel(config('portal.signing.otp_ttl_minutes') + 1)->minutes();
        $this->expectExceptionMessage('expired');
        app(SigningService::class)->verifyOtp($req, $code);
    }

    public function test_resend_is_rate_limited(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());

        $max = config('portal.signing.otp_resend_per_hour');
        for ($i = 0; $i < $max; $i++) {
            app(SigningService::class)->issueOtp($req);
        }
        $this->expectExceptionMessage('Too many codes');
        app(SigningService::class)->issueOtp($req);
    }

    public function test_signing_requires_a_verified_code(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());

        $this->expectExceptionMessage('confirm your email');
        app(ContractWorkflow::class)->signOnline($req, ['signer_name' => 'Ali', 'intent' => true]);
    }

    public function test_tamper_check_refuses_when_sent_pdf_changed(): void
    {
        Mail::fake();
        Notification::fake();
        $c = $this->sentContract();
        $req = $this->request($c);
        $code = $this->issueAndCaptureCode($req);
        app(SigningService::class)->verifyOtp($req, $code);

        // Alter the stored approved PDF so its hash no longer matches the pin.
        Storage::disk('local')->put($req->fresh()->sentDocument->path, 'not the original pdf');

        $this->expectExceptionMessage('document has changed');
        app(ContractWorkflow::class)->signOnline($req->fresh(), ['signer_name' => 'Ali', 'intent' => true]);
    }

    public function test_full_online_sign_produces_signed_pdf_and_notifies(): void
    {
        Mail::fake();
        Notification::fake();
        $c = $this->sentContract();
        $req = $this->request($c);
        $code = $this->issueAndCaptureCode($req);
        app(SigningService::class)->verifyOtp($req, $code);

        $png = 'data:image/png;base64,'.base64_encode('fake-png-bytes');
        // store a drawn signature too
        Storage::disk('local')->put($p = 'contracts/'.$c->id.'/signatures/test.png', 'fake-png');
        app(ContractWorkflow::class)->signOnline($req->fresh(), [
            'signer_name' => 'Ali Raza',
            'intent' => true,
            'signature_image_path' => $p,
        ]);

        $c->refresh();
        $req->refresh();
        $this->assertSame(ContractStatus::Signed, $c->status);
        $this->assertSame(SigningStatus::Signed, $req->status);
        $this->assertSame('Ali Raza', $req->signer_name);
        $this->assertNotNull($req->signed_at);
        $this->assertNotEmpty($req->ip);

        $doc = $c->documents()->where('kind', 'signed')->first();
        $this->assertNotNull($doc);
        $bytes = Storage::disk('local')->get($doc->path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame($doc->sha256, hash('sha256', $bytes));

        Mail::assertQueued(ContractSignedMail::class);
        Notification::assertSentTo($this->staff, ContractSigned::class);   // drafter
        Notification::assertSentTo($this->manager, ContractSigned::class); // manager
    }

    public function test_public_pages_and_streams_respect_state(): void
    {
        Mail::fake();
        Notification::fake();
        $c = $this->sentContract();
        $req = $this->request($c);
        $token = $req->token;

        // Landing page renders; unknown token 404s.
        $this->get('/sign/'.$token)->assertOk();
        $this->get('/sign/does-not-exist')->assertNotFound();

        // Document stream blocked until the email is verified.
        $this->get(route('sign.document', ['token' => $token]))->assertForbidden();
        $this->get(route('sign.signed', ['token' => $token]))->assertForbidden();

        $code = $this->issueAndCaptureCode($req);
        app(SigningService::class)->verifyOtp($req, $code);

        $this->get(route('sign.document', ['token' => $token]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Signed stream only after signing.
        app(ContractWorkflow::class)->signOnline($req->fresh(), ['signer_name' => 'Ali', 'intent' => true]);
        $this->get(route('sign.signed', ['token' => $token]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_livewire_flow_end_to_end(): void
    {
        Mail::fake();
        Notification::fake();
        $c = $this->sentContract();
        $req = $this->request($c);

        $component = Livewire::test(SignContract::class, ['token' => $req->token])
            ->assertSet('step', 'start')
            ->call('sendCode')
            ->assertSet('step', 'otp');

        $code = null;
        Mail::assertSent(SigningOtpMail::class, function (SigningOtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $component->set('code', $code)->call('verify')->assertSet('step', 'review')
            ->set('signerName', 'Ali Raza')->set('intent', true)
            ->call('sign')->assertSet('step', 'done');

        $this->assertSame(ContractStatus::Signed, $c->fresh()->status);
    }

    public function test_expired_link_shows_invalid(): void
    {
        Mail::fake();
        Notification::fake();
        $req = $this->request($this->sentContract());
        $req->update(['expires_at' => now()->subDay()]);

        Livewire::test(SignContract::class, ['token' => $req->token])->assertSet('step', 'invalid');
    }
}
