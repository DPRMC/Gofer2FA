<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxAttachment;
use DPRMC\Gofer2FA\Contracts\MailboxAttachmentInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use UnexpectedValueException;

class FakeMailboxMessage implements MailboxMessageInterface {
    private ?string $id;
    private ?string $fromAddress;
    private ?string $toAddress;
    private ?string $subject;
    private ?string $textBody;
    private ?string $htmlBody;
    private ?DateTimeInterface $receivedAt;
    /**
     * @var array<int, MailboxAttachmentInterface>
     */
    private array $attachments;

    public function __construct(
        ?string $id = NULL,
        ?string $fromAddress = NULL,
        ?string $subject = NULL,
        ?string $textBody = NULL,
        ?string $htmlBody = NULL,
        ?DateTimeInterface $receivedAt = NULL,
        array $attachments = [],
        ?string $toAddress = NULL
    ) {
        $this->id = $id;
        $this->fromAddress = $fromAddress;
        $this->toAddress = $toAddress;
        $this->subject = $subject;
        $this->textBody = $textBody;
        $this->htmlBody = $htmlBody;
        $this->receivedAt = $receivedAt ?: new DateTimeImmutable();
        $this->attachments = $this->normalizeAttachments( $attachments );
    }

    public function getId(): ?string {
        return $this->id;
    }

    public function getFromAddress(): ?string {
        return $this->fromAddress;
    }

    public function getToAddress(): ?string {
        return $this->toAddress;
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

    /**
     * @return array<int, MailboxAttachmentInterface>
     */
    public function getAttachments(): array {
        return $this->attachments;
    }

    /**
     * @param array<int, mixed> $attachments
     *
     * @return array<int, MailboxAttachmentInterface>
     */
    private function normalizeAttachments( array $attachments ): array {
        $normalized = [];

        foreach ( $attachments as $attachment ) {
            if ( $attachment instanceof MailboxAttachmentInterface ) {
                $normalized[] = $attachment;
                continue;
            }

            if ( is_array( $attachment ) ) {
                $normalized[] = new ArrayMailboxAttachment( $attachment );
                continue;
            }

            if ( is_string( $attachment ) ) {
                $normalized[] = new ArrayMailboxAttachment( [ 'content' => $attachment ] );
                continue;
            }

            throw new UnexpectedValueException( 'Fake mailbox attachments must be MailboxAttachmentInterface instances, arrays, or strings.' );
        }

        return $normalized;
    }
}
