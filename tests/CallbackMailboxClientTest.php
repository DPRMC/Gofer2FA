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
                'to_address' => 'User2+Costar@Example.com',
                'subject' => 'Google verification',
                'text_body' => 'G-123456',
                'html_body' => '<p>G-123456</p>',
                'received_at' => '2026-04-02T08:00:00+00:00',
                'attachments' => [
                    [
                        'filename' => 'code.txt',
                        'content_type' => 'text/plain',
                        'content' => 'Backup code 654321',
                    ],
                ],
            ]];
        } );

        $messages = iterator_to_array( $client->findMessages( new MessageQuery() ) );

        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( 'message-1', $messages[0]->getId() );
        $this->assertSame( 'no-reply@accounts.google.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'user2+costar@example.com', $messages[0]->getToAddress() );
        $this->assertSame( 'G-123456', $messages[0]->getTextBody() );
        $this->assertInstanceOf( DateTimeInterface::class, $messages[0]->getReceivedAt() );
        $this->assertCount( 1, $messages[0]->getAttachments() );
        $this->assertSame( 'Backup code 654321', $messages[0]->getAttachments()[0]->getContent() );
    }

    public function testItRejectsUnsupportedMessageTypes(): void {
        $client = new CallbackMailboxClient( static function (): array {
            return ['invalid'];
        } );

        $this->expectException( UnexpectedValueException::class );

        iterator_to_array( $client->findMessages( new MessageQuery() ) );
    }

    public function testItDelegatesMessageDeletionWhenConfigured(): void {
        $deletedMessageIds = [];
        $client = new CallbackMailboxClient(
            static function (): array {
                return [];
            },
            static function ( string $messageId ) use ( &$deletedMessageIds ): void {
                $deletedMessageIds[] = $messageId;
            }
        );

        $client->deleteMessage( 'message-1' );

        $this->assertSame( [ 'message-1' ], $deletedMessageIds );
    }

    public function testItRejectsDeletionWhenNoDeleteResolverWasConfigured(): void {
        $client = new CallbackMailboxClient( static function (): array {
            return [];
        } );

        $this->expectException( UnexpectedValueException::class );

        $client->deleteMessage( 'message-1' );
    }
}
