<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Integration;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\ValueObjects\TwoFactorCode;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @group integration
 */
class Office365MailboxIntegrationTest extends TestCase {
    public function testItCanReadARealOffice365Mailbox(): void {
        if ( !$this->isEnabled() ) {
            $this->markTestSkipped( 'Office 365 integration test is disabled. Set GOFER_O365_TEST_ENABLED=true to run it.' );
        }

        $bootstrapFile = getenv( 'GOFER_O365_BOOTSTRAP_FILE' ) ?: __DIR__ . '/../Support/office365-bootstrap.local.php';

        if ( !is_file( $bootstrapFile ) ) {
            $this->markTestSkipped( sprintf(
                'Office 365 bootstrap file not found at %s. Copy tests/Support/office365-bootstrap.example.php and point GOFER_O365_BOOTSTRAP_FILE to it.',
                $bootstrapFile
            ) );
        }

        $bootstrap = require $bootstrapFile;

        if ( !is_callable( $bootstrap ) ) {
            throw new RuntimeException( 'Office 365 bootstrap file must return a callable.' );
        }

        $definition = $bootstrap();
        $gofer = $this->buildGofer( $definition );
        $siteKey = getenv( 'GOFER_O365_SITE_KEY' ) ?: 'microsoft';
        $timeout = max( 1, (int) ( getenv( 'GOFER_O365_TIMEOUT' ) ?: 30 ) );
        $pollInterval = max( 1, (int) ( getenv( 'GOFER_O365_POLL_INTERVAL' ) ?: 5 ) );
        $since = $this->parseSince( '2026-04-02' );

        $code = $gofer->waitForCode( $siteKey, $timeout, $pollInterval, $since );

        $this->assertInstanceOf(
            TwoFactorCode::class,
            $code,
            sprintf( 'No 2FA code was found for site "%s" in the configured Office 365 mailbox.', $siteKey )
        );
    }

    /**
     * @param Gofer2FA|MailboxClientInterface|array<string, mixed> $definition
     */
    private function buildGofer( $definition ): Gofer2FA {
        if ( $definition instanceof Gofer2FA ) {
            return $definition;
        }

        if ( $definition instanceof MailboxClientInterface ) {
            return Gofer2FA::withDefaultSites( $definition );
        }

        if ( !is_array( $definition ) ) {
            throw new RuntimeException( 'Office 365 bootstrap callable must return a Gofer2FA instance, a MailboxClientInterface, or a config array.' );
        }

        $mailboxClient = $definition['mailbox_client'] ?? NULL;

        if ( !$mailboxClient instanceof MailboxClientInterface ) {
            throw new RuntimeException( 'Integration bootstrap config must contain a "mailbox_client" that implements MailboxClientInterface.' );
        }

        $gofer = isset( $definition['default_sites'] ) && $definition['default_sites'] === FALSE
            ? new Gofer2FA( $mailboxClient )
            : Gofer2FA::withDefaultSites( $mailboxClient );

        foreach ( $definition['sites'] ?? [] as $site ) {
            if ( !$site instanceof ChallengeSiteInterface ) {
                throw new RuntimeException( 'Every custom site in the integration bootstrap config must implement ChallengeSiteInterface.' );
            }

            $gofer->registerSite( $site );
        }

        if ( isset( $definition['debug'] ) ) {
            $gofer->setDebug( (bool) $definition['debug'] );
        }

        return $gofer;
    }

    private function isEnabled(): bool {
        return filter_var( getenv( 'GOFER_O365_TEST_ENABLED' ) ?: 'false', FILTER_VALIDATE_BOOLEAN );
    }

    private function parseSince( ?string $since ): ?DateTimeImmutable {
        if ( $since === NULL || trim( $since ) === '' ) {
            return NULL;
        }

        return new DateTimeImmutable( $since );
    }
}
