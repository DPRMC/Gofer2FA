<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

interface ChallengeSiteInterface {
    /**
     * Return the unique key used to look up this site parser.
     */
    public function key(): string;

    /**
     * Return the sender email addresses that identify this site's challenge emails.
     *
     * @return string[]
     */
    public function senderAddresses(): array;

    /**
     * Attempt to extract a 2FA code from the supplied mailbox message.
     */
    public function parseCode( MailboxMessageInterface $message ): ?string;
}
