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
- Starter site implementations for GitHub, Google, Microsoft, and Okta.
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
            'subject' => $message['subject'] ?? null,
            'text_body' => $message['text'] ?? null,
            'html_body' => $message['html'] ?? null,
            'received_at' => $message['received_at'] ?? null,
        ];
    });
});

$gofer = Gofer2FA::withDefaultSites($mailbox);

$code = $gofer->waitForCode('microsoft', 90, 5);
```

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
