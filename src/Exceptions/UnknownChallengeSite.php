<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when `Gofer2FA` is asked for a site key that has not been registered.
 *
 * This guards the first step of the lookup flow by failing early before any mailbox query is attempted.
 */
class UnknownChallengeSite extends InvalidArgumentException {
    /**
     * Create an exception for an unregistered site key.
     */
    public static function forKey( string $siteKey ): self {
        return new self( sprintf( 'No 2FA site parser is registered for "%s".', $siteKey ) );
    }
}
