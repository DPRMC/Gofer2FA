<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

class GitHubChallengeSite extends AbstractChallengeSite {
    /**
     * Return the registry key for GitHub challenge emails.
     */
    public function key(): string {
        return 'github';
    }

    /**
     * Return the GitHub sender addresses used for challenge emails.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return ['noreply@github.com'];
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, [
            '/verification code[^0-9]*(?<code>\d{6})/i',
            '/one-time code[^0-9]*(?<code>\d{6})/i',
            '/\b(?<code>\d{6})\b/',
        ] );
    }
}
