<?php

return [
    // Staff must set up an authenticator app (Google Authenticator, Microsoft Authenticator…) on first login.
    'require_2fa' => env('PORTAL_REQUIRE_2FA', true),

    // Client area reached by WHMCS SSO handoff (Phase 2).
    'client_session_minutes' => (int) env('CLIENT_SESSION_MINUTES', 30), // idle timeout for the client area
    'sso_token_ttl_seconds' => (int) env('CLIENT_SSO_TTL', 120),         // how long a WHMCS SSO link is valid

    // Online signing (Phase 2): email-OTP e-signature flow.
    'signing' => [
        'link_days' => (int) env('SIGN_LINK_DAYS', 14),            // how long a signing link stays valid
        'otp_length' => 6,
        'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),     // how long a code is valid
        'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),    // wrong tries before a code is locked
        'otp_resend_per_hour' => (int) env('OTP_RESEND_PER_HOUR', 5), // codes issued per link per hour
        // Reminders for contracts sent but not yet signed (daily contracts:signing-reminders command).
        'reminder_after_days' => (int) env('SIGN_REMINDER_AFTER_DAYS', 3),    // first nudge this long after sending
        'reminder_interval_days' => (int) env('SIGN_REMINDER_INTERVAL_DAYS', 3), // wait between nudges
        'reminder_max' => (int) env('SIGN_REMINDER_MAX', 3),       // stop after this many nudges
    ],
];
