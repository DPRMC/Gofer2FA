<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

/**
 * Configurable site parser built from caller-supplied sender addresses and regex patterns.
 *
 * This is the fastest way to add support for a new 2FA email format without creating a dedicated class.
 * It plugs directly into the normal `Gofer2FA` flow through the shared behavior in `AbstractChallengeSite`.
 */
class CustomRegexChallengeSite extends AbstractChallengeSite {
    private string $key;

    /**
     * @var string[]
     */
    private array $senderAddresses;

    /**
     * @var string[]
     */
    private array $patterns;

    /**
     * Create a site parser from caller-provided sender addresses and regex patterns.
     *
     * @param string[] $senderAddresses
     * @param string[] $patterns
     */
    public function __construct( string $key, array $senderAddresses, array $patterns ) {
        $this->key = strtolower( trim( $key ) );
        $this->senderAddresses = array_values( array_filter( array_map( static function ( string $sender ): string {
            return strtolower( trim( $sender ) );
        }, $senderAddresses ) ) );
        $this->patterns = $patterns;
    }

    /**
     * Return the normalized lookup key for this site.
     */
    public function key(): string {
        return $this->key;
    }

    /**
     * Return the sender addresses that identify this site's challenge emails.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return $this->senderAddresses;
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, $this->patterns );
    }
}
