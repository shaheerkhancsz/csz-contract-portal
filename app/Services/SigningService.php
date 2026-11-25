<?php

namespace App\Services;

use App\Enums\SigningStatus;
use App\Mail\SigningOtpMail;
use App\Models\SigningOtp;
use App\Models\SigningRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Email one-time-code verification for the online signing flow.
 * Codes are hashed at rest, expire, lock after too many wrong tries, and are rate-limited per link.
 */
class SigningService
{
    /** Generate a code, store its hash, and email it to the signer. */
    public function issueOtp(SigningRequest $request): SigningOtp
    {
        $cfg = config('portal.signing');

        $recent = $request->otps()->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= $cfg['otp_resend_per_hour']) {
            throw new RuntimeException('Too many codes requested. Please wait a while before asking for another.');
        }

        $length = $cfg['otp_length'];
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $otp = $request->otps()->create([
            'code_hash' => Hash::make($code),
            'sent_to' => $request->email,
            'expires_at' => now()->addMinutes($cfg['otp_ttl_minutes']),
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);

        Mail::to($request->email)->send(new SigningOtpMail($code, $request, $cfg['otp_ttl_minutes']));

        return $otp;
    }

    /** Check a code; on success the request becomes Verified. Throws a friendly message otherwise. */
    public function verifyOtp(SigningRequest $request, string $code): void
    {
        $cfg = config('portal.signing');

        $otp = $request->otps()->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $otp) {
            throw new RuntimeException('That code has expired. Please request a new one.');
        }
        if ($otp->attempts >= $cfg['otp_max_attempts']) {
            throw new RuntimeException('Too many wrong attempts for this code. Please request a new one.');
        }
        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');
            throw new RuntimeException('That code is not correct. Please check it and try again.');
        }

        $otp->update(['consumed_at' => now()]);
        $request->update(['status' => SigningStatus::Verified, 'verified_at' => now()]);
    }
}
