<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\ValueObjects\TwoFactorCode;
use PHPUnit\Framework\TestCase;

class TwoFactorCodeTest extends TestCase {
    public function testToArrayContainsSerializedFields(): void {
        $receivedAt = new DateTimeImmutable( '2026-04-02T08:00:00+00:00' );
        $code = new TwoFactorCode(
            'microsoft',
            '123456',
            'message-1',
            'account-security-noreply@accountprotection.microsoft.com',
            'Your Microsoft security code',
            $receivedAt
        );

        $this->assertSame( [
            'site_key' => 'microsoft',
            'code' => '123456',
            'message_id' => 'message-1',
            'from_address' => 'account-security-noreply@accountprotection.microsoft.com',
            'subject' => 'Your Microsoft security code',
            'received_at' => '2026-04-02T08:00:00+00:00',
        ], $code->toArray() );
    }
}
