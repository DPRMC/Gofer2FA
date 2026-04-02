<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DPRMC\Gofer2FA\Exceptions\UnknownChallengeSite;
use PHPUnit\Framework\TestCase;

class UnknownChallengeSiteTest extends TestCase {
    public function testForKeyBuildsExpectedMessage(): void {
        $exception = UnknownChallengeSite::forKey( 'missing-site' );

        $this->assertSame( 'No 2FA site parser is registered for "missing-site".', $exception->getMessage() );
    }
}
