<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

use DPRMC\Gofer2FA\Contracts\MailboxAttachmentInterface;
use DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;

abstract class AbstractChallengeSite implements ChallengeSiteInterface {
    /**
     * Parse a 2FA code by inspecting the message subject, body, and decoded attachment content.
     */
    public function parseCode( MailboxMessageInterface $message ): ?string {
        return $this->extractFromContent( $this->messageContent( $message ) );
    }

    protected function messageContent( MailboxMessageInterface $message ): string {
        $parts = [
            $message->getSubject(),
            $message->getTextBody(),
            strip_tags( (string) $message->getHtmlBody() ),
        ];

        foreach ( $message->getAttachments() as $attachment ) {
            $parts[] = $this->attachmentContent( $attachment );
        }

        return trim( implode( "\n\n", array_filter( $parts, static function ( $value ): bool {
            return is_string( $value ) && trim( $value ) !== '';
        } ) ) );
    }

    /**
     * @param string[] $patterns
     */
    protected function extractFirstMatchingCode( string $content, array $patterns ): ?string {
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content, $matches ) === 1 ) {
                return isset( $matches['code'] ) ? trim( (string) $matches['code'] ) : trim( (string) ( $matches[1] ?? '' ) );
            }
        }

        return NULL;
    }

    protected function attachmentContent( MailboxAttachmentInterface $attachment ): string {
        $content = trim( (string) $attachment->getContent() );

        if ( $content === '' ) {
            return '';
        }

        $contentType = strtolower( (string) $attachment->getContentType() );

        if ( str_contains( $contentType, 'html' ) || $this->looksLikeHtml( $content ) ) {
            $content = strip_tags( $content );
        }

        return trim( implode( "\n", array_filter( [
            $attachment->getFilename(),
            $content,
        ], static function ( $value ): bool {
            return is_string( $value ) && trim( $value ) !== '';
        } ) ) );
    }

    protected function looksLikeHtml( string $content ): bool {
        return $content !== strip_tags( $content );
    }

    abstract protected function extractFromContent( string $content ): ?string;
}
