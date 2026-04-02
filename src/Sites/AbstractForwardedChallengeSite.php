<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

use DateTimeInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

/**
 * Base implementation for site parsers that identify forwarded 2FA emails by recipient address.
 *
 * This is used when the original sender can no longer identify the site reliably because a mailbox rule
 * forwards the challenge to a tagged destination address. `Gofer2FA` uses this class's custom query and
 * matching behavior to find the correct forwarded message before parsing the code.
 */
abstract class AbstractForwardedChallengeSite extends AbstractChallengeSite {
    /**
     * Return the plus-address tag that identifies this forwarded challenge site.
     */
    abstract public function forwardingTag(): string;

    /**
     * Forwarded challenge sites do not rely on sender matching.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return [];
    }

    public function messageQuery( ?DateTimeInterface $since = NULL, int $limit = 25 ): MessageQuery {
        return new MessageQuery( [], $since, $limit );
    }

    public function matchesMessage( MailboxMessageInterface $message ): bool {
        $toAddress = strtolower( trim( (string) $message->getToAddress() ) );

        if ( $toAddress === '' ) {
            return FALSE;
        }

        return $this->extractForwardingTag( $toAddress ) === strtolower( trim( $this->forwardingTag() ) );
    }

    protected function extractForwardingTag( string $toAddress ): ?string {
        $parts = explode( '@', strtolower( trim( $toAddress ) ), 2 );

        if ( count( $parts ) !== 2 ) {
            return NULL;
        }

        $localPart = $parts[0];

        if ( !str_contains( $localPart, '+' ) ) {
            return NULL;
        }

        $localParts = explode( '+', $localPart, 2 );
        $tag = trim( $localParts[1] ?? '' );

        return $tag !== '' ? $tag : NULL;
    }
}
