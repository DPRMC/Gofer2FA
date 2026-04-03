<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\MailboxClients\Office365GraphMailboxClient;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;

class Office365GraphMailboxClientTest extends TestCase {
    public function testItNormalizesGraphMessagesAndTextAttachments(): void {
        $httpClient = function ( string $method, string $url, array $headers, ?string $body = NULL ): array {
            if ( $method === 'POST' && strpos( $url, '/oauth2/v2.0/token' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'access_token' => 'test-token',
                        'expires_in' => 3600,
                    ] ),
                ];
            }

            if ( strpos( $url, '/mailFolders/inbox/messages?' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'value' => [
                            [
                                'id' => 'message-1',
                                'subject' => 'CoStar code',
                                'from' => [ 'emailAddress' => [ 'address' => 'forwarder@example.com' ] ],
                                'toRecipients' => [
                                    [ 'emailAddress' => [ 'address' => 'user+costar@example.com' ] ],
                                ],
                                'receivedDateTime' => '2026-04-03T00:05:00+00:00',
                                'body' => [
                                    'contentType' => 'html',
                                    'content' => '<p>See attached text file.</p>',
                                ],
                                'bodyPreview' => 'See attached text file.',
                                'hasAttachments' => TRUE,
                            ],
                            [
                                'id' => 'message-2',
                                'subject' => 'Old code',
                                'from' => [ 'emailAddress' => [ 'address' => 'forwarder@example.com' ] ],
                                'toRecipients' => [
                                    [ 'emailAddress' => [ 'address' => 'user+costar@example.com' ] ],
                                ],
                                'receivedDateTime' => '2026-04-01T23:59:00+00:00',
                                'body' => [
                                    'contentType' => 'text',
                                    'content' => 'Old code body.',
                                ],
                                'bodyPreview' => 'Old code body.',
                                'hasAttachments' => FALSE,
                            ],
                        ],
                    ] ),
                ];
            }

            if ( strpos( $url, '/messages/message-1/attachments?' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'value' => [
                            [
                                'id' => 'att-1',
                                'name' => 'costar-code.txt',
                                'contentType' => 'text/plain',
                            ],
                            [
                                'id' => 'att-2',
                                'name' => 'image.png',
                                'contentType' => 'image/png',
                            ],
                        ],
                    ] ),
                ];
            }

            if ( strpos( $url, '/messages/message-1/attachments/att-1/$value' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => 'Your one-time CoStar access code is 132584.',
                ];
            }

            $this->fail( 'Unexpected Office 365 HTTP request: ' . $method . ' ' . $url );
        };

        $client = new Office365GraphMailboxClient(
            'tenant-id',
            'client-id',
            'secret',
            'mailbox@example.com',
            'inbox',
            'https://graph.microsoft.com/v1.0',
            $httpClient
        );

        $messages = array_values( iterator_to_array(
            $client->findMessages(
                new MessageQuery(
                    [ 'forwarder@example.com' ],
                    new DateTimeImmutable( '2026-04-02T00:00:00+00:00' ),
                    25,
                    [ 'user+costar@example.com' ]
                )
            )
        ) );

        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( 'message-1', $messages[0]->getId() );
        $this->assertSame( 'forwarder@example.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'user+costar@example.com', $messages[0]->getToAddress() );
        $this->assertSame( 'CoStar code', $messages[0]->getSubject() );
        $this->assertSame( 'See attached text file.', $messages[0]->getTextBody() );
        $this->assertSame( '<p>See attached text file.</p>', $messages[0]->getHtmlBody() );
        $this->assertCount( 2, $messages[0]->getAttachments() );
        $this->assertSame( 'costar-code.txt', $messages[0]->getAttachments()[0]->getFilename() );
        $this->assertSame( 'Your one-time CoStar access code is 132584.', $messages[0]->getAttachments()[0]->getContent() );
        $this->assertNull( $messages[0]->getAttachments()[1]->getContent() );
    }
}
