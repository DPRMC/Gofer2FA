<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Laravel;

use DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\MailboxClientFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Optional Laravel service provider for Gofer2FA.
 *
 * This provider publishes package config, builds a mailbox client from config through `MailboxClientFactory`,
 * and binds a ready-to-use `Gofer2FA` instance into the Laravel service container.
 */
class Gofer2FAServiceProvider extends ServiceProvider {
    /**
     * Publish config and make it available to Laravel applications.
     */
    public function boot(): void {
        $this->publishes( [
            __DIR__ . '/../../config/gofer2fa.php' => config_path( 'gofer2fa.php' ),
        ], 'gofer2fa-config' );
    }

    /**
     * Register the mailbox factory, mailbox client, and Gofer2FA service bindings.
     */
    public function register(): void {
        $this->mergeConfigFrom( __DIR__ . '/../../config/gofer2fa.php', 'gofer2fa' );

        $this->app->singleton( MailboxClientFactory::class, static fn(): MailboxClientFactory => new MailboxClientFactory() );

        $this->app->singleton( MailboxClientInterface::class, function ( Container $app ): MailboxClientInterface {
            /** @var MailboxClientFactory $factory */
            $factory = $app->make( MailboxClientFactory::class );

            return $factory->make( (array) $app['config']->get( 'gofer2fa.mailbox', [] ) );
        } );

        $this->app->singleton( Gofer2FA::class, function ( Container $app ): Gofer2FA {
            $mailbox = $app->make( MailboxClientInterface::class );
            $gofer = (bool) $app['config']->get( 'gofer2fa.default_sites', true )
                ? Gofer2FA::withDefaultSites( $mailbox )
                : new Gofer2FA( $mailbox );

            foreach ( (array) $app['config']->get( 'gofer2fa.sites', [] ) as $siteDefinition ) {
                $site = $this->resolveSiteDefinition( $app, $siteDefinition );
                $gofer->registerSite( $site );
            }

            return $gofer;
        } );

        $this->app->alias( Gofer2FA::class, 'gofer2fa' );
    }

    /**
     * @param mixed $siteDefinition
     */
    private function resolveSiteDefinition( Container $app, $siteDefinition ): ChallengeSiteInterface {
        if ( is_string( $siteDefinition ) ) {
            $site = $app->make( $siteDefinition );
        } elseif ( is_array( $siteDefinition ) && isset( $siteDefinition['class'] ) ) {
            $arguments = is_array( $siteDefinition['arguments'] ?? NULL ) ? $siteDefinition['arguments'] : [];
            $site = $app->makeWith( (string) $siteDefinition['class'], $arguments );
        } else {
            throw new InvalidArgumentException( 'Each configured gofer2fa site must be a class-string or an array with a class key.' );
        }

        if ( !$site instanceof ChallengeSiteInterface ) {
            throw new InvalidArgumentException( 'Configured gofer2fa site classes must implement ChallengeSiteInterface.' );
        }

        return $site;
    }
}
