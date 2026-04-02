<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;

class MessageQueryTest extends TestCase {
    public function testItNormalizesFromAddressesAndDefaultsLimit(): void {
        $query = new MessageQuery( [
            ' Sales@Example.com ',
            '',
            'sales@example.com',
            'SUPPORT@example.com',
        ], NULL, 0 );

        $this->assertSame( [
            'sales@example.com',
            'support@example.com',
        ], $query->fromAddresses() );
        $this->assertSame( 25, $query->limit() );
    }

    public function testWithersReturnUpdatedCopies(): void {
        $originalSince = new DateTimeImmutable( '2026-04-02 08:00:00' );
        $newSince = new DateTimeImmutable( '2026-04-02 09:00:00' );
        $query = new MessageQuery( ['login@example.com'], $originalSince, 10 );

        $updatedSince = $query->withSince( $newSince );
        $updatedLimit = $query->withLimit( 50 );

        $this->assertSame( $originalSince, $query->since() );
        $this->assertSame( 10, $query->limit() );
        $this->assertSame( $newSince, $updatedSince->since() );
        $this->assertSame( 10, $updatedSince->limit() );
        $this->assertSame( 50, $updatedLimit->limit() );
        $this->assertSame( $originalSince, $updatedLimit->since() );
    }
}
