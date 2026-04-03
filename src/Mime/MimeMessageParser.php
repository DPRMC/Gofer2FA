<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Mime;

/**
 * Parses raw MIME email messages into Gofer's normalized mailbox-message shape.
 *
 * Mailbox clients that receive RFC 822 content instead of provider-specific JSON use this parser to
 * extract headers, text and HTML bodies, and text-capable attachments in a consistent format.
 */
class MimeMessageParser {
    /**
     * Parse a raw MIME message into a normalized mailbox-message attribute array.
     *
     * @return array<string, mixed>
     */
    public function parse( string $rawMessage, ?string $fallbackId = NULL ): array {
        [ $headerBlock, $body ] = $this->splitHeadersAndBody( $rawMessage );
        $headers = $this->parseHeaders( $headerBlock );
        $contentType = $this->parseHeaderValue( $headers['content-type'] ?? 'text/plain; charset=UTF-8' );
        $contentTransferEncoding = strtolower( trim( $headers['content-transfer-encoding'] ?? '' ) );
        $parsed = [
            'text_body' => NULL,
            'html_body' => NULL,
            'attachments' => [],
        ];

        $this->parseEntity(
            $headers,
            $body,
            $contentType['value'],
            $contentType['params'],
            $contentTransferEncoding,
            $parsed
        );

        return [
            'id' => $this->normalizeHeaderValue( $headers['message-id'] ?? NULL ) ?: $fallbackId,
            'from_address' => $this->extractAddress( $headers['from'] ?? NULL ),
            'to_address' => $this->extractAddress( $headers['to'] ?? NULL ),
            'subject' => $this->normalizeHeaderValue( $headers['subject'] ?? NULL ),
            'text_body' => $parsed['text_body'],
            'html_body' => $parsed['html_body'],
            'received_at' => $this->normalizeHeaderValue( $headers['date'] ?? NULL ),
            'attachments' => $parsed['attachments'],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $contentTypeParams
     * @param array{text_body:?string,html_body:?string,attachments:array<int, array<string, string|null>>} $parsed
     */
    private function parseEntity(
        array  $headers,
        string $body,
        string $contentType,
        array  $contentTypeParams,
        string $contentTransferEncoding,
        array  &$parsed
    ): void {
        if ( strpos( $contentType, 'multipart/' ) === 0 ) {
            $boundary = $contentTypeParams['boundary'] ?? NULL;

            if ( $boundary === NULL || trim( $boundary ) === '' ) {
                return;
            }

            foreach ( $this->splitMultipartBody( $body, $boundary ) as $part ) {
                [ $partHeaderBlock, $partBody ] = $this->splitHeadersAndBody( $part );
                $partHeaders = $this->parseHeaders( $partHeaderBlock );
                $partContentType = $this->parseHeaderValue( $partHeaders['content-type'] ?? 'text/plain; charset=UTF-8' );

                $this->parseEntity(
                    $partHeaders,
                    $partBody,
                    $partContentType['value'],
                    $partContentType['params'],
                    strtolower( trim( $partHeaders['content-transfer-encoding'] ?? '' ) ),
                    $parsed
                );
            }

            return;
        }

        $contentDisposition = $this->parseHeaderValue( $headers['content-disposition'] ?? '' );
        $body = $this->decodeBody( $body, $contentTransferEncoding );
        $filename = $contentDisposition['params']['filename']
            ?? $contentTypeParams['name']
            ?? NULL;
        $isAttachment = $filename !== NULL || in_array( $contentDisposition['value'], [ 'attachment', 'inline' ], TRUE );

        if ( $isAttachment ) {
            $parsed['attachments'][] = [
                'filename' => $filename,
                'content_type' => $contentType,
                'content' => $this->attachmentCanContainText( $contentType, $filename ) ? $body : NULL,
            ];

            return;
        }

        if ( $contentType === 'text/plain' && $parsed['text_body'] === NULL ) {
            $parsed['text_body'] = $body !== '' ? $body : NULL;
        }

        if ( $contentType === 'text/html' && $parsed['html_body'] === NULL ) {
            $parsed['html_body'] = $body !== '' ? $body : NULL;
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitHeadersAndBody( string $rawMessage ): array {
        $parts = preg_split( "/\R\R/", $rawMessage, 2 );

        return [
            (string) ( $parts[0] ?? '' ),
            (string) ( $parts[1] ?? '' ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders( string $headerBlock ): array {
        $headers = [];
        $currentHeader = NULL;
        $lines = preg_split( "/\R/", $headerBlock ) ?: [];

        foreach ( $lines as $line ) {
            if ( preg_match( '/^\s+/', $line ) === 1 && $currentHeader !== NULL ) {
                $headers[$currentHeader] .= ' ' . trim( $line );

                continue;
            }

            $parts = explode( ':', $line, 2 );

            if ( count( $parts ) !== 2 ) {
                continue;
            }

            $currentHeader = strtolower( trim( $parts[0] ) );
            $headers[$currentHeader] = trim( $parts[1] );
        }

        return $headers;
    }

    /**
     * @return array{value:string,params:array<string, string>}
     */
    private function parseHeaderValue( ?string $headerValue ): array {
        $headerValue = trim( (string) $headerValue );

        if ( $headerValue === '' ) {
            return [
                'value' => '',
                'params' => [],
            ];
        }

        $segments = array_map( 'trim', explode( ';', $headerValue ) );
        $value = strtolower( array_shift( $segments ) ?: '' );
        $params = [];

        foreach ( $segments as $segment ) {
            $parts = explode( '=', $segment, 2 );

            if ( count( $parts ) !== 2 ) {
                continue;
            }

            $params[strtolower( trim( $parts[0] ) )] = trim( $parts[1], " \t\n\r\0\x0B\"" );
        }

        return [
            'value' => $value,
            'params' => $params,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitMultipartBody( string $body, string $boundary ): array {
        $delimiter = '--' . $boundary;
        $closingDelimiter = $delimiter . '--';
        $segments = explode( $delimiter, $body );
        $parts = [];

        foreach ( $segments as $segment ) {
            $segment = ltrim( $segment, "\r\n" );
            $segment = rtrim( $segment, "\r\n" );

            if ( $segment === '' || $segment === '--' || $segment === $closingDelimiter ) {
                continue;
            }

            if ( substr( $segment, -2 ) === '--' ) {
                $segment = substr( $segment, 0, -2 );
                $segment = rtrim( $segment, "\r\n" );
            }

            $parts[] = $segment;
        }

        return $parts;
    }

    private function decodeBody( string $body, string $encoding ): string {
        $body = trim( $body );

        if ( $body === '' ) {
            return '';
        }

        if ( $encoding === 'base64' ) {
            $decoded = base64_decode( $body, TRUE );

            return $decoded !== FALSE ? $decoded : '';
        }

        if ( $encoding === 'quoted-printable' ) {
            return quoted_printable_decode( $body );
        }

        return $body;
    }

    private function normalizeHeaderValue( ?string $value ): ?string {
        if ( $value === NULL ) {
            return NULL;
        }

        $decoded = $this->decodeMimeHeader( $value );
        $decoded = trim( $decoded );

        return $decoded !== '' ? $decoded : NULL;
    }

    private function extractAddress( ?string $headerValue ): ?string {
        $value = $this->normalizeHeaderValue( $headerValue );

        if ( $value === NULL ) {
            return NULL;
        }

        if ( preg_match( '/<([^>]+)>/', $value, $matches ) === 1 ) {
            $value = $matches[1];
        }

        $value = strtolower( trim( $value, " \t\n\r\0\x0B<>" ) );

        return $value !== '' ? $value : NULL;
    }

    private function decodeMimeHeader( string $value ): string {
        if ( trim( $value ) === '' ) {
            return '';
        }

        if ( function_exists( 'iconv_mime_decode' ) ) {
            $decoded = iconv_mime_decode( $value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );

            if ( is_string( $decoded ) && $decoded !== '' ) {
                return $decoded;
            }
        }

        return $value;
    }

    private function attachmentCanContainText( ?string $contentType, ?string $filename ): bool {
        $contentType = $contentType !== NULL ? strtolower( trim( $contentType ) ) : NULL;

        if ( $contentType !== NULL ) {
            if ( strpos( $contentType, 'text/' ) === 0 ) {
                return TRUE;
            }

            if ( in_array( $contentType, [ 'application/json', 'application/xml', 'text/xml', 'message/rfc822' ], TRUE ) ) {
                return TRUE;
            }
        }

        if ( $filename === NULL || trim( $filename ) === '' ) {
            return FALSE;
        }

        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        return in_array( $extension, [ 'txt', 'csv', 'log', 'json', 'xml', 'html', 'htm', 'eml' ], TRUE );
    }
}
