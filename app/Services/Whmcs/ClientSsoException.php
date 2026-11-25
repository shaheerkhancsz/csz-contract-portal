<?php

namespace App\Services\Whmcs;

use RuntimeException;

/** A WHMCS SSO token failed verification. Message is safe to show to the client. */
class ClientSsoException extends RuntimeException
{
}
