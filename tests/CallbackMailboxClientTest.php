<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeInterface;
use DPRMC\Gofer2FA\Adapters\CallbackMailboxClient;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class CallbackMailboxClientTest extends TestCase {
    public function testItNormalizesArrayMessages(): void {
        $client = new CallbackMailboxClient( static function (): array {
            return [[
                'id' => 'message-1',
                'from_address' => 'No-Reply@Accounts.Google.com',
                'subject' => 'Google verification',
                'text_body' => 'G-123456',
                'html_body' => '<p>G-123456</p>',
                'received_at' => '2026-04-02T08:00:00+00:00',
            ]];
        } );

        $messages = iterator_to_array( $client->findMessages( new MessageQuery() ) );

        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( 'message-1', $messages[0]->getId() );
        $this->assertSame( 'no-reply@accounts.google.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'G-123456', $messages[0]->getTextBody() );
        $this->assertInstanceOf( DateTimeInterface::class, $messages[0]->getReceivedAt() );
    }

    public function testItRejectsUnsupportedMessageTypes(): void {
        $client = new CallbackMailboxClient( static function (): array {
            return ['invalid'];
        } );

        $this->expectException( UnexpectedValueException::class );

        iterator_to_array( $client->findMessages( new MessageQuery() ) );
    }
}
