<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Adapters;

use DateTimeImmutable;
use DateTimeInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;

class ArrayMailboxMessage implements MailboxMessageInterface {
    private ?string $id;
    private ?string $fromAddress;
    private ?string $subject;
    private ?string $textBody;
    private ?string $htmlBody;
    private ?DateTimeInterface $receivedAt;

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
}
