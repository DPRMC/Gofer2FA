<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Adapters;

use DPRMC\Gofer2FA\Contracts\MailboxAttachmentInterface;

class ArrayMailboxAttachment implements MailboxAttachmentInterface {
    private ?string $filename;
    private ?string $contentType;
    private ?string $content;

    /**
     * Create a mailbox attachment wrapper from a normalized attribute array.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct( array $attributes ) {
        $this->filename = isset( $attributes['filename'] ) ? trim( (string) $attributes['filename'] ) : NULL;
        $this->contentType = isset( $attributes['content_type'] ) ? strtolower( trim( (string) $attributes['content_type'] ) ) : NULL;
        $this->content = isset( $attributes['content'] ) ? (string) $attributes['content'] : NULL;
    }

    /**
     * Return the attachment filename when one was provided.
     */
    public function getFilename(): ?string {
        return $this->filename;
    }

    /**
     * Return the normalized attachment MIME type.
     */
    public function getContentType(): ?string {
        return $this->contentType;
    }

    /**
     * Return decoded attachment content when available.
     */
    public function getContent(): ?string {
        return $this->content;
    }
}
