<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Sites;

/**
 * Site parser for CoStar access codes that have been forwarded to mailbox aliases.
 *
 * This parser uses `To`-address matching instead of the normal sender-based flow, which lets a mailbox
 * rule forward CoStar messages to addresses like `user+costar@example.com` and still be identified
 * correctly before extracting the code from the forwarded attachment content.
 */
class ForwardedCostarChallengeSite extends AbstractForwardedChallengeSite {
    /**
     * @var string[]
     */
    private array $toAddresses;

    /**
     * @param string[] $toAddresses
     */
    public function __construct( array $toAddresses ) {
        $this->toAddresses = array_values( array_filter( array_map( static function ( string $toAddress ): string {
            return strtolower( trim( $toAddress ) );
        }, $toAddresses ) ) );
    }

    /**
     * Return the registry key for forwarded CoStar challenge emails.
     */
    public function key(): string {
        return 'forwarded-costar';
    }

    /**
     * Return the original CoStar sender addresses when known.
     *
     * @return string[]
     */
    public function senderAddresses(): array {
        return ['9173313518@vzwpix.com'];
    }

    /**
     * Return the forwarded recipient addresses used to identify CoStar challenge emails.
     *
     * @return string[]
     */
    public function toAddresses(): array {
        return $this->toAddresses;
    }

    protected function extractFromContent( string $content ): ?string {
        return $this->extractFirstMatchingCode( $content, [
            '/one-time CoStar access code is (?<code>\d{6})/i',
            '/CoStar access code is (?<code>\d{6})/i',
            '/\b(?<code>\d{6})\b/',
        ] );
    }
}
