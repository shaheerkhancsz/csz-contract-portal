<?php

return [
    // Staff must set up an authenticator app (Google Authenticator, Microsoft Authenticator…) on first login.
    'require_2fa' => env('PORTAL_REQUIRE_2FA', true),
];
