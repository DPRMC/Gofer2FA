<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;

class FakeMailboxMessage implements MailboxMessageInterface {
    private ?string $id;
    private ?string $fromAddress;
    private ?string $subject;
    private ?string $textBody;
    private ?string $htmlBody;
    private ?DateTimeInterface $receivedAt;

    public function __construct(
        ?string $id = NULL,
        ?string $fromAddress = NULL,
        ?string $subject = NULL,
        ?string $textBody = NULL,
        ?string $htmlBody = NULL,
        ?DateTimeInterface $receivedAt = NULL
    ) {
        $this->id = $id;
        $this->fromAddress = $fromAddress;
        $this->subject = $subject;
        $this->textBody = $textBody;
        $this->htmlBody = $htmlBody;
        $this->receivedAt = $receivedAt ?: new DateTimeImmutable();
    }

    public function getId(): ?string {
        return $this->id;
    }

    public function getFromAddress(): ?string {
        return $this->fromAddress;
    }

    public function getSubject(): ?string {
        return $this->subject;
    }

    public function getTextBody(): ?string {
        return $this->textBody;
    }

    public function getHtmlBody(): ?string {
        return $this->htmlBody;
    }

    public function getReceivedAt(): ?DateTimeInterface {
        return $this->receivedAt;
    }
}
