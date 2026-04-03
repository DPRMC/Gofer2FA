<?php

declare(strict_types=1);

use DPRMC\Gofer2FA\MailboxClients\Office365GraphMailboxClient;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;

/*
 * Required environment variables:
 * GOFER_O365_TENANT_ID=your-tenant-id-guid
 * GOFER_O365_CLIENT_ID=your-app-registration-client-id-guid
 * GOFER_O365_CLIENT_SECRET=your-app-registration-client-secret
 * GOFER_O365_MAILBOX_USER=shared-mailbox@example.com
 *
 * Accepted aliases from existing app config:
 * OFFICE365MAIL_TENANT=your-tenant-id-guid
 * OFFICE365MAIL_CLIENT_ID=your-app-registration-client-id-guid
 * OFFICE365MAIL_CLIENT_SECRET=your-app-registration-client-secret
 *
 * Optional environment variables:
 * GOFER_O365_MAIL_FOLDER=inbox
 * GOFER_O365_GRAPH_BASE_URL=https://graph.microsoft.com/v1.0
 * GOFER_O365_DEBUG=true
 * GOFER_O365_TEST_ENABLED=true
 * GOFER_O365_BOOTSTRAP_FILE=tests/Support/office365-bootstrap.local.php
 * GOFER_O365_SITE_KEY=costar
 * GOFER_O365_TIMEOUT=60
 * GOFER_O365_POLL_INTERVAL=5
 * GOFER_O365_SINCE=2026-04-02T12:00:00+00:00
 */
return static function (): array {
    $mailbox = new Office365GraphMailboxClient(
        goferOffice365EnvAny( [ 'GOFER_O365_TENANT_ID', 'OFFICE365MAIL_TENANT' ] ),
        goferOffice365EnvAny( [ 'GOFER_O365_CLIENT_ID', 'OFFICE365MAIL_CLIENT_ID' ] ),
        goferOffice365EnvAny( [ 'GOFER_O365_CLIENT_SECRET', 'OFFICE365MAIL_CLIENT_SECRET' ] ),
        goferOffice365Env( 'GOFER_O365_MAILBOX_USER' ),
        getenv( 'GOFER_O365_MAIL_FOLDER' ) ?: 'inbox',
        getenv( 'GOFER_O365_GRAPH_BASE_URL' ) ?: 'https://graph.microsoft.com/v1.0'
    );

    return [
        'mailbox_client' => $mailbox,
        'default_sites' => TRUE,
        'sites' => [
            new ForwardedCostarChallengeSite(),
        ],
        'debug' => filter_var( getenv( 'GOFER_O365_DEBUG' ) ?: 'true', FILTER_VALIDATE_BOOLEAN ),
    ];
};

function goferOffice365Env( string $name ): string {
    $value = getenv( $name );

    if ( !is_string( $value ) || trim( $value ) === '' ) {
        throw new RuntimeException( sprintf( 'Environment variable %s is required for Office 365 integration.', $name ) );
    }

    return trim( $value );
}

/**
 * @param array<int, string> $names
 */
function goferOffice365EnvAny( array $names ): string {
    foreach ( $names as $name ) {
        $value = getenv( $name );

        if ( is_string( $value ) && trim( $value ) !== '' ) {
            return trim( $value );
        }
    }

    throw new RuntimeException(
        sprintf(
            'One of the following environment variables is required for Office 365 integration: %s',
            implode( ', ', $names )
        )
    );
}
