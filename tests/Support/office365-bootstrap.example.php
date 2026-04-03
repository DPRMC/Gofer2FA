<?php

declare(strict_types=1);

use DPRMC\Gofer2FA\Adapters\CallbackMailboxClient;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

return static function (): array {
    /*
     * Copy this file to tests/Support/office365-bootstrap.local.php and replace the
     * placeholder callback below with your real Office 365 mailbox access logic.
     *
     * The callback must return an iterable of arrays or MailboxMessageInterface objects.
     * If you return arrays, the fields Gofer understands include:
     * - id
     * - from_address or from
     * - to_address or to or recipient or recipients
     * - subject
     * - text_body
     * - html_body
     * - received_at
     * - attachments
     *
     * Example environment variables for running the integration test:
     * GOFER_O365_TEST_ENABLED=true
     * GOFER_O365_BOOTSTRAP_FILE=tests/Support/office365-bootstrap.local.php
     * GOFER_O365_SITE_KEY=costar
     * GOFER_O365_TIMEOUT=60
     * GOFER_O365_POLL_INTERVAL=5
     * GOFER_O365_SINCE=2026-04-03T12:00:00+00:00
     */
    $mailbox = new CallbackMailboxClient( static function ( MessageQuery $query ): iterable {
        throw new RuntimeException(
            'Implement your Office 365 mailbox lookup in tests/Support/office365-bootstrap.local.php.'
        );
    } );

    return [
        'mailbox_client' => $mailbox,
        'default_sites' => TRUE,
        'sites' => [
            // Register any forwarded/tag-based sites you need for the target mailbox.
            new ForwardedCostarChallengeSite(),
        ],
        'debug' => TRUE,
    ];
};
