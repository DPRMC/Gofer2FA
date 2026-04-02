<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

class GoogleChallengeSite extends AbstractChallengeSite {
    /**
     * Return the registry key for Google challenge emails.
     */
    public function key(): string {
        return 'google';
    }

    /**
     * Return the Google sender addresses used for challenge emails.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return ['no-reply@accounts.google.com'];
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, [
            '/G-?(?<code>\d{6})/i',
            '/verification code[^A-Z0-9]*(?<code>\d{6})/i',
            '/\b(?<code>\d{6})\b/',
        ] );
    }
}
