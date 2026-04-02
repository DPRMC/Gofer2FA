<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Adapters;

use DateTimeImmutable;
use DateTimeInterface;
use DPRMC\Gofer2FA\Contracts\MailboxAttachmentInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use UnexpectedValueException;

class ArrayMailboxMessage implements MailboxMessageInterface {
    private ?string $id;
    private ?string $fromAddress;
    private ?string $subject;
    private ?string $textBody;
    private ?string $htmlBody;
    private ?DateTimeInterface $receivedAt;
    /**
     * @var array<int, MailboxAttachmentInterface>
     */
    private array $attachments;

    /**
     * Create a mailbox message wrapper from a normalized attribute array.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct( array $attributes ) {
        $this->id = isset( $attributes['id'] ) ? (string) $attributes['id'] : NULL;
        $this->fromAddress = isset( $attributes['from_address'] ) ? strtolower( trim( (string) $attributes['from_address'] ) ) : NULL;
        $this->subject = isset( $attributes['subject'] ) ? (string) $attributes['subject'] : NULL;
        $this->textBody = isset( $attributes['text_body'] ) ? (string) $attributes['text_body'] : NULL;
        $this->htmlBody = isset( $attributes['html_body'] ) ? (string) $attributes['html_body'] : NULL;
        $this->receivedAt = $this->normalizeDate( $attributes['received_at'] ?? NULL );
        $this->attachments = $this->normalizeAttachments( $attributes['attachments'] ?? [] );
    }

    /**
     * Return the message identifier when one was provided.
     */
    public function getId(): ?string {
        return $this->id;
    }

    /**
     * Return the normalized sender address.
     */
    public function getFromAddress(): ?string {
        return $this->fromAddress;
    }

    /**
     * Return the message subject.
     */
    public function getSubject(): ?string {
        return $this->subject;
    }

    /**
     * Return the plain text body.
     */
    public function getTextBody(): ?string {
        return $this->textBody;
    }

    /**
     * Return the HTML body.
     */
    public function getHtmlBody(): ?string {
        return $this->htmlBody;
    }

    /**
     * Return the received timestamp when one was provided.
     */
    public function getReceivedAt(): ?DateTimeInterface {
        return $this->receivedAt;
    }

    /**
     * Return normalized attachment wrappers for the message.
     *
     * @return array<int, MailboxAttachmentInterface>
     */
    public function getAttachments(): array {
        return $this->attachments;
    }

    /**
     * @param mixed $value
     */
    private function normalizeDate( $value ): ?DateTimeInterface {
        if ( $value instanceof DateTimeInterface ) {
            return $value;
        }

        if ( is_string( $value ) && trim( $value ) !== '' ) {
            return new DateTimeImmutable( $value );
        }

        return NULL;
    }

    /**
     * @param mixed $attachments
     *
     * @return array<int, MailboxAttachmentInterface>
     */
    private function normalizeAttachments( $attachments ): array {
        if ( !is_iterable( $attachments ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $attachments as $attachment ) {
            $normalized[] = $this->normalizeAttachment( $attachment );
        }

        return $normalized;
    }

    /**
     * @param mixed $attachment
     */
    private function normalizeAttachment( $attachment ): MailboxAttachmentInterface {
        if ( $attachment instanceof MailboxAttachmentInterface ) {
            return $attachment;
        }

        if ( is_array( $attachment ) ) {
            return new ArrayMailboxAttachment( $attachment );
        }

        if ( is_string( $attachment ) ) {
            return new ArrayMailboxAttachment( [ 'content' => $attachment ] );
        }

        throw new UnexpectedValueException( 'Mailbox message attachments must be MailboxAttachmentInterface instances, arrays, or strings.' );
    }
}
