<?php

return [
    'default_sites' => true,

    /*
    |--------------------------------------------------------------------------
    | Additional Site Parsers
    |--------------------------------------------------------------------------
    |
    | Each entry may be a class-string or an array with a "class" key and
    | optional "arguments". These are resolved through the Laravel container.
    |
    */
    'sites' => [
        // \App\Gofer2FA\Sites\MyCustomChallengeSite::class,
        // [
        //     'class' => \App\Gofer2FA\Sites\MyParameterizedSite::class,
        //     'arguments' => [ 'example' ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailbox Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers:
    | office365_graph, imap, gmail_api, ses_s3, pop3, callback
    |
    */
    'mailbox' => [
        'driver' => env( 'GOFER2FA_MAILBOX_DRIVER', 'office365_graph' ),

        'tenant' => env( 'OFFICE365MAIL_TENANT' ),
        'client_id' => env( 'OFFICE365MAIL_CLIENT_ID' ),
        'client_secret' => env( 'OFFICE365MAIL_CLIENT_SECRET' ),
        'mailbox_user' => env( 'GOFER2FA_MAILBOX_USER' ),
        'mail_folder' => env( 'GOFER2FA_MAIL_FOLDER', 'inbox' ),
        'graph_base_url' => env( 'GOFER2FA_GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0' ),

        'mailbox' => env( 'GOFER2FA_IMAP_MAILBOX' ),
        'username' => env( 'GOFER2FA_IMAP_USERNAME' ),
        'password' => env( 'GOFER2FA_IMAP_PASSWORD' ),
        'options' => (int) env( 'GOFER2FA_IMAP_OPTIONS', 0 ),
        'retries' => (int) env( 'GOFER2FA_IMAP_RETRIES', 0 ),
        'parameters' => [],

        'user_id' => env( 'GOFER2FA_GMAIL_USER_ID', 'me' ),
        'refresh_token' => env( 'GOFER2FA_GMAIL_REFRESH_TOKEN' ),
        'base_url' => env( 'GOFER2FA_GMAIL_BASE_URL', 'https://gmail.googleapis.com/gmail/v1' ),

        'access_key_id' => env( 'AWS_ACCESS_KEY_ID' ),
        'secret_access_key' => env( 'AWS_SECRET_ACCESS_KEY' ),
        'session_token' => env( 'AWS_SESSION_TOKEN' ),
        'region' => env( 'AWS_DEFAULT_REGION' ),
        'bucket' => env( 'GOFER2FA_SES_BUCKET' ),
        'prefix' => env( 'GOFER2FA_SES_PREFIX', '' ),

        'host' => env( 'GOFER2FA_POP3_HOST' ),
        'port' => (int) env( 'GOFER2FA_POP3_PORT', 995 ),
        'use_tls' => (bool) env( 'GOFER2FA_POP3_USE_TLS', true ),
        'use_starttls' => (bool) env( 'GOFER2FA_POP3_USE_STARTTLS', false ),
        'timeout' => (int) env( 'GOFER2FA_POP3_TIMEOUT', 30 ),

        'resolver' => null,
        'deleter' => null,
    ],
];
