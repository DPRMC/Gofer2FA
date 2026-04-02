<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

use DateTimeInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

/**
 * Represents a challenge site that needs custom mailbox-query and message-matching behavior.
 *
 * The base `ChallengeSiteInterface` assumes a site can be identified by its sender address alone.
 * This interface is for cases where that is not sufficient, such as forwarded 2FA emails that must
 * be identified by the recipient address or some other message-level rule.
 *
 * `Gofer2FA` checks for this interface when fetching codes. If a site implements it, the site is
 * responsible for building the `MessageQuery` used to search the mailbox and for deciding whether a
 * returned `MailboxMessageInterface` belongs to that site before parsing the code.
 */
interface MessageMatchingChallengeSiteInterface extends ChallengeSiteInterface {
    /**
     * Build the mailbox query needed to find candidate messages for this site.
     */
    public function messageQuery( ?DateTimeInterface $since = NULL, int $limit = 25 ): MessageQuery;

    /**
     * Determine whether the mailbox message belongs to this site.
     */
    public function matchesMessage( MailboxMessageInterface $message ): bool;
}
