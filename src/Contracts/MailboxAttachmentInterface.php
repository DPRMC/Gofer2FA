<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

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
