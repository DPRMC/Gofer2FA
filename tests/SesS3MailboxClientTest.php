<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\MailboxClients\SesS3MailboxClient;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;

class SesS3MailboxClientTest extends TestCase {
    public function testItNormalizesSesS3MimeMessages(): void {
        $httpClient = function ( string $method, string $url, array $headers, ?string $body = NULL ): array {
            if ( strpos( $url, 'list-type=2' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
  <Contents>
    <Key>inbound/message-1.eml</Key>
    <LastModified>2026-04-03T00:05:00.000Z</LastModified>
  </Contents>
  <Contents>
    <Key>inbound/message-2.eml</Key>
    <LastModified>2026-04-02T00:05:00.000Z</LastModified>
  </Contents>
</ListBucketResult>
XML,
                ];
            }

            if ( strpos( $url, 'inbound/message-1.eml' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => implode( "\r\n", [
                        'Message-ID: <ses-1@example.com>',
                        'Date: Fri, 03 Apr 2026 00:05:00 +0000',
                        'From: Forwarder <forwarder@example.com>',
                        'To: User <user+costar@example.com>',
                        'Subject: CoStar code',
                        'Content-Type: multipart/mixed; boundary="ses-boundary"',
                        '',
                        '--ses-boundary',
                        'Content-Type: text/plain; charset=UTF-8',
                        '',
                        'See attached text file.',
                        '--ses-boundary',
                        'Content-Type: text/plain; name="costar-code.txt"',
                        'Content-Disposition: attachment; filename="costar-code.txt"',
                        '',
                        'Your one-time CoStar access code is 132584.',
                        '--ses-boundary--',
                        '',
                    ] ),
                ];
            }

            if ( strpos( $url, 'inbound/message-2.eml' ) !== FALSE ) {
                return [
                    'status' => 200,
                    'body' => implode( "\r\n", [
                        'Message-ID: <ses-2@example.com>',
                        'Date: Thu, 02 Apr 2026 00:05:00 +0000',
                        'From: Forwarder <forwarder@example.com>',
                        'To: User <user+other@example.com>',
                        'Subject: Wrong code',
                        'Content-Type: text/plain; charset=UTF-8',
                        '',
                        'Ignore this message.',
                    ] ),
                ];
            }

            $this->fail( 'Unexpected SES/S3 HTTP request: ' . $method . ' ' . $url );
        };

        $client = new SesS3MailboxClient(
            'access-key',
            'secret-key',
            'us-east-1',
            'mail-bucket',
            'inbound',
            NULL,
            $httpClient
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

        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( '<ses-1@example.com>', $messages[0]->getId() );
        $this->assertSame( 'forwarder@example.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'user+costar@example.com', $messages[0]->getToAddress() );
        $this->assertCount( 1, $messages[0]->getAttachments() );
        $this->assertSame( 'costar-code.txt', $messages[0]->getAttachments()[0]->getFilename() );
        $this->assertSame( 'Your one-time CoStar access code is 132584.', $messages[0]->getAttachments()[0]->getContent() );
    }
}
