<?php

namespace App\Services\Whmcs;

use RuntimeException;

/** A WHMCS API call failed (transport error or an error result). Message is safe to show to staff. */
class WhmcsException extends RuntimeException
{
}
