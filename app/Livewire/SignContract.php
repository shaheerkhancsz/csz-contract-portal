<?php

namespace App\Livewire;

use App\Models\SigningRequest;
use App\Services\ContractWorkflow;
use App\Services\SigningService;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Public, login-free signing flow driven by an emailed token:
 * start → email one-time code → review the document → type name (+ optional drawn signature) → done.
 */
#[Layout('components.public-layout')]
class SignContract extends Component
{
    public string $token = '';
    public string $step = 'start';   // start | otp | review | done | invalid

    public string $code = '';
    public string $signerName = '';
    public bool $intent = false;
    public ?string $signatureData = null; // data URL from the signature pad

    public string $maskedEmail = '';
    public ?string $info = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $request = $this->request();
        abort_if($request === null, 404);

        $this->maskedEmail = $this->mask($request->email);
        $this->signerName = (string) $request->contract->client?->contact_name;

        $this->step = match (true) {
            $request->status->value === 'signed' => 'done',
            ! $request->isUsable() => 'invalid',
            $request->isVerified() => 'review',
            default => 'start',
        };
    }

    /** Current signing request for the token (null if unknown). */
    protected function request(): ?SigningRequest
    {
        return SigningRequest::with('contract.client')->where('token', $this->token)->first();
    }

    public function sendCode(SigningService $signing): void
    {
        $this->resetErrorBag();
        $request = $this->request();
        if (! $request || ! $request->isUsable()) {
            $this->step = 'invalid';

            return;
        }
        try {
            $signing->issueOtp($request);
            $this->step = 'otp';
            $this->info = 'We emailed a verification code to '.$this->maskedEmail.'.';
        } catch (RuntimeException $e) {
            $this->addError('code', $e->getMessage());
        }
    }

    public function verify(SigningService $signing): void
    {
        $this->resetErrorBag();
        $request = $this->request();
        if (! $request || ! $request->isUsable()) {
            $this->step = 'invalid';

            return;
        }
        try {
            $signing->verifyOtp($request, $this->code);
            $this->code = '';
            $this->info = null;
            $this->step = 'review';
        } catch (RuntimeException $e) {
            $this->addError('code', $e->getMessage());
        }
    }

    public function sign(ContractWorkflow $workflow): void
    {
        $this->resetErrorBag();
        $request = $this->request();
        if (! $request) {
            $this->step = 'invalid';

            return;
        }
        if (blank($this->signerName)) {
            $this->addError('signerName', 'Please type your full name.');

            return;
        }
        if (! $this->intent) {
            $this->addError('intent', 'Please tick the box to confirm you intend to sign.');

            return;
        }

        try {
            $workflow->signOnline($request, [
                'signer_name' => $this->signerName,
                'intent' => $this->intent,
                'signature_image_path' => $this->storeSignatureImage($request),
            ]);
            $this->step = 'done';
        } catch (RuntimeException $e) {
            $this->addError('intent', $e->getMessage());
        }
    }

    /** Persist the optional drawn signature (PNG data URL) to the private disk. */
    protected function storeSignatureImage(SigningRequest $request): ?string
    {
        $prefix = 'data:image/png;base64,';
        if (! $this->signatureData || ! str_starts_with($this->signatureData, $prefix)) {
            return null;
        }
        $png = base64_decode(substr($this->signatureData, strlen($prefix)), true);
        if ($png === false || strlen($png) > 500_000) {
            return null;
        }
        $path = 'contracts/'.$request->contract_id.'/signatures/'.$request->id.'-'.Str::random(8).'.png';
        Storage::disk('local')->put($path, $png);

        return $path;
    }

    private function mask(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'your email';
        }
        [$name, $domain] = explode('@', $email, 2);
        $shown = mb_substr($name, 0, 1).str_repeat('*', max(1, mb_strlen($name) - 1));

        return $shown.'@'.$domain;
    }

    public function render()
    {
        $request = $this->request();

        return view('livewire.sign-contract', [
            'contract' => $request?->contract,
            'settings' => Settings::all(),
        ])->title('Sign '.($request?->contract?->agreement_no ?? 'document'));
    }
}
