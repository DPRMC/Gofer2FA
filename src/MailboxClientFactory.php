<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA;

use DPRMC\Gofer2FA\Adapters\CallbackMailboxClient;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\MailboxClients\GmailApiMailboxClient;
use DPRMC\Gofer2FA\MailboxClients\ImapMailboxClient;
use DPRMC\Gofer2FA\MailboxClients\Office365GraphMailboxClient;
use DPRMC\Gofer2FA\MailboxClients\Pop3MailboxClient;
use DPRMC\Gofer2FA\MailboxClients\SesS3MailboxClient;
use InvalidArgumentException;

/**
 * Factory for building mailbox clients from normalized configuration arrays.
 *
 * This allows Laravel config, test bootstrap code, or other host applications to select a mailbox driver
 * without reimplementing provider-specific constructor wiring or message normalization logic.
 */
class MailboxClientFactory {
    /**
     * Build a mailbox client from a config array.
     *
     * @param array<string, mixed> $config
     */
    public function make( array $config ): MailboxClientInterface {
        $driver = strtolower( trim( (string) ( $config['driver'] ?? '' ) ) );

        switch ( $driver ) {
            case 'office365':
            case 'office365_graph':
            case 'microsoft_graph':
                return new Office365GraphMailboxClient(
                    $this->requireString( $config, 'tenant' ),
                    $this->requireString( $config, 'client_id' ),
                    $this->requireString( $config, 'client_secret' ),
                    $this->requireString( $config, 'mailbox_user' ),
                    $this->stringValue( $config, 'mail_folder', 'inbox' ),
                    $this->stringValue( $config, 'graph_base_url', 'https://graph.microsoft.com/v1.0' )
                );

            case 'imap':
                return new ImapMailboxClient(
                    $this->requireString( $config, 'mailbox' ),
                    $this->requireString( $config, 'username' ),
                    $this->requireString( $config, 'password' ),
                    (int) ( $config['options'] ?? 0 ),
                    (int) ( $config['retries'] ?? 0 ),
                    is_array( $config['parameters'] ?? NULL ) ? $config['parameters'] : []
                );

            case 'gmail':
            case 'gmail_api':
                return new GmailApiMailboxClient(
                    $this->stringValue( $config, 'user_id', 'me' ),
                    $this->requireString( $config, 'client_id' ),
                    $this->requireString( $config, 'client_secret' ),
                    $this->requireString( $config, 'refresh_token' ),
                    $this->stringValue( $config, 'base_url', 'https://gmail.googleapis.com/gmail/v1' )
                );

            case 'ses':
            case 'ses_s3':
                return new SesS3MailboxClient(
                    $this->requireString( $config, 'access_key_id' ),
                    $this->requireString( $config, 'secret_access_key' ),
                    $this->requireString( $config, 'region' ),
                    $this->requireString( $config, 'bucket' ),
                    $this->stringValue( $config, 'prefix', '' ),
                    $this->nullableStringValue( $config, 'session_token' )
                );

            case 'pop3':
                return new Pop3MailboxClient(
                    $this->requireString( $config, 'host' ),
                    (int) ( $config['port'] ?? 995 ),
                    $this->requireString( $config, 'username' ),
                    $this->requireString( $config, 'password' ),
                    (bool) ( $config['use_tls'] ?? TRUE ),
                    (int) ( $config['timeout'] ?? 30 ),
                    NULL,
                    NULL,
                    (bool) ( $config['use_starttls'] ?? FALSE )
                );

            case 'callback':
                $resolver = $config['resolver'] ?? NULL;
                $deleter = $config['deleter'] ?? NULL;

                if ( !is_callable( $resolver ) ) {
                    throw new InvalidArgumentException( 'Callback mailbox config requires a callable resolver.' );
                }

                if ( $deleter !== NULL && !is_callable( $deleter ) ) {
                    throw new InvalidArgumentException( 'Callback mailbox config deleter must be callable when provided.' );
                }

                return new CallbackMailboxClient( $resolver, $deleter );
        }

        throw new InvalidArgumentException( sprintf( 'Unsupported mailbox driver "%s".', $driver ) );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requireString( array $config, string $key ): string {
        $value = $this->nullableStringValue( $config, $key );

        if ( $value === NULL ) {
            throw new InvalidArgumentException( sprintf( 'Mailbox config key "%s" is required.', $key ) );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function stringValue( array $config, string $key, string $default = '' ): string {
        return $this->nullableStringValue( $config, $key ) ?? $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function nullableStringValue( array $config, string $key ): ?string {
        if ( !array_key_exists( $key, $config ) ) {
            return NULL;
        }

        $value = trim( (string) $config[$key] );

        return $value !== '' ? $value : NULL;
    }
}
