<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA;

use DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface;
use DPRMC\Gofer2FA\Exceptions\UnknownChallengeSite;

class ChallengeSiteRegistry {
    /**
     * @var array<string, \DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface>
     */
    private array $sites = [];

    /**
     * Seed the registry with an optional collection of site parsers.
     *
     * @param iterable<\DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface> $sites
     */
    public function __construct( iterable $sites = [] ) {
        foreach ( $sites as $site ) {
            $this->register( $site );
        }
    }

    /**
     * Register or replace a site parser by its normalized key.
     */
    public function register( ChallengeSiteInterface $site ): self {
        $this->sites[strtolower( $site->key() )] = $site;

        return $this;
    }

    /**
     * Resolve a registered site parser by key.
     */
    public function get( string $siteKey ): ChallengeSiteInterface {
        $siteKey = strtolower( trim( $siteKey ) );

        if ( ! isset( $this->sites[$siteKey] ) ) {
            throw UnknownChallengeSite::forKey( $siteKey );
        }

        return $this->sites[$siteKey];
    }

    /**
     * Return all registered site parsers keyed by normalized site name.
     *
     * @return array<string, \DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface>
     */
    public function all(): array {
        return $this->sites;
    }
}
