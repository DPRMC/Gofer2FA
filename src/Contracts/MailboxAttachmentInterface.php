<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

/**
 * Contract for decoded mailbox attachment data.
 *
 * Site parsers consume this interface when a 2FA code is present in an attachment instead of the email body.
 * Mailbox adapters are responsible for exposing any text-capable attachment content through this contract.
 */
interface MailboxAttachmentInterface {
    /**
     * Return the attachment filename when available.
     */
    public function getFilename(): ?string;

    /**
     * Return the attachment MIME type when available.
     */
    public function getContentType(): ?string;

    /**
     * Return decoded attachment content when the mailbox client can provide it as text.
     */
    public function getContent(): ?string;
}
