<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\MailboxClients\ImapMailboxClient;
use DPRMC\Gofer2FA\Tests\Support\FakeImapRuntime;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;

class ImapMailboxClientTest extends TestCase {
    public function testItNormalizesImapMessagesBodiesAndAttachments(): void {
        $runtime = new FakeImapRuntime();
        $runtime->searchResults = [ 2001, 2002 ];
        $runtime->overviews = [
            2002 => [
                (object) [
                    'subject' => 'Wrong recipient',
                    'from' => 'Forwarder <forwarder@example.com>',
                    'to' => 'User <user+other@example.com>',
                    'date' => 'Thu, 03 Apr 2026 00:06:00 +0000',
                    'message_id' => '<message-2002@example.com>',
                ],
            ],
            2001 => [
                (object) [
                    'subject' => 'CoStar code',
                    'from' => 'Forwarder <forwarder@example.com>',
                    'to' => 'User <user+costar@example.com>',
                    'date' => 'Thu, 03 Apr 2026 00:05:00 +0000',
                    'message_id' => '<message-2001@example.com>',
                ],
            ],
        ];
        $runtime->structures = [
            2002 => (object) [
                'type' => 1,
                'parts' => [
                    (object) [
                        'type' => 0,
                        'subtype' => 'PLAIN',
                        'encoding' => 0,
                    ],
                ],
            ],
            2001 => (object) [
                'type' => 1,
                'parts' => [
                    (object) [
                        'type' => 0,
                        'subtype' => 'PLAIN',
                        'encoding' => 0,
                    ],
                    (object) [
                        'type' => 3,
                        'subtype' => 'OCTET-STREAM',
                        'encoding' => 0,
                        'disposition' => 'ATTACHMENT',
                        'dparameters' => [
                            (object) [
                                'attribute' => 'filename',
                                'value' => 'costar-code.txt',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $runtime->bodies = [
            '2002:1' => 'Ignore this message.',
            '2001:1' => 'See attached text file.',
            '2001:2' => 'Your one-time CoStar access code is 132584.',
        ];

        $client = new ImapMailboxClient(
            '{imap.example.com:993/imap/ssl}INBOX',
            'user@example.com',
            'secret',
            0,
            0,
            [],
            $runtime
        );

        $messages = array_values( iterator_to_array(
            $client->findMessages(
                new MessageQuery(
                    [ 'forwarder@example.com' ],
                    new DateTimeImmutable( '2026-04-03T00:00:00+00:00' ),
                    10,
                    [ 'user+costar@example.com' ]
                )
            )
        ) );

        $this->assertTrue( $runtime->opened );
        $this->assertTrue( $runtime->closed );
        $this->assertSame( 'SINCE "03-Apr-2026"', $runtime->searchCriteria[0] );
        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( '<message-2001@example.com>', $messages[0]->getId() );
        $this->assertSame( 'forwarder@example.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'user+costar@example.com', $messages[0]->getToAddress() );
        $this->assertSame( 'CoStar code', $messages[0]->getSubject() );
        $this->assertSame( 'See attached text file.', $messages[0]->getTextBody() );
        $this->assertCount( 1, $messages[0]->getAttachments() );
        $this->assertSame( 'costar-code.txt', $messages[0]->getAttachments()[0]->getFilename() );
        $this->assertSame( 'Your one-time CoStar access code is 132584.', $messages[0]->getAttachments()[0]->getContent() );
    }
}
