<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\MailboxClients;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxMessage;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\Contracts\Pop3RuntimeInterface;
use DPRMC\Gofer2FA\Mime\MimeMessageParser;
use DPRMC\Gofer2FA\Pop3\NativePop3Runtime;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

/**
 * Mailbox client backed by a POP3 inbox.
 *
 * POP3 exposes raw RFC 822 messages rather than a structured message API, so this client retrieves the
 * newest messages, parses them through `MimeMessageParser`, and yields normalized mailbox message objects.
 */
class Pop3MailboxClient implements MailboxClientInterface {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private bool $useTls;
    private bool $useStartTls;
    private int $timeout;
    private Pop3RuntimeInterface $runtime;
    private MimeMessageParser $parser;

    /**
     * Create a POP3-backed mailbox client.
     */
    public function __construct(
        string                 $host,
        int                    $port,
        string                 $username,
        string                 $password,
        bool                   $useTls = TRUE,
        int                    $timeout = 30,
        ?Pop3RuntimeInterface  $runtime = NULL,
        ?MimeMessageParser     $parser = NULL,
        bool                   $useStartTls = FALSE
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->useTls = $useTls;
        $this->useStartTls = $useStartTls;
        $this->timeout = $timeout;
        $this->runtime = $runtime ?: new NativePop3Runtime();
        $this->parser = $parser ?: new MimeMessageParser();
    }

    /**
     * Create a POP3 mailbox client configured for explicit STARTTLS on a plaintext port.
     */
    public static function withStartTls(
        string                 $host,
        int                    $port,
        string                 $username,
        string                 $password,
        int                    $timeout = 30,
        ?Pop3RuntimeInterface  $runtime = NULL,
        ?MimeMessageParser     $parser = NULL
    ): self {
        return new self( $host, $port, $username, $password, FALSE, $timeout, $runtime, $parser, TRUE );
    }

    /**
     * Fetch candidate mailbox messages from POP3 and normalize them for Gofer.
     *
     * @return iterable<MailboxMessageInterface>
     */
    public function findMessages( MessageQuery $query ): iterable {
        $connection = $this->runtime->open( $this->host, $this->port, $this->useTls, $this->useStartTls, $this->timeout );

        try {
            $this->runtime->authenticate( $connection, $this->username, $this->password );

            foreach ( $this->normalizedMessages( $connection, $query ) as $message ) {
                yield new ArrayMailboxMessage( $message );
            }
        } finally {
            $this->runtime->close( $connection );
        }
    }

    /**
     * @param mixed $connection
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizedMessages( $connection, MessageQuery $query ): array {
        $messages = $this->runtime->listMessages( $connection );
        usort( $messages, static fn( array $left, array $right ): int => $right['number'] <=> $left['number'] );
        $normalized = [];

        foreach ( $messages as $message ) {
            $messageNumber = (int) $message['number'];
            $parsed = $this->parser->parse(
                $this->runtime->retrieveMessage( $connection, $messageNumber ),
                (string) $messageNumber
            );

            if ( !$this->messageMatchesQuery( $parsed, $query ) ) {
                continue;
            }

            $normalized[] = $parsed;

            if ( count( $normalized ) >= $query->limit() ) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function messageMatchesQuery( array $message, MessageQuery $query ): bool {
        $fromAddress = isset( $message['from_address'] ) ? (string) $message['from_address'] : '';
        $toAddress = isset( $message['to_address'] ) ? (string) $message['to_address'] : '';
        $receivedAt = isset( $message['received_at'] ) && is_string( $message['received_at'] ) && trim( $message['received_at'] ) !== ''
            ? new DateTimeImmutable( $message['received_at'] )
            : NULL;

        if ( $query->fromAddresses() !== [] && !in_array( strtolower( $fromAddress ), $query->fromAddresses(), TRUE ) ) {
            return FALSE;
        }

        if ( $query->toAddresses() !== [] && !in_array( strtolower( $toAddress ), $query->toAddresses(), TRUE ) ) {
            return FALSE;
        }

        if ( $query->since() !== NULL && $receivedAt !== NULL && $receivedAt < $query->since() ) {
            return FALSE;
        }

        return TRUE;
    }
}
