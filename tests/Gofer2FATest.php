<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateInterval;
use DateTimeImmutable;
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\Sites\CustomRegexChallengeSite;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;
use DPRMC\Gofer2FA\Sites\MicrosoftChallengeSite;
use DPRMC\Gofer2FA\Tests\Support\FakeMailboxMessage;
use DPRMC\Gofer2FA\Tests\Support\InMemoryMailboxClient;
use PHPUnit\Framework\TestCase;

class Gofer2FATest extends TestCase {
    public function testFetchCodeReturnsParsedCodeForMatchingSender(): void {
        $receivedAt = new DateTimeImmutable( '2026-04-02 08:00:00' );
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-1',
                'account-security-noreply@accountprotection.microsoft.com',
                'Your Microsoft security code',
                'Use security code 712844 to verify your sign in.',
                NULL,
                $receivedAt
            ),
        ] );

        $gofer = Gofer2FA::withDefaultSites( $mailbox );

        $code = $gofer->fetchCode( 'microsoft' );

        $this->assertNotNull( $code );
        $this->assertSame( 'microsoft', $code->siteKey() );
        $this->assertSame( '712844', $code->code() );
        $this->assertSame( 'message-1', $code->messageId() );
        $this->assertSame( $receivedAt, $code->receivedAt() );
        $this->assertCount( 1, $mailbox->queries() );
        $this->assertSame(
            ['account-security-noreply@accountprotection.microsoft.com'],
            $mailbox->queries()[0]->fromAddresses()
        );
    }

    public function testFetchCodeIgnoresMessagesOlderThanSince(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-1',
                'account-security-noreply@accountprotection.microsoft.com',
                'Your Microsoft security code',
                'Use security code 712844 to verify your sign in.',
                NULL,
                new DateTimeImmutable( '2026-04-02 07:59:00' )
            ),
        ] );

        $gofer = Gofer2FA::withDefaultSites( $mailbox );
        $since = new DateTimeImmutable( '2026-04-02 08:00:00' );

        $code = $gofer->fetchCode( 'microsoft', $since );

        $this->assertNull( $code );
    }

    public function testFetchCodeIgnoresMessagesFromUnexpectedSender(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-1',
                'attacker@example.com',
                'Your Microsoft security code',
                'Use security code 712844 to verify your sign in.'
            ),
        ] );

        $gofer = Gofer2FA::withDefaultSites( $mailbox );

        $this->assertNull( $gofer->fetchCode( 'microsoft' ) );
    }

    public function testRegisterSiteAllowsCustomParsers(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-2',
                'login@acme.test',
                'Acme Login',
                'Your Acme code is 445566.'
            ),
        ] );

        $gofer = new Gofer2FA( $mailbox );
        $gofer->registerSite( new CustomRegexChallengeSite(
            'acme',
            ['login@acme.test'],
            ['/code is (?<code>\d{6})/i']
        ) );

        $code = $gofer->fetchCode( 'acme' );

        $this->assertNotNull( $code );
        $this->assertSame( '445566', $code->code() );
    }

    public function testWaitForCodeReturnsImmediatelyWhenCodeIsAlreadyAvailable(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-3',
                'account-security-noreply@accountprotection.microsoft.com',
                'Your Microsoft security code',
                'Use security code 998877 to verify your sign in.',
                NULL,
                new DateTimeImmutable( '2026-04-02 08:00:10' )
            ),
        ] );

        $gofer = Gofer2FA::withDefaultSites( $mailbox );

        $code = $gofer->waitForCode( 'microsoft', 1, 1, new DateTimeImmutable( '2026-04-02 08:00:00' ) );

        $this->assertNotNull( $code );
        $this->assertSame( '998877', $code->code() );
        $this->assertCount( 1, $mailbox->queries() );
    }

    public function testSitesReturnsRegisteredSites(): void {
        $gofer = Gofer2FA::withDefaultSites( new InMemoryMailboxClient( [] ) );

        $sites = $gofer->sites();

        $this->assertArrayHasKey( 'costar', $sites );
        $this->assertArrayHasKey( 'github', $sites );
        $this->assertArrayHasKey( 'google', $sites );
        $this->assertArrayHasKey( 'microsoft', $sites );
        $this->assertArrayHasKey( 'okta', $sites );
        $this->assertInstanceOf( MicrosoftChallengeSite::class, $sites['microsoft'] );
    }

    public function testFetchCodeCanMatchForwardedCostarByToAddress(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-forwarded-costar',
                'forwarder@example.com',
                'Fwd: CoStar access code',
                'See attachment.',
                NULL,
                new DateTimeImmutable( '2026-04-02 08:06:00' ),
                [
                    [
                        'filename' => 'costar-code.txt',
                        'content_type' => 'text/plain',
                        'content' => 'Your one-time CoStar access code is 132584.  If you wish to stop receiving these messages, reply "STOP" to opt-out. Reply "HELP" for help.',
                    ],
                ],
                'user2+costar@example.com'
            ),
        ] );

        $gofer = new Gofer2FA( $mailbox );
        $gofer->registerSite( new ForwardedCostarChallengeSite() );

        $code = $gofer->fetchCode( 'costar' );

        $this->assertNotNull( $code );
        $this->assertSame( '132584', $code->code() );
        $this->assertSame( [], $mailbox->queries()[0]->toAddresses() );
        $this->assertSame( [], $mailbox->queries()[0]->fromAddresses() );
    }

    public function testFetchCodeIgnoresForwardedCostarMessagesWithWrongRecipient(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-forwarded-costar-wrong-to',
                'forwarder@example.com',
                'Fwd: CoStar access code',
                'See attachment.',
                NULL,
                new DateTimeImmutable( '2026-04-02 08:06:00' ),
                [
                    [
                        'filename' => 'costar-code.txt',
                        'content_type' => 'text/plain',
                        'content' => 'Your one-time CoStar access code is 132584.',
                    ],
                ],
                'user2+other@example.com'
            ),
        ] );

        $gofer = new Gofer2FA( $mailbox );
        $gofer->registerSite( new ForwardedCostarChallengeSite() );

        $this->assertNull( $gofer->fetchCode( 'costar' ) );
    }

    public function testFetchCodeReturnsNullWhenParserFindsNoCode(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-4',
                'account-security-noreply@accountprotection.microsoft.com',
                'Your Microsoft security code',
                'There is no actual code in this email.',
                NULL,
                ( new DateTimeImmutable( '2026-04-02 08:00:00' ) )->add( new DateInterval( 'PT1M' ) )
            ),
        ] );

        $gofer = Gofer2FA::withDefaultSites( $mailbox );

        $this->assertNull( $gofer->fetchCode( 'microsoft' ) );
    }

    public function testFetchCodeCanParseCodeFromAttachmentContent(): void {
        $mailbox = new InMemoryMailboxClient( [
            new FakeMailboxMessage(
                'message-5',
                'account-security-noreply@accountprotection.microsoft.com',
                'Your Microsoft security code',
                'Open the attached file for your code.',
                NULL,
                new DateTimeImmutable( '2026-04-02 08:05:00' ),
                [
                    [
                        'filename' => 'security-code.txt',
                        'content_type' => 'text/plain',
                        'content' => 'Use security code 334455 to verify your sign in.',
                    ],
                ]
            ),
        ] );

        $gofer = Gofer2FA::withDefaultSites( $mailbox );

        $code = $gofer->fetchCode( 'microsoft' );

        $this->assertNotNull( $code );
        $this->assertSame( '334455', $code->code() );
    }
}
