@php use App\Services\ContractRenderer; @endphp
<div>
    @if ($step === 'invalid')
        <div class="card">
            <h1>This signing link isn’t available</h1>
            <p class="muted">It may have expired, already been used, or been cancelled. Please contact {{ $settings['company_name'] }} at {{ $settings['support_email'] }} and we’ll send you a new one.</p>
        </div>

    @elseif ($step === 'done')
        <div class="card">
            <h1>✓ Thank you — it’s signed</h1>
            <p>Your signature has been recorded for <strong>{{ $contract?->agreement_no }}</strong>. We’ve emailed you a copy of the signed agreement together with a signature certificate.</p>
            @if ($contract)
                <p><a class="btn" href="{{ route('sign.signed', ['token' => $token]) }}" target="_blank" rel="noopener">Download the signed PDF</a></p>
            @endif
        </div>

    @else
        {{-- Contract summary (shown on every active step) --}}
        <div class="card">
            <h1>Sign your agreement</h1>
            <p class="muted">{{ $settings['company_name'] }} has prepared this agreement for your signature.</p>
            @if ($contract)
                <table class="summary">
                    <tr><td>Agreement</td><td>{{ $contract->agreement_no }}</td></tr>
                    <tr><td>Title</td><td>{{ $contract->title }}</td></tr>
                    <tr><td>Client</td><td>{{ $contract->client?->company_name }}</td></tr>
                    <tr><td>Fee</td><td>{{ ContractRenderer::money($contract->fee, $contract->currency) }} {{ $contract->billing_cycle?->per() }}</td></tr>
                </table>
            @endif

            @if ($step === 'start')
                <p>To confirm it’s you, we’ll email a one-time code to <strong>{{ $maskedEmail }}</strong>.</p>
                <button class="btn" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode">
                    <span wire:loading.remove wire:target="sendCode">Email me a code</span>
                    <span wire:loading wire:target="sendCode">Sending…</span>
                </button>
                @error('code') <p class="err">{{ $message }}</p> @enderror

            @elseif ($step === 'otp')
                @if ($info) <p class="ok">{{ $info }}</p> @endif
                <label for="code">Enter the 6-digit code</label>
                <input id="code" class="code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                       wire:model="code" wire:keydown.enter="verify">
                @error('code') <p class="err">{{ $message }}</p> @enderror
                <p style="margin-top:16px;">
                    <button class="btn" wire:click="verify" wire:loading.attr="disabled" wire:target="verify">Verify &amp; continue</button>
                    <button class="btn btn-ghost" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode">Resend code</button>
                </p>
            @endif
        </div>

        {{-- Review + sign --}}
        @if ($step === 'review')
            <div class="card">
                <h2>1. Review the document</h2>
                <iframe class="doc" src="{{ route('sign.document', ['token' => $token]) }}" title="Agreement PDF"></iframe>

                <h2>2. Sign</h2>
                <label for="signerName">Your full name</label>
                <input id="signerName" type="text" wire:model="signerName" placeholder="e.g. Ali Raza">
                @error('signerName') <p class="err">{{ $message }}</p> @enderror

                <label>Draw your signature <span class="muted">(optional)</span></label>
                <div x-data="signaturePad()" x-init="init()">
                    <canvas class="pad" x-ref="pad"></canvas>
                    <p style="margin:8px 0 0;">
                        <button type="button" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;" @click="clearPad()">Clear</button>
                    </p>
                </div>

                <label class="check" for="intent">
                    <input id="intent" type="checkbox" wire:model="intent">
                    <span>I confirm I have read and agree to this agreement, and I intend this to be my electronic signature. I understand it is legally binding.</span>
                </label>
                @error('intent') <p class="err">{{ $message }}</p> @enderror

                <p style="margin-top:18px;">
                    <button class="btn" wire:click="sign" wire:loading.attr="disabled" wire:target="sign">
                        <span wire:loading.remove wire:target="sign">Sign the agreement</span>
                        <span wire:loading wire:target="sign">Signing…</span>
                    </button>
                </p>
            </div>

            <script>
                function signaturePad() {
                    return {
                        drawing: false, ctx: null, last: null,
                        init() {
                            const c = this.$refs.pad;
                            const ratio = window.devicePixelRatio || 1;
                            c.width = c.offsetWidth * ratio;
                            c.height = c.offsetHeight * ratio;
                            this.ctx = c.getContext('2d');
                            this.ctx.scale(ratio, ratio);
                            this.ctx.lineWidth = 2; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#1A1E22';
                            const pos = (e) => {
                                const r = c.getBoundingClientRect();
                                const t = e.touches ? e.touches[0] : e;
                                return { x: t.clientX - r.left, y: t.clientY - r.top };
                            };
                            const start = (e) => { e.preventDefault(); this.drawing = true; this.last = pos(e); };
                            const move = (e) => {
                                if (!this.drawing) return; e.preventDefault();
                                const p = pos(e);
                                this.ctx.beginPath(); this.ctx.moveTo(this.last.x, this.last.y); this.ctx.lineTo(p.x, p.y); this.ctx.stroke();
                                this.last = p;
                            };
                            const end = () => { if (!this.drawing) return; this.drawing = false; this.$wire.set('signatureData', c.toDataURL('image/png'), false); };
                            c.addEventListener('mousedown', start); c.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
                            c.addEventListener('touchstart', start, { passive: false }); c.addEventListener('touchmove', move, { passive: false }); c.addEventListener('touchend', end);
                        },
                        clearPad() {
                            this.ctx.clearRect(0, 0, this.$refs.pad.width, this.$refs.pad.height);
                            this.$wire.set('signatureData', null, false);
                        },
                    };
                }
            </script>
        @endif
    @endif
</div>
