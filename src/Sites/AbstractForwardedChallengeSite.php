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
     * Return the forwarded recipient addresses that identify this site's challenge emails.
     *
     * @return string[]
     */
    abstract public function toAddresses(): array;

    public function messageQuery( ?DateTimeInterface $since = NULL, int $limit = 25 ): MessageQuery {
        return new MessageQuery( [], $since, $limit, $this->toAddresses() );
    }

    public function matchesMessage( MailboxMessageInterface $message ): bool {
        $toAddress = strtolower( trim( (string) $message->getToAddress() ) );
        $expectedRecipients = array_map( 'strtolower', $this->toAddresses() );

        return $toAddress !== '' && in_array( $toAddress, $expectedRecipients, TRUE );
    }
}
