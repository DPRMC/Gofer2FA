<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\MailboxClients\Pop3MailboxClient;
use DPRMC\Gofer2FA\Tests\Support\FakePop3Runtime;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use PHPUnit\Framework\TestCase;

class Pop3MailboxClientTest extends TestCase {
    public function testItNormalizesPop3MessagesThroughMimeParsing(): void {
        $runtime = new FakePop3Runtime();
        $runtime->messages = [
            [ 'number' => 1, 'size' => 1000 ],
            [ 'number' => 2, 'size' => 1000 ],
        ];
        $runtime->rawMessages = [
            2 => implode( "\r\n", [
                'Message-ID: <pop-2@example.com>',
                'Date: Fri, 03 Apr 2026 00:06:00 +0000',
                'From: Forwarder <forwarder@example.com>',
                'To: User <user+other@example.com>',
                'Subject: Wrong code',
                'Content-Type: text/plain; charset=UTF-8',
                '',
                'Ignore this message.',
            ] ),
            1 => implode( "\r\n", [
                'Message-ID: <pop-1@example.com>',
                'Date: Fri, 03 Apr 2026 00:05:00 +0000',
                'From: Forwarder <forwarder@example.com>',
                'To: User <user+costar@example.com>',
                'Subject: CoStar code',
                'Content-Type: multipart/mixed; boundary="pop-boundary"',
                '',
                '--pop-boundary',
                'Content-Type: text/plain; charset=UTF-8',
                '',
                'See attached text file.',
                '--pop-boundary',
                'Content-Type: text/plain; name="costar-code.txt"',
                'Content-Disposition: attachment; filename="costar-code.txt"',
                '',
                'Your one-time CoStar access code is 132584.',
                '--pop-boundary--',
                '',
            ] ),
        ];

        $client = new Pop3MailboxClient(
            'pop.example.com',
            995,
            'user@example.com',
            'secret',
            TRUE,
            30,
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
        $this->assertTrue( $runtime->authenticated );
        $this->assertTrue( $runtime->closed );
        $this->assertCount( 1, $messages );
        $this->assertInstanceOf( MailboxMessageInterface::class, $messages[0] );
        $this->assertSame( '<pop-1@example.com>', $messages[0]->getId() );
        $this->assertSame( 'forwarder@example.com', $messages[0]->getFromAddress() );
        $this->assertSame( 'user+costar@example.com', $messages[0]->getToAddress() );
        $this->assertSame( 'See attached text file.', $messages[0]->getTextBody() );
        $this->assertCount( 1, $messages[0]->getAttachments() );
        $this->assertSame( 'costar-code.txt', $messages[0]->getAttachments()[0]->getFilename() );
        $this->assertSame( 'Your one-time CoStar access code is 132584.', $messages[0]->getAttachments()[0]->getContent() );
    }

    public function testWithStartTlsUsesExplicitStartTlsMode(): void {
        $runtime = new FakePop3Runtime();
        $runtime->messages = [];

        $client = Pop3MailboxClient::withStartTls(
            'pop.example.com',
            110,
            'user@example.com',
            'secret',
            30,
            $runtime
        );

        iterator_to_array( $client->findMessages( new MessageQuery() ) );

        $this->assertTrue( $runtime->opened );
        $this->assertFalse( $runtime->lastUseTls );
        $this->assertTrue( $runtime->lastUseStartTls );
    }
}
