<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

use DateTimeInterface;

interface MailboxMessageInterface {
    /**
     * Return the mailbox provider's identifier for this message when available.
     */
    public function getId(): ?string;

    /**
     * Return the normalized sender email address for this message.
     */
    public function getFromAddress(): ?string;

    /**
     * Return the message subject line.
     */
    public function getSubject(): ?string;

    /**
     * Return the plain text message body when available.
     */
    public function getTextBody(): ?string;

    /**
     * Return the HTML message body when available.
     */
    public function getHtmlBody(): ?string;

    /**
     * Return when the message was received by the mailbox provider.
     */
    public function getReceivedAt(): ?DateTimeInterface;

    /**
     * Return decoded text-capable attachments supplied with the message.
     *
     * @return array<int, \DPRMC\Gofer2FA\Contracts\MailboxAttachmentInterface>
     */
    public function getAttachments(): array;
}
