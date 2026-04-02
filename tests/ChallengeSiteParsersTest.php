<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Sites\CustomRegexChallengeSite;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;
use DPRMC\Gofer2FA\Sites\GitHubChallengeSite;
use DPRMC\Gofer2FA\Sites\GoogleChallengeSite;
use DPRMC\Gofer2FA\Sites\OktaChallengeSite;
use DPRMC\Gofer2FA\Tests\Support\FakeMailboxMessage;
use PHPUnit\Framework\TestCase;

class ChallengeSiteParsersTest extends TestCase {
    public function testForwardedCostarParserExtractsCodeFromTextAttachment(): void {
        $site = new ForwardedCostarChallengeSite();
        $message = new FakeMailboxMessage(
            'message-0',
            '9173313518@vzwpix.com',
            'CoStar access code',
            'See attachment for your code.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' ),
            [
                [
                    'filename' => 'costar-code.txt',
                    'content_type' => 'text/plain',
                    'content' => 'Your one-time CoStar access code is 132584.  If you wish to stop receiving these messages, reply "STOP" to opt-out. Reply "HELP" for help.',
                ],
            ],
            'user2+costar@example.com'
        );

        $this->assertSame( '132584', $site->parseCode( $message ) );
        $this->assertTrue( $site->matchesMessage( $message ) );
        $this->assertSame( 'costar', $site->forwardingTag() );
        $this->assertSame( [], $site->senderAddresses() );
        $this->assertSame( [], $site->messageQuery()->toAddresses() );
    }

    public function testForwardedCostarParserRejectsWrongRecipient(): void {
        $site = new ForwardedCostarChallengeSite();
        $message = new FakeMailboxMessage(
            'message-0b',
            '9173313518@vzwpix.com',
            'CoStar access code',
            'See attachment for your code.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' ),
            [
                [
                    'filename' => 'costar-code.txt',
                    'content_type' => 'text/plain',
                    'content' => 'Your one-time CoStar access code is 132584.',
                ],
            ],
            'user2+other@example.com'
        );

        $this->assertFalse( $site->matchesMessage( $message ) );
    }

    public function testForwardedCostarParserRejectsRecipientWithoutPlusTag(): void {
        $site = new ForwardedCostarChallengeSite();
        $message = new FakeMailboxMessage(
            'message-0c',
            '9173313518@vzwpix.com',
            'CoStar access code',
            'See attachment for your code.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' ),
            [],
            'user@example.com'
        );

        $this->assertFalse( $site->matchesMessage( $message ) );
    }

    public function testGitHubParserExtractsVerificationCode(): void {
        $site = new GitHubChallengeSite();
        $message = new FakeMailboxMessage(
            'message-1',
            'noreply@github.com',
            'GitHub login',
            'Your GitHub verification code is 112233.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' )
        );

        $this->assertSame( '112233', $site->parseCode( $message ) );
    }

    public function testGoogleParserExtractsGPrefixedCode(): void {
        $site = new GoogleChallengeSite();
        $message = new FakeMailboxMessage(
            'message-2',
            'no-reply@accounts.google.com',
            'Google sign-in',
            'Use G-654321 to finish signing in.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' )
        );

        $this->assertSame( '654321', $site->parseCode( $message ) );
    }

    public function testOktaParserExtractsVerificationCode(): void {
        $site = new OktaChallengeSite();
        $message = new FakeMailboxMessage(
            'message-3',
            'no-reply@okta.com',
            'Okta verification',
            'Your verification code is 778899.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' )
        );

        $this->assertSame( '778899', $site->parseCode( $message ) );
    }

    public function testCustomRegexSiteParsesHtmlAttachmentContent(): void {
        $site = new CustomRegexChallengeSite(
            'custom',
            ['sender@example.com'],
            ['/verification code[^0-9]*(?<code>\d{6})/i']
        );
        $message = new FakeMailboxMessage(
            'message-4',
            'sender@example.com',
            'Attachment only',
            'See attachment.',
            NULL,
            new DateTimeImmutable( '2026-04-02 08:00:00' ),
            [
                [
                    'filename' => 'code.html',
                    'content_type' => 'text/html',
                    'content' => '<html><body><p>Your verification code is <strong>443322</strong>.</p></body></html>',
                ],
            ]
        );

        $this->assertSame( '443322', $site->parseCode( $message ) );
    }
}
