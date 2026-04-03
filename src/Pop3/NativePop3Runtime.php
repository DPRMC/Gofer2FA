<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Pop3;

use DPRMC\Gofer2FA\Contracts\Pop3RuntimeInterface;
use RuntimeException;

/**
 * Production POP3 runtime backed by PHP stream sockets.
 *
 * This implements the minimal POP3 commands Gofer needs: connect, authenticate, list messages,
 * retrieve raw messages, and quit.
 */
class NativePop3Runtime implements Pop3RuntimeInterface {
    /**
     * Open a POP3 socket connection and verify the server greeting.
     *
     * @return resource
     */
    public function open( string $host, int $port, bool $useTls, bool $useStartTls = FALSE, int $timeout = 30 ) {
        $scheme = $useTls ? 'ssl' : 'tcp';
        $connection = @stream_socket_client(
            sprintf( '%s://%s:%d', $scheme, $host, $port ),
            $errorNumber,
            $errorMessage,
            $timeout
        );

        if ( $connection === FALSE ) {
            throw new RuntimeException( sprintf( 'Unable to open POP3 connection: %s', $errorMessage ?: 'unknown error' ) );
        }

        stream_set_timeout( $connection, $timeout );
        $this->assertOkResponse( $this->readLine( $connection ) );

        if ( !$useTls && $useStartTls ) {
            $this->assertOkResponse( $this->sendCommand( $connection, 'STLS' ) );

            if ( !stream_socket_enable_crypto( $connection, TRUE, $this->streamCryptoMethod() ) ) {
                throw new RuntimeException( 'Unable to enable POP3 STARTTLS encryption.' );
            }
        }

        return $connection;
    }

    /**
     * Authenticate a POP3 session with USER and PASS commands.
     *
     * @param resource $connection
     */
    public function authenticate( $connection, string $username, string $password ): void {
        $this->assertOkResponse( $this->sendCommand( $connection, 'USER ' . $username ) );
        $this->assertOkResponse( $this->sendCommand( $connection, 'PASS ' . $password ) );
    }

    /**
     * Return the current POP3 message list with message number and size.
     *
     * @param resource $connection
     *
     * @return array<int, array{number:int,size:int}>
     */
    public function listMessages( $connection ): array {
        $response = $this->sendCommand( $connection, 'LIST' );
        $this->assertOkResponse( $response );
        $lines = $this->readMultiline( $connection );
        $messages = [];

        foreach ( $lines as $line ) {
            $parts = preg_split( '/\s+/', trim( $line ) ) ?: [];

            if ( count( $parts ) < 2 ) {
                continue;
            }

            $messages[] = [
                'number' => (int) $parts[0],
                'size' => (int) $parts[1],
            ];
        }

        return $messages;
    }

    /**
     * Retrieve the full raw RFC 822 message for a POP3 message number.
     *
     * @param resource $connection
     */
    public function retrieveMessage( $connection, int $messageNumber ): string {
        $response = $this->sendCommand( $connection, 'RETR ' . $messageNumber );
        $this->assertOkResponse( $response );
        $lines = $this->readMultiline( $connection );

        return implode( "\r\n", $lines );
    }

    /**
     * Close the POP3 connection with QUIT.
     *
     * @param resource $connection
     */
    public function close( $connection ): void {
        @fwrite( $connection, "QUIT\r\n" );
        fclose( $connection );
    }

    /**
     * @param resource $connection
     */
    private function sendCommand( $connection, string $command ): string {
        fwrite( $connection, $command . "\r\n" );

        return $this->readLine( $connection );
    }

    /**
     * @param resource $connection
     */
    private function readLine( $connection ): string {
        $line = fgets( $connection );

        if ( $line === FALSE ) {
            throw new RuntimeException( 'POP3 server closed the connection unexpectedly.' );
        }

        return rtrim( $line, "\r\n" );
    }

    /**
     * @param resource $connection
     *
     * @return array<int, string>
     */
    private function readMultiline( $connection ): array {
        $lines = [];

        while ( TRUE ) {
            $line = $this->readLine( $connection );

            if ( $line === '.' ) {
                break;
            }

            if ( strpos( $line, '..' ) === 0 ) {
                $line = substr( $line, 1 );
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function assertOkResponse( string $response ): void {
        if ( strpos( $response, '+OK' ) !== 0 ) {
            throw new RuntimeException( 'POP3 command failed: ' . $response );
        }
    }

    private function streamCryptoMethod(): int {
        if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ) {
            return STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        return defined( 'STREAM_CRYPTO_METHOD_TLS_CLIENT' )
            ? STREAM_CRYPTO_METHOD_TLS_CLIENT
            : 0;
    }
}
