<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DPRMC\Gofer2FA\Mime\MimeMessageParser;
use PHPUnit\Framework\TestCase;

class MimeMessageParserTest extends TestCase {
    public function testItParsesHeadersBodiesAndTextAttachments(): void {
        $rawMessage = implode( "\r\n", [
            'Message-ID: <mime-1@example.com>',
            'Date: Fri, 03 Apr 2026 00:05:00 +0000',
            'From: Forwarder <forwarder@example.com>',
            'To: User <user+costar@example.com>',
            'Subject: CoStar code',
            'Content-Type: multipart/mixed; boundary="gofer-boundary"',
            '',
            '--gofer-boundary',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'See attached text file.',
            '--gofer-boundary',
            'Content-Type: text/plain; name="costar-code.txt"',
            'Content-Disposition: attachment; filename="costar-code.txt"',
            '',
            'Your one-time CoStar access code is 132584.',
            '--gofer-boundary--',
            '',
        ] );

        $parsed = ( new MimeMessageParser() )->parse( $rawMessage, 'fallback-id' );

        $this->assertSame( '<mime-1@example.com>', $parsed['id'] );
        $this->assertSame( 'forwarder@example.com', $parsed['from_address'] );
        $this->assertSame( 'user+costar@example.com', $parsed['to_address'] );
        $this->assertSame( 'CoStar code', $parsed['subject'] );
        $this->assertSame( 'See attached text file.', $parsed['text_body'] );
        $this->assertCount( 1, $parsed['attachments'] );
        $this->assertSame( 'costar-code.txt', $parsed['attachments'][0]['filename'] );
        $this->assertSame( 'Your one-time CoStar access code is 132584.', $parsed['attachments'][0]['content'] );
    }
}
