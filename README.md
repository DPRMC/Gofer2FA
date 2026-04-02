# Gofer2FA

A PHP library for checking an email inbox for 2FA codes.

## What it provides

- A main `Gofer2FA` service for polling an inbox for challenge codes.
- A mailbox client contract so the package can work with any Laravel-side mail transport or inbox reader.
- A site parser contract and a `Sites/` directory for company-specific sender matching and code extraction.
- Starter site implementations for GitHub, Google, Microsoft, and Okta.
- A callback adapter so existing application services can be wrapped quickly.

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
