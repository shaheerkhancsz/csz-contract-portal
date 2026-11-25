<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Enums\SigningStatus;
use App\Mail\SignatureReminderMail;
use App\Models\ActivityLog;
use App\Models\SigningRequest;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a reminder to clients who were sent a contract but haven't signed yet.
 * Runs daily; nudges after a delay, then at an interval, up to a capped number of times.
 */
class SendSigningReminders extends Command
{
    protected $signature = 'contracts:signing-reminders';

    protected $description = 'Remind clients about contracts that were sent for signing but not yet signed';

    public function handle(): int
    {
        if (! Settings::enabled('notifications_enabled')) {
            $this->info('Portal emails are turned off in Settings; no reminders sent.');

            return self::SUCCESS;
        }

        $cfg = config('portal.signing');
        $requests = SigningRequest::with('contract.client')
            ->whereIn('status', [SigningStatus::Pending->value, SigningStatus::Verified->value])
            ->where('reminders_sent', '<', $cfg['reminder_max'])
            ->where('expires_at', '>', now())
            ->whereNotNull('email')
            ->get();

        $sent = 0;
        foreach ($requests as $request) {
            if (($request->contract?->status ?? null) !== ContractStatus::Sent) {
                continue;   // only chase contracts still awaiting signature
            }
            $since = $request->last_reminded_at ?? $request->created_at;
            $waitDays = $request->last_reminded_at ? $cfg['reminder_interval_days'] : $cfg['reminder_after_days'];
            if ($since->copy()->addDays($waitDays)->isFuture()) {
                continue;   // not due yet
            }

            Mail::to($request->email)->send(new SignatureReminderMail($request));
            $request->update([
                'reminders_sent' => $request->reminders_sent + 1,
                'last_reminded_at' => now(),
            ]);
            ActivityLog::record('sign_reminder', $request->contract, 'Signing reminder sent to '.$request->email);
            $sent++;
        }

        $this->info("Sent {$sent} signing reminder(s).");

        return self::SUCCESS;
    }
}
