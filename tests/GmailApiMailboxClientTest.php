<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\MailboxClients\GmailApiMailboxClient;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;

class GmailApiMailboxClientTest extends TestCase {
    public function testItNormalizesGmailMessagesAndAttachments(): void {
        $httpClient = function ( string $method, string $url, array $headers, ?string $body = NULL ): array {
            if ( $method === 'POST' && $url === 'https://oauth2.googleapis.com/token' ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'access_token' => 'gmail-token',
                        'expires_in' => 3600,
                    ] ),
                ];
            }

            if ( strpos( $url, '/users/me/messages?' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'messages' => [
                            [ 'id' => 'gmail-1' ],
                            [ 'id' => 'gmail-2' ],
                        ],
                    ] ),
                ];
            }

            if ( strpos( $url, '/users/me/messages/gmail-1?' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'id' => 'gmail-1',
                        'internalDate' => '1775174700000',
                        'payload' => [
                            'headers' => [
                                [ 'name' => 'From', 'value' => 'Forwarder <forwarder@example.com>' ],
                                [ 'name' => 'To', 'value' => 'User <user+costar@example.com>' ],
                                [ 'name' => 'Subject', 'value' => 'CoStar code' ],
                            ],
                            'parts' => [
                                [
                                    'mimeType' => 'text/plain',
                                    'filename' => '',
                                    'body' => [
                                        'data' => rtrim( strtr( base64_encode( 'See attached text file.' ), '+/', '-_' ), '=' ),
                                    ],
                                ],
                                [
                                    'mimeType' => 'text/plain',
                                    'filename' => 'costar-code.txt',
                                    'body' => [
                                        'attachmentId' => 'attachment-1',
                                    ],
                                ],
                            ],
                        ],
                    ] ),
                ];
            }

            if ( strpos( $url, '/users/me/messages/gmail-2?' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'id' => 'gmail-2',
                        'internalDate' => '1775000000000',
                        'payload' => [
                            'headers' => [
                                [ 'name' => 'From', 'value' => 'Forwarder <forwarder@example.com>' ],
                                [ 'name' => 'To', 'value' => 'User <user+other@example.com>' ],
                                [ 'name' => 'Subject', 'value' => 'Wrong code' ],
                            ],
                            'parts' => [
                                [
                                    'mimeType' => 'text/plain',
                                    'filename' => '',
                                    'body' => [
                                        'data' => rtrim( strtr( base64_encode( 'Ignore this message.' ), '+/', '-_' ), '=' ),
                                    ],
                                ],
                            ],
                        ],
                    ] ),
                ];
            }

            if ( strpos( $url, '/users/me/messages/gmail-1/attachments/attachment-1' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => json_encode( [
                        'data' => rtrim( strtr( base64_encode( 'Your one-time CoStar access code is 132584.' ), '+/', '-_' ), '=' ),
                    ] ),
                ];
            }

            $this->fail( 'Unexpected Gmail HTTP request: ' . $method . ' ' . $url );
        };

        $client = new GmailApiMailboxClient(
            'me',
            'client-id',
            'client-secret',
            'refresh-token',
            'https://gmail.googleapis.com/gmail/v1',
            $httpClient
        );

        $messages = array_values( iterator_to_array(
            $client->findMessages(
                new MessageQuery(
                    [ 'forwarder@example.com' ],
                    new DateTimeImmutable( '2026-04-02T00:00:00+00:00' ),
                    10,
                    [ 'user+costar@example.com' ]
                )
            )
        ) );

        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( 'gmail-1', $messages[0]->getId() );
        $this->assertSame( 'forwarder@example.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'user+costar@example.com', $messages[0]->getToAddress() );
        $this->assertSame( 'See attached text file.', $messages[0]->getTextBody() );
        $this->assertCount( 1, $messages[0]->getAttachments() );
        $this->assertSame( 'costar-code.txt', $messages[0]->getAttachments()[0]->getFilename() );
        $this->assertSame( 'Your one-time CoStar access code is 132584.', $messages[0]->getAttachments()[0]->getContent() );
    }
}
