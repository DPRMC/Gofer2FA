<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

class MicrosoftChallengeSite extends AbstractChallengeSite {
    /**
     * Return the registry key for Microsoft challenge emails.
     */
    public function key(): string {
        return 'microsoft';
    }

    /**
     * Return the Microsoft sender addresses used for challenge emails.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return ['account-security-noreply@accountprotection.microsoft.com'];
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, [
            '/security code[^0-9]*(?<code>\d{4,8})/i',
            '/use (?<code>\d{4,8}) as/i',
            '/\b(?<code>\d{4,8})\b/',
        ] );
    }
}
