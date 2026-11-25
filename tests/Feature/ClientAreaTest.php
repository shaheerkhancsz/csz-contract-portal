<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\SigningStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\SigningRequest;
use App\Models\Template;
use App\Services\Whmcs\ClientSso;
use App\Services\Whmcs\ClientSsoException;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientAreaTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplateSeeder::class);
        config(['services.whmcs.sso_secret' => 'test-sso-secret']);
    }

    private function makeContract(Client $client, ContractStatus $status): Contract
    {
        $t = Template::where('code', 'WMA')->first();

        return Contract::create([
            'agreement_no' => 'CSZ-WMA-2026-'.str_pad((string) ++$this->seq, 4, '0', STR_PAD_LEFT),
            'title' => 'Test Agreement '.$this->seq,
            'client_id' => $client->id,
            'template_id' => $t->id,
            'template_version_id' => $t->current_version_id,
            'status' => $status,
            'start_date' => '2026-12-01',
            'end_date' => '2027-11-30',
            'fee' => 7500,
            'billing_cycle' => 'monthly',
            'currency' => 'PKR',
        ]);
    }

    private function clientSession(Client $client): array
    {
        return ['client_portal' => ['client_id' => $client->id, 'name' => $client->company_name, 'expires_at' => now()->addMinutes(30)->timestamp]];
    }

    // ---------------- ClientSso service ----------------

    public function test_token_round_trip(): void
    {
        $sso = app(ClientSso::class);
        $payload = $sso->verify($sso->makeToken(42, 'a@b.test'));

        $this->assertSame(42, $payload['cid']);
        $this->assertSame('a@b.test', $payload['email']);
    }

    public function test_tampered_token_is_rejected(): void
    {
        $sso = app(ClientSso::class);
        $token = $sso->makeToken(42, 'a@b.test');

        $this->expectException(ClientSsoException::class);
        $sso->verify(substr($token, 0, -1).($token[-1] === 'A' ? 'B' : 'A'));
    }

    public function test_expired_token_is_rejected(): void
    {
        $sso = app(ClientSso::class);
        $this->expectExceptionMessage('expired');
        $sso->verify($sso->makeToken(42, 'a@b.test', -10));
    }

    public function test_nonce_is_single_use(): void
    {
        $sso = app(ClientSso::class);
        $this->assertTrue($sso->consumeNonce('abc', 120));
        $this->assertFalse($sso->consumeNonce('abc', 120));
    }

    // ---------------- SSO controller ----------------

    public function test_valid_sso_signs_the_client_in(): void
    {
        $client = Client::create(['company_name' => 'Z-Houses', 'whmcs_client_id' => 777]);
        $token = app(ClientSso::class)->makeToken(777, 'client@z-houses.test');

        $this->get('/client/sso?token='.urlencode($token))->assertRedirect(route('client.contracts'));
        $this->get(route('client.contracts'))->assertOk()->assertSee('Z-Houses');
    }

    public function test_used_token_is_refused_on_replay(): void
    {
        $client = Client::create(['company_name' => 'Z-Houses', 'whmcs_client_id' => 777]);
        $token = app(ClientSso::class)->makeToken(777, 'client@z-houses.test');

        $this->get('/client/sso?token='.urlencode($token))->assertRedirect(route('client.contracts'));
        $this->get('/client/sso?token='.urlencode($token))->assertStatus(403);
    }

    public function test_bad_token_is_refused(): void
    {
        $this->get('/client/sso?token=not-a-real-token')->assertStatus(403);
    }

    public function test_unknown_whmcs_client_is_handled(): void
    {
        $token = app(ClientSso::class)->makeToken(999, 'nobody@x.test');
        $this->get('/client/sso?token='.urlencode($token))->assertStatus(404);
    }

    public function test_sso_disabled_when_secret_missing(): void
    {
        config(['services.whmcs.sso_secret' => null]);
        $this->get('/client/sso?token=x')->assertNotFound();
    }

    // ---------------- Client area ----------------

    public function test_area_requires_a_session(): void
    {
        $this->get(route('client.contracts'))->assertRedirect(route('client.expired'));
    }

    public function test_area_lists_only_own_visible_contracts(): void
    {
        $a = Client::create(['company_name' => 'Alpha Co', 'whmcs_client_id' => 1]);
        $b = Client::create(['company_name' => 'Beta Co', 'whmcs_client_id' => 2]);

        $mine = $this->makeContract($a, ContractStatus::Signed);
        $draft = $this->makeContract($a, ContractStatus::Draft);      // internal — hidden
        $theirs = $this->makeContract($b, ContractStatus::Signed);

        $this->withSession($this->clientSession($a))->get(route('client.contracts'))
            ->assertOk()
            ->assertSee($mine->agreement_no)
            ->assertDontSee($draft->agreement_no)
            ->assertDontSee($theirs->agreement_no);
    }

    public function test_area_shows_sign_link_for_sent_contract(): void
    {
        $a = Client::create(['company_name' => 'Alpha Co', 'whmcs_client_id' => 1]);
        $c = $this->makeContract($a, ContractStatus::Sent);
        $req = $c->signingRequests()->create([
            'token' => SigningRequest::newToken(),
            'email' => 'client@alpha.test',
            'status' => SigningStatus::Pending,
            'expires_at' => now()->addDays(14),
        ]);

        $this->withSession($this->clientSession($a))->get(route('client.contracts'))
            ->assertOk()->assertSee(route('sign', ['token' => $req->token]), false);
    }

    public function test_pdf_download_is_scoped_to_the_client(): void
    {
        $a = Client::create(['company_name' => 'Alpha Co', 'whmcs_client_id' => 1]);
        $b = Client::create(['company_name' => 'Beta Co', 'whmcs_client_id' => 2]);
        $mine = $this->makeContract($a, ContractStatus::Signed);
        $theirs = $this->makeContract($b, ContractStatus::Signed);

        Storage::disk('local')->put($path = 'contracts/'.$mine->id.'/signed.pdf', '%PDF-fake');
        $mine->documents()->create(['kind' => 'signed', 'path' => $path, 'sha256' => hash('sha256', '%PDF-fake'), 'size' => 8]);

        $session = $this->clientSession($a);
        $this->withSession($session)->get(route('client.contract.pdf', $mine))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withSession($session)->get(route('client.contract.pdf', $theirs))->assertForbidden();
    }

    public function test_logout_clears_the_session(): void
    {
        $a = Client::create(['company_name' => 'Alpha Co', 'whmcs_client_id' => 1]);

        $this->withSession($this->clientSession($a))->post(route('client.logout'))->assertRedirect(route('client.expired'));
        $this->get(route('client.contracts'))->assertRedirect(route('client.expired'));
    }
}
