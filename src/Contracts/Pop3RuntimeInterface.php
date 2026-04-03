<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

/**
 * Runtime abstraction for POP3 operations.
 *
 * `Pop3MailboxClient` uses this interface so the production client can talk to a real POP3 server while
 * tests can supply a deterministic fake implementation.
 */
interface Pop3RuntimeInterface {
    /**
     * Open a POP3 connection.
     *
     * @return mixed
     */
    public function open( string $host, int $port, bool $useTls, bool $useStartTls = FALSE, int $timeout = 30 );

    /**
     * Authenticate the POP3 session.
     *
     * @param mixed $connection
     */
    public function authenticate( $connection, string $username, string $password ): void;

    /**
     * List messages available in the mailbox.
     *
     * @param mixed $connection
     *
     * @return array<int, array{number:int,size:int}>
     */
    public function listMessages( $connection ): array;

    /**
     * Retrieve the full raw RFC 822 message for a POP3 message number.
     *
     * @param mixed $connection
     */
    public function retrieveMessage( $connection, int $messageNumber ): string;

    /**
     * Close the POP3 connection.
     *
     * @param mixed $connection
     */
    public function close( $connection ): void;
}
