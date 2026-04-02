<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

/**
 * Contract for a mailbox reader that can search for candidate 2FA emails.
 *
 * `Gofer2FA` hands a `MessageQuery` to this client, and the client returns mailbox messages that site
 * parsers can inspect for matching metadata and extracted codes.
 */
interface MailboxClientInterface {
    /**
     * Find mailbox messages that match the supplied query constraints.
     *
     * @return iterable<\DPRMC\Gofer2FA\Contracts\MailboxMessageInterface>
     */
    public function findMessages( MessageQuery $query ): iterable;
}
