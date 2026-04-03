<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

/**
 * Contract for mailbox clients that can delete a message after it has been processed.
 *
 * `Gofer2FA` uses this capability when callers opt into deleting the matched challenge email after a
 * code is read. The message identifier is the same value exposed by `MailboxMessageInterface::getId()`.
 */
interface DeletableMailboxClientInterface extends MailboxClientInterface {
    /**
     * Delete a mailbox message by its normalized message identifier.
     */
    public function deleteMessage( string $messageId ): void;
}
