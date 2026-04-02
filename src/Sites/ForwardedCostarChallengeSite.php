<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

/**
 * Site parser for CoStar access codes that have been forwarded to mailbox aliases.
 *
 * This parser uses the plus-address tag in the `To` field instead of sender matching, which lets a mailbox
 * rule forward CoStar messages to addresses like `user+costar@example.com` and still be identified
 * correctly before extracting the code from the forwarded attachment content.
 */
class ForwardedCostarChallengeSite extends AbstractForwardedChallengeSite {
    /**
     * Return the registry key for forwarded CoStar challenge emails.
     */
    public function key(): string {
        return 'costar';
    }

    /**
     * Return the plus-address tag used to identify forwarded CoStar messages.
     */
    public function forwardingTag(): string {
        return 'costar';
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, [
            '/one-time CoStar access code is (?<code>\d{6})/i',
            '/CoStar access code is (?<code>\d{6})/i',
            '/\b(?<code>\d{6})\b/',
        ] );
    }
}
