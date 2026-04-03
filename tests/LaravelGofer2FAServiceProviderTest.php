<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\Laravel\Facades\Gofer2FA as Gofer2FAFacade;
use DPRMC\Gofer2FA\Laravel\Gofer2FAServiceProvider;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class LaravelGofer2FAServiceProviderTest extends TestCase {
    protected function tearDown(): void {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication( NULL );
        Container::setInstance( NULL );

        parent::tearDown();
    }

    public function testProviderRegistersMailboxClientAndGoferService(): void {
        $app = $this->makeContainer();
        $provider = new Gofer2FAServiceProvider( $app );

        $provider->register();

        $mailbox = $app->make( MailboxClientInterface::class );
        $gofer = $app->make( Gofer2FA::class );

        $this->assertInstanceOf( MailboxClientInterface::class, $mailbox );
        $this->assertInstanceOf( Gofer2FA::class, $gofer );
        $this->assertArrayHasKey( 'costar', $gofer->sites() );
    }

    public function testFacadeResolvesContainerAlias(): void {
        $app = $this->makeContainer();
        $provider = new Gofer2FAServiceProvider( $app );

        $provider->register();
        Facade::setFacadeApplication( $app );

        $code = Gofer2FAFacade::fetchCode( 'costar', new DateTimeImmutable( '2026-04-03T00:00:00+00:00' ) );

        $this->assertNotNull( $code );
        $this->assertSame( '132584', $code->code() );
    }

    private function makeContainer(): Container {
        $app = new Container();
        Container::setInstance( $app );

        $config = new Repository( [
            'gofer2fa' => [
                'default_sites' => FALSE,
                'sites' => [
                    ForwardedCostarChallengeSite::class,
                ],
                'mailbox' => [
                    'driver' => 'callback',
                    'resolver' => static fn(): array => [
                        [
                            'id' => 'laravel-message-1',
                            'from_address' => 'forwarder@example.com',
                            'to_address' => 'user+costar@example.com',
                            'subject' => 'Fwd: CoStar access code',
                            'text_body' => 'See attachment.',
                            'received_at' => '2026-04-03T00:05:00+00:00',
                            'attachments' => [
                                [
                                    'filename' => 'costar-code.txt',
                                    'content_type' => 'text/plain',
                                    'content' => 'Your one-time CoStar access code is 132584.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ] );

        $app->instance( 'config', $config );

        return $app;
    }
}
