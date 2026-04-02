<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Exceptions;

use InvalidArgumentException;

class UnknownChallengeSite extends InvalidArgumentException {
    /**
     * Create an exception for an unregistered site key.
     */
    public static function forKey( string $siteKey ): self {
        return new self( sprintf( 'No 2FA site parser is registered for "%s".', $siteKey ) );
    }
}
