<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DPRMC\Gofer2FA\ChallengeSiteRegistry;
use DPRMC\Gofer2FA\Exceptions\UnknownChallengeSite;
use DPRMC\Gofer2FA\Sites\CustomRegexChallengeSite;
use PHPUnit\Framework\TestCase;

class ChallengeSiteRegistryTest extends TestCase {
    public function testItStoresAndReturnsSitesByNormalizedKey(): void {
        $site = new CustomRegexChallengeSite( 'Acme', ['login@acme.test'], ['/\b(?<code>\d{6})\b/'] );
        $registry = new ChallengeSiteRegistry( [$site] );

        $resolved = $registry->get( 'acme' );

        $this->assertSame( $site, $resolved );
        $this->assertArrayHasKey( 'acme', $registry->all() );
    }

    public function testItThrowsForUnknownSiteKeys(): void {
        $registry = new ChallengeSiteRegistry();

        $this->expectException( UnknownChallengeSite::class );

        $registry->get( 'missing-site' );
    }
}
