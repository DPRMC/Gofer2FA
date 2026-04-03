<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Imap;

use DPRMC\Gofer2FA\Contracts\ImapRuntimeInterface;
use RuntimeException;

/**
 * Production IMAP runtime backed by PHP's native IMAP extension.
 *
 * This isolates direct calls to `imap_*` functions from the mailbox client so the rest of the codebase
 * can stay unit-testable and the failure mode is explicit when the extension is unavailable.
 */
class NativeImapRuntime implements ImapRuntimeInterface {
    /**
     * @param array<string, mixed> $parameters
     *
     * @return resource
     */
    public function open(
        string $mailbox,
        string $username,
        string $password,
        int    $options = 0,
        int    $retries = 0,
        array  $parameters = []
    ) {
        $this->assertExtensionLoaded();

        $stream = imap_open( $mailbox, $username, $password, $options, $retries, $parameters );

        if ( $stream === FALSE ) {
            throw new RuntimeException( 'Unable to open IMAP mailbox: ' . implode( '; ', imap_errors() ?: [] ) );
        }

        return $stream;
    }

    /**
     * @param resource $stream
     */
    public function close( $stream ): void {
        $this->assertExtensionLoaded();
        imap_close( $stream );
    }

    /**
     * @param resource $stream
     *
     * @return array<int, int>
     */
    public function search( $stream, string $criteria ): array {
        $this->assertExtensionLoaded();

        $results = imap_search( $stream, $criteria, SE_UID );

        if ( $results === FALSE ) {
            return [];
        }

        return array_map( 'intval', $results );
    }

    /**
     * @param resource $stream
     *
     * @return array<int, object>
     */
    public function fetchOverview( $stream, string $sequence, int $options = 0 ): array {
        $this->assertExtensionLoaded();

        $overview = imap_fetch_overview( $stream, $sequence, $options );

        return is_array( $overview ) ? $overview : [];
    }

    /**
     * @param resource $stream
     *
     * @return object|null
     */
    public function fetchStructure( $stream, int $messageNumber ) {
        $this->assertExtensionLoaded();
        $structure = imap_fetchstructure( $stream, $messageNumber, FT_UID );

        return is_object( $structure ) ? $structure : NULL;
    }

    /**
     * @param resource $stream
     */
    public function fetchBody( $stream, int $messageNumber, string $section, int $options = 0 ): string {
        $this->assertExtensionLoaded();
        $body = imap_fetchbody( $stream, $messageNumber, $section, $options );

        return is_string( $body ) ? $body : '';
    }

    /**
     * @param resource $stream
     */
    public function deleteMessage( $stream, int $messageNumber, int $options = 0 ): void {
        $this->assertExtensionLoaded();
        imap_delete( $stream, (string) $messageNumber, $options );
    }

    /**
     * @param resource $stream
     */
    public function expunge( $stream ): void {
        $this->assertExtensionLoaded();
        imap_expunge( $stream );
    }

    private function assertExtensionLoaded(): void {
        if ( !function_exists( 'imap_open' ) ) {
            throw new RuntimeException( 'The PHP IMAP extension is required to use ImapMailboxClient.' );
        }
    }
}
