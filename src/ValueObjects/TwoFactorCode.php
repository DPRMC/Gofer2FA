<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\ValueObjects;

use DateTimeInterface;

/**
 * Value object representing a successfully parsed 2FA code and its source message metadata.
 *
 * This is the final output of the lookup flow. `Gofer2FA` returns it once a site parser extracts a code
 * from a matching mailbox message, allowing callers to inspect both the code and where it came from.
 */
class TwoFactorCode {
    private string $siteKey;
    private string $code;
    private ?string $messageId;
    private ?string $fromAddress;
    private ?string $subject;
    private ?DateTimeInterface $receivedAt;

    /**
     * Create a resolved 2FA code value object.
     */
    public function __construct(
        string $siteKey,
        string $code,
        ?string $messageId = NULL,
        ?string $fromAddress = NULL,
        ?string $subject = NULL,
        ?DateTimeInterface $receivedAt = NULL
    ) {
        $this->siteKey = $siteKey;
        $this->code = $code;
        $this->messageId = $messageId;
        $this->fromAddress = $fromAddress;
        $this->subject = $subject;
        $this->receivedAt = $receivedAt;
    }

    /**
     * Return the site key that produced this code.
     */
    public function siteKey(): string {
        return $this->siteKey;
    }

    /**
     * Return the parsed 2FA code.
     */
    public function code(): string {
        return $this->code;
    }

    /**
     * Return the source message identifier when available.
     */
    public function messageId(): ?string {
        return $this->messageId;
    }

    /**
     * Return the sender address of the message that produced the code.
     */
    public function fromAddress(): ?string {
        return $this->fromAddress;
    }

    /**
     * Return the source message subject when available.
     */
    public function subject(): ?string {
        return $this->subject;
    }

    /**
     * Return when the source message was received.
     */
    public function receivedAt(): ?DateTimeInterface {
        return $this->receivedAt;
    }

    /**
     * Convert the value object into a scalar array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'site_key' => $this->siteKey,
            'code' => $this->code,
            'message_id' => $this->messageId,
            'from_address' => $this->fromAddress,
            'subject' => $this->subject,
            'received_at' => $this->receivedAt ? $this->receivedAt->format( DATE_ATOM ) : NULL,
        ];
    }
}
