<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

use DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;

abstract class AbstractChallengeSite implements ChallengeSiteInterface {
    /**
     * Parse a 2FA code by inspecting the message subject and body content.
     */
    public function parseCode( MailboxMessageInterface $message ): ?string {
        return $this->extractFromContent( $this->messageContent( $message ) );
    }

    protected function messageContent( MailboxMessageInterface $message ): string {
        return trim( implode( "\n\n", array_filter( [
            $message->getSubject(),
            $message->getTextBody(),
            strip_tags( (string) $message->getHtmlBody() ),
        ], static function ( $value ): bool {
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

    abstract protected function extractFromContent( string $content ): ?string;
}
