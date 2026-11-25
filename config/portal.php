<?php

return [
    // Staff must set up an authenticator app (Google Authenticator, Microsoft Authenticator…) on first login.
    'require_2fa' => env('PORTAL_REQUIRE_2FA', true),

    // Online signing (Phase 2): email-OTP e-signature flow.
    'signing' => [
        'link_days' => (int) env('SIGN_LINK_DAYS', 14),            // how long a signing link stays valid
        'otp_length' => 6,
        'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),     // how long a code is valid
        'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),    // wrong tries before a code is locked
        'otp_resend_per_hour' => (int) env('OTP_RESEND_PER_HOUR', 5), // codes issued per link per hour
    ],
];
