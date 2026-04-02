<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxAttachment;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxMessage;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ArrayMailboxMessageTest extends TestCase {
    public function testItNormalizesDatesAndAttachments(): void {
        $receivedAt = new DateTimeImmutable( '2026-04-02 08:00:00' );
        $message = new ArrayMailboxMessage( [
            'id' => 'message-1',
            'from_address' => ' Sender@Test.com ',
            'to_address' => ' User2+Costar@Example.com ',
            'subject' => 'Subject',
            'text_body' => 'Body',
            'html_body' => '<p>Body</p>',
            'received_at' => $receivedAt,
            'attachments' => [
                'Verification code 456789',
                [
                    'filename' => 'extra.txt',
                    'content' => 'Another code 999999',
                ],
                new ArrayMailboxAttachment( [
                    'filename' => 'existing.txt',
                    'content_type' => 'text/plain',
                    'content' => 'Existing attachment',
                ] ),
            ],
        ] );

        $this->assertSame( 'sender@test.com', $message->getFromAddress() );
        $this->assertSame( 'user2+costar@example.com', $message->getToAddress() );
        $this->assertSame( $receivedAt, $message->getReceivedAt() );
        $this->assertCount( 3, $message->getAttachments() );
        $this->assertSame( 'Verification code 456789', $message->getAttachments()[0]->getContent() );
        $this->assertSame( 'extra.txt', $message->getAttachments()[1]->getFilename() );
        $this->assertSame( 'existing.txt', $message->getAttachments()[2]->getFilename() );
    }

    public function testItReturnsEmptyAttachmentsWhenInputIsNotIterable(): void {
        $message = new ArrayMailboxMessage( [
            'attachments' => 123,
        ] );

        $this->assertSame( [], $message->getAttachments() );
    }

    public function testItRejectsUnsupportedAttachmentTypes(): void {
        $this->expectException( UnexpectedValueException::class );
        $this->expectExceptionMessage( 'Mailbox message attachments must be MailboxAttachmentInterface instances, arrays, or strings.' );

        new ArrayMailboxMessage( [
            'attachments' => [new \stdClass()],
        ] );
    }
}
