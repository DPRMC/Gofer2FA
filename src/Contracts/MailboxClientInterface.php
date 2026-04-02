<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

interface MailboxClientInterface {
    /**
     * Find mailbox messages that match the supplied query constraints.
     *
     * @return iterable<\DPRMC\Gofer2FA\Contracts\MailboxMessageInterface>
     */
    public function findMessages( MessageQuery $query ): iterable;
}
