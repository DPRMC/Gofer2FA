<?php

declare(strict_types=1);

use DPRMC\Gofer2FA\MailboxClients\GmailApiMailboxClient;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;

/*
 * Required environment variables:
 * GOFER_GMAIL_CLIENT_ID=your-google-oauth-client-id
 * GOFER_GMAIL_CLIENT_SECRET=your-google-oauth-client-secret
 * GOFER_GMAIL_REFRESH_TOKEN=your-google-refresh-token
 *
 * Optional environment variables:
 * GOFER_GMAIL_USER_ID=me
 * GOFER_GMAIL_BASE_URL=https://gmail.googleapis.com/gmail/v1
 * GOFER_GMAIL_DEBUG=true
 * GOFER_GMAIL_TEST_ENABLED=true
 * GOFER_GMAIL_BOOTSTRAP_FILE=tests/Support/gmail-bootstrap.local.php
 * GOFER_GMAIL_SITE_KEY=costar
 * GOFER_GMAIL_TIMEOUT=60
 * GOFER_GMAIL_POLL_INTERVAL=5
 * GOFER_GMAIL_SINCE=2026-04-03T12:00:00+00:00
 */
return static function (): array {
    $mailbox = new GmailApiMailboxClient(
        getenv( 'GOFER_GMAIL_USER_ID' ) ?: 'me',
        goferGmailEnv( 'GOFER_GMAIL_CLIENT_ID' ),
        goferGmailEnv( 'GOFER_GMAIL_CLIENT_SECRET' ),
        goferGmailEnv( 'GOFER_GMAIL_REFRESH_TOKEN' ),
        getenv( 'GOFER_GMAIL_BASE_URL' ) ?: 'https://gmail.googleapis.com/gmail/v1'
    );

    return [
        'mailbox_client' => $mailbox,
        'default_sites' => TRUE,
        'sites' => [
            new ForwardedCostarChallengeSite(),
        ],
        'debug' => filter_var( getenv( 'GOFER_GMAIL_DEBUG' ) ?: 'true', FILTER_VALIDATE_BOOLEAN ),
    ];
};

function goferGmailEnv( string $name ): string {
    $value = getenv( $name );

    if ( !is_string( $value ) || trim( $value ) === '' ) {
        throw new RuntimeException( sprintf( 'Environment variable %s is required for Gmail integration.', $name ) );
    }

    return trim( $value );
}
