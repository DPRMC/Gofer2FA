# Gofer2FA

[![Tests](https://github.com/DPRMC/Gofer2FA/actions/workflows/tests.yml/badge.svg)](https://github.com/DPRMC/Gofer2FA/actions/workflows/tests.yml)
[![Codecov](https://codecov.io/gh/DPRMC/Gofer2FA/graph/badge.svg?branch=main)](https://codecov.io/gh/DPRMC/Gofer2FA)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](composer.json)
[![PHPUnit 9.5+](https://img.shields.io/badge/PHPUnit-9.5%2B-0F80C1?logo=phpunit&logoColor=white)](phpunit.xml.dist)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A PHP library for checking an email inbox for 2FA codes.

## What it provides

- A main `Gofer2FA` service for polling an inbox for challenge codes.
- A mailbox client contract so the package can work with any Laravel-side mail transport or inbox reader.
- A site parser contract and a `Sites/` directory for company-specific sender matching and code extraction.
- Forwarded challenge site support for workflows that identify the site by the plus-address tag in the `To` address instead of the `From` address.
- Attachment-aware parsing when the mailbox client provides decoded attachment text content.
- Starter site implementations for forwarded CoStar, GitHub, Google, Microsoft, and Okta.
- A callback adapter so existing application services can be wrapped quickly.

## CI and Coverage

- GitHub Actions runs the test suite on PHP `8.0` and `8.3`.
- Codecov uploads a Clover coverage report generated on the PHP `8.3` job.
- If Codecov requires a token for this repository, add `CODECOV_TOKEN` to the repository secrets in GitHub.

## Basic usage

```php
use DPRMC\Gofer2FA\Adapters\CallbackMailboxClient;
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

$transport = app(Office365MailTransport::class);

$mailbox = new CallbackMailboxClient(function (MessageQuery $query) use ($transport): iterable {
    return collect($transport->findMessages([
        'from' => $query->fromAddresses(),
        'since' => $query->since() ? $query->since()->format(DATE_ATOM) : null,
        'limit' => $query->limit(),
    ]))->map(function (array $message): array {
        return [
            'id' => $message['id'] ?? null,
            'from_address' => $message['from'] ?? null,
            'to_address' => $message['to'] ?? null,
            'subject' => $message['subject'] ?? null,
            'text_body' => $message['text'] ?? null,
            'html_body' => $message['html'] ?? null,
            'received_at' => $message['received_at'] ?? null,
            'attachments' => $message['attachments'] ?? [],
        ];
    });
});

$gofer = Gofer2FA::withDefaultSites($mailbox);

$code = $gofer->waitForCode('microsoft', 90, 5);
```

`ArrayMailboxMessage` prefers `to_address`, but it can also infer the recipient from common mailbox keys such as `to`, `recipient`, or `recipients[0]`.

## Standard mailbox clients

Gofer now includes first-class mailbox clients that own provider-specific normalization inside the library. That removes the need to build `$normalizedMessage` arrays in application code.

### Microsoft 365 / Graph

```php
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\MailboxClients\Office365GraphMailboxClient;

$mailbox = new Office365GraphMailboxClient(
    env('OFFICE365MAIL_TENANT'),
    env('OFFICE365MAIL_CLIENT_ID'),
    env('OFFICE365MAIL_CLIENT_SECRET'),
    'fims@deerparkrd.com',
    'inbox'
);

$gofer = Gofer2FA::withDefaultSites($mailbox);

$code = $gofer->fetchCode('costar');
```

### IMAP

```php
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\MailboxClients\ImapMailboxClient;

$mailbox = new ImapMailboxClient(
    '{imap.example.com:993/imap/ssl}INBOX',
    'user@example.com',
    'secret'
);

$gofer = Gofer2FA::withDefaultSites($mailbox);
```

### Gmail API

```php
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\MailboxClients\GmailApiMailboxClient;

$mailbox = new GmailApiMailboxClient(
    'me',
    env('GOFER_GMAIL_CLIENT_ID'),
    env('GOFER_GMAIL_CLIENT_SECRET'),
    env('GOFER_GMAIL_REFRESH_TOKEN')
);

$gofer = Gofer2FA::withDefaultSites($mailbox);
```

### SES / S3

```php
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\MailboxClients\SesS3MailboxClient;

$mailbox = new SesS3MailboxClient(
    env('AWS_ACCESS_KEY_ID'),
    env('AWS_SECRET_ACCESS_KEY'),
    env('AWS_DEFAULT_REGION'),
    'my-inbound-mail-bucket',
    'ses/inbound'
);

$gofer = Gofer2FA::withDefaultSites($mailbox);
```

For AWS temporary credentials, pass the session token:

```php
$mailbox = new SesS3MailboxClient(
    env('AWS_ACCESS_KEY_ID'),
    env('AWS_SECRET_ACCESS_KEY'),
    env('AWS_DEFAULT_REGION'),
    'my-inbound-mail-bucket',
    'ses/inbound',
    env('AWS_SESSION_TOKEN')
);
```

### POP3

```php
use DPRMC\Gofer2FA\Gofer2FA;
use DPRMC\Gofer2FA\MailboxClients\Pop3MailboxClient;

$mailbox = new Pop3MailboxClient(
    'pop.example.com',
    995,
    'user@example.com',
    'secret'
);

$gofer = Gofer2FA::withDefaultSites($mailbox);
```

For explicit POP3 STARTTLS on port `110`:

```php
use DPRMC\Gofer2FA\MailboxClients\Pop3MailboxClient;

$mailbox = Pop3MailboxClient::withStartTls(
    'pop.example.com',
    110,
    'user@example.com',
    'secret'
);
```

`Office365GraphMailboxClient`, `GmailApiMailboxClient`, `SesS3MailboxClient`, `ImapMailboxClient`, and `Pop3MailboxClient` all return standardized `MailboxMessageInterface` objects. `SesS3MailboxClient` and `Pop3MailboxClient` normalize raw MIME messages through the library's MIME parser. `ImapMailboxClient` requires PHP's IMAP extension at runtime.

## Laravel integration

Gofer includes an optional Laravel service provider and package config:

- service provider: `DPRMC\Gofer2FA\Laravel\Gofer2FAServiceProvider`
- config file: `config/gofer2fa.php`

Publish the config in your Laravel app:

```bash
php artisan vendor:publish --tag=gofer2fa-config
```

Example config:

```php
'mailbox' => [
    'driver' => 'office365_graph',
    'tenant' => env('OFFICE365MAIL_TENANT'),
    'client_id' => env('OFFICE365MAIL_CLIENT_ID'),
    'client_secret' => env('OFFICE365MAIL_CLIENT_SECRET'),
    'mailbox_user' => env('GOFER2FA_MAILBOX_USER'),
    'mail_folder' => env('GOFER2FA_MAIL_FOLDER', 'inbox'),
],
```

Then resolve Gofer from the container:

```php
use DPRMC\Gofer2FA\Gofer2FA;

$gofer = app(Gofer2FA::class);
```

Or use the Laravel facade alias:

```php
$code = \Gofer2FA::fetchCode('costar');
```

Custom site parsers can be appended through the `sites` array in `config/gofer2fa.php`.

## Debugging

```php
$gofer = Gofer2FA::withDefaultSites($mailbox)
    ->setDebug(true);

$code = $gofer->fetchCode('microsoft');
```

When debug mode is enabled, Gofer writes useful mailbox-check information to the console:

- the parser class being used
- the mailbox filter criteria
- a table showing the messages returned by the mailbox client for each check

If you want Gofer to delete the matched email after successfully reading the code, pass `true` as the final argument to `fetchCode()` or `waitForCode()`:

```php
$code = $gofer->fetchCode('costar', null, 25, true);
$code = $gofer->waitForCode('costar', 90, 5, null, 25, true);
```

Deletion only works when the selected mailbox client implements Gofer's deletable-mailbox contract. The built-in Office 365, Gmail, IMAP, POP3, and SES/S3 clients support it.

## Mailbox Inspection

Use `printMailboxPage()` when you want to inspect the raw first page of messages visible to the mailbox client without involving a site parser:

```php
$gofer->printMailboxPage(
    new DateTimeImmutable('2026-04-02T00:00:00+00:00'),
    25
);
```

Use `printMailboxPageForSite()` when you want the same table output, but with the selected site's parser, mailbox filters, `MATCH` evaluation, and extracted `CODE` column applied:

```php
$gofer->printMailboxPageForSite(
    'costar',
    new DateTimeImmutable('2026-04-02T00:00:00+00:00'),
    25
);
```

## Office 365 Integration Testing

Gofer includes an opt-in PHPUnit integration scaffold for development against a real Office 365 mailbox.

- Unit tests remain the default: `composer test`
- Run the Office 365 integration test explicitly: `composer test-integration`
- The integration test is skipped unless `GOFER_O365_TEST_ENABLED=true`

Setup flow:

1. Copy `tests/Support/office365-bootstrap.example.php` to `tests/Support/office365-bootstrap.local.php`
2. Copy `.env.gofer-o365.example` to a local shell file or export the same variables directly
3. Fill in the Azure tenant, client, secret, and mailbox values
4. Source the file and run the integration test:

```bash
set -a
source .env.gofer-o365.example
set +a
composer test-integration
```

The tracked example file is:

- `.env.gofer-o365.example`

The local bootstrap now instantiates `Office365GraphMailboxClient` directly, so the integration test uses the same normalization path as production code. It does not need further code changes unless your tenant requires a different authentication flow.

The bootstrap file may return:

- a ready-to-use `Gofer2FA` instance
- a `MailboxClientInterface`
- or a config array containing:
  - `mailbox_client`
  - optional `sites`
  - optional `default_sites`
  - optional `debug`

## Gmail Integration Testing

Gofer also includes an opt-in PHPUnit integration scaffold for development against a real Gmail mailbox.

- Run the Gmail integration test explicitly: `vendor/bin/phpunit --group integration tests/Integration/GmailMailboxIntegrationTest.php`
- The Gmail integration test is skipped unless `GOFER_GMAIL_TEST_ENABLED=true`

Setup flow:

1. Copy `tests/Support/gmail-bootstrap.example.php` to `tests/Support/gmail-bootstrap.local.php`
2. Copy `.env.gofer-gmail.example` to a local shell file or export the same variables directly
3. Fill in the Google OAuth client and refresh token values
4. Source the file and run the Gmail integration test:

```bash
set -a
source .env.gofer-gmail.example
set +a
vendor/bin/phpunit --group integration tests/Integration/GmailMailboxIntegrationTest.php
```

The tracked example file is:

- `.env.gofer-gmail.example`

The local Gmail bootstrap instantiates `GmailApiMailboxClient` directly, so Gmail integration testing uses the same normalization path as production code.

## Custom site parser

```php
use DPRMC\Gofer2FA\Sites\CustomRegexChallengeSite;

$gofer->registerSite(new CustomRegexChallengeSite(
    'acme',
    ['login@acme.test'],
    [
        '/code[^0-9]*(?<code>\\d{6})/i',
        '/\\b(?<code>\\d{6})\\b/',
    ]
));
```
