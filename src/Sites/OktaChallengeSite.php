<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

/**
 * Built-in parser for Okta verification emails.
 *
 * It plugs into the standard sender-based site flow and extracts codes from the common wording found in
 * Okta login challenge messages.
 */
class OktaChallengeSite extends AbstractChallengeSite {
    /**
     * Return the registry key for Okta challenge emails.
     */
    public function key(): string {
        return 'okta';
    }

    /**
     * Return the Okta sender addresses used for challenge emails.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return ['no-reply@okta.com'];
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, [
            '/verification code[^0-9]*(?<code>\d{6})/i',
            '/security code[^0-9]*(?<code>\d{6})/i',
            '/\b(?<code>\d{6})\b/',
        ] );
    }
}
