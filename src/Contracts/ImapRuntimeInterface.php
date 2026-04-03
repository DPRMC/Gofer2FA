<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Contracts;

/**
 * Runtime abstraction for native IMAP operations.
 *
 * `ImapMailboxClient` uses this interface so the library can rely on PHP's IMAP extension in production
 * while PHPUnit uses a fake runtime with deterministic mailbox payloads.
 */
interface ImapRuntimeInterface {
    /**
     * Open an IMAP mailbox stream.
     *
     * @param array<string, mixed> $parameters
     *
     * @return mixed
     */
    public function open(
        string $mailbox,
        string $username,
        string $password,
        int    $options = 0,
        int    $retries = 0,
        array  $parameters = []
    );

    /**
     * Close an IMAP mailbox stream.
     *
     * @param mixed $stream
     */
    public function close( $stream ): void;

    /**
     * Search the mailbox and return message numbers.
     *
     * @param mixed $stream
     *
     * @return array<int, int>
     */
    public function search( $stream, string $criteria ): array;

    /**
     * Fetch overview metadata for the supplied message sequence.
     *
     * @param mixed $stream
     *
     * @return array<int, object>
     */
    public function fetchOverview( $stream, string $sequence, int $options = 0 ): array;

    /**
     * Fetch the MIME structure for a message.
     *
     * @param mixed $stream
     *
     * @return object|null
     */
    public function fetchStructure( $stream, int $messageNumber );

    /**
     * Fetch a body section from a message.
     *
     * @param mixed $stream
     */
    public function fetchBody( $stream, int $messageNumber, string $section, int $options = 0 ): string;
}
