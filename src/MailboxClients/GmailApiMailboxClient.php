<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\MailboxClients;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxMessage;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use RuntimeException;

/**
 * Mailbox client for Gmail inboxes queried through the Gmail API.
 *
 * This class standardizes Gmail API message and attachment normalization inside the library so callers only
 * provide mailbox credentials and query parameters rather than hand-building normalized message arrays.
 */
class GmailApiMailboxClient implements MailboxClientInterface {
    private string $userId;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $baseUrl;
    /**
     * @var callable|null
     */
    private $httpClient;
    private ?string $accessToken = NULL;
    private int $expiresAt = 0;

    /**
     * Create a Gmail API-backed mailbox client.
     */
    public function __construct(
        string    $userId,
        string    $clientId,
        string    $clientSecret,
        string    $refreshToken,
        string    $baseUrl = 'https://gmail.googleapis.com/gmail/v1',
        ?callable $httpClient = NULL
    ) {
        $this->userId = trim( $userId );
        $this->clientId = trim( $clientId );
        $this->clientSecret = trim( $clientSecret );
        $this->refreshToken = trim( $refreshToken );
        $this->baseUrl = rtrim( trim( $baseUrl ), '/' );
        $this->httpClient = $httpClient;
    }

    /**
     * Fetch candidate mailbox messages from Gmail and normalize them for Gofer.
     *
     * @return iterable<MailboxMessageInterface>
     */
    public function findMessages( MessageQuery $query ): iterable {
        foreach ( $this->fetchNormalizedMessages( $query ) as $message ) {
            yield new ArrayMailboxMessage( $message );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchNormalizedMessages( MessageQuery $query ): array {
        $response = $this->gmailGet(
            sprintf(
                '%s/users/%s/messages?%s',
                $this->baseUrl,
                rawurlencode( $this->userId ),
                http_build_query( [
                    'maxResults' => max( $query->limit() * 3, 25 ),
                    'q' => $this->gmailSearchQuery( $query ),
                ] )
            )
        );
        $payload = $this->decodeJson( $response['body'], 'Gmail list response' );
        $messages = $payload['messages'] ?? [];

        if ( !is_array( $messages ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $messages as $message ) {
            if ( !is_array( $message ) || !isset( $message['id'] ) ) {
                continue;
            }

            $normalizedMessage = $this->fetchMessage( (string) $message['id'] );

            if ( !$this->messageMatchesQuery( $normalizedMessage, $query ) ) {
                continue;
            }

            $normalized[] = $normalizedMessage;

            if ( count( $normalized ) >= $query->limit() ) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchMessage( string $messageId ): array {
        $response = $this->gmailGet(
            sprintf(
                '%s/users/%s/messages/%s?%s',
                $this->baseUrl,
                rawurlencode( $this->userId ),
                rawurlencode( $messageId ),
                http_build_query( [
                    'format' => 'full',
                ] )
            )
        );
        $payload = $this->decodeJson( $response['body'], 'Gmail message response' );
        $headers = $this->headersFromPayload( $payload['payload']['headers'] ?? [] );
        $parts = [
            'text_body' => NULL,
            'html_body' => NULL,
            'attachments' => [],
        ];

        $this->walkPayloadPart( $payload['payload'] ?? [], $messageId, $parts );

        return [
            'id' => isset( $payload['id'] ) ? (string) $payload['id'] : $messageId,
            'from_address' => $this->extractAddress( $headers['from'] ?? NULL ),
            'to_address' => $this->extractAddress( $headers['to'] ?? NULL ),
            'subject' => $headers['subject'] ?? NULL,
            'text_body' => $parts['text_body'],
            'html_body' => $parts['html_body'],
            'received_at' => isset( $payload['internalDate'] )
                ? ( new DateTimeImmutable( '@' . ( (int) $payload['internalDate'] / 1000 ) ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( DATE_ATOM )
                : NULL,
            'attachments' => $parts['attachments'],
        ];
    }

    /**
     * @param mixed $payloadPart
     * @param array{text_body:?string,html_body:?string,attachments:array<int, array<string, string|null>>} $parts
     */
    private function walkPayloadPart( $payloadPart, string $messageId, array &$parts ): void {
        if ( !is_array( $payloadPart ) ) {
            return;
        }

        $mimeType = strtolower( trim( (string) ( $payloadPart['mimeType'] ?? '' ) ) );
        $filename = isset( $payloadPart['filename'] ) ? trim( (string) $payloadPart['filename'] ) : '';
        $body = is_array( $payloadPart['body'] ?? NULL ) ? $payloadPart['body'] : [];

        if ( isset( $payloadPart['parts'] ) && is_array( $payloadPart['parts'] ) ) {
            foreach ( $payloadPart['parts'] as $childPart ) {
                $this->walkPayloadPart( $childPart, $messageId, $parts );
            }
        }

        if ( $mimeType === '' ) {
            return;
        }

        $content = $this->partBodyContent( $body, $messageId );

        if ( $filename !== '' ) {
            $parts['attachments'][] = [
                'filename' => $filename,
                'content_type' => $mimeType,
                'content' => $this->attachmentCanContainText( $mimeType, $filename ) ? $content : NULL,
            ];

            return;
        }

        if ( $mimeType === 'text/plain' && $parts['text_body'] === NULL ) {
            $parts['text_body'] = $content !== '' ? $content : NULL;
        }

        if ( $mimeType === 'text/html' && $parts['html_body'] === NULL ) {
            $parts['html_body'] = $content !== '' ? $content : NULL;
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function partBodyContent( array $body, string $messageId ): string {
        if ( isset( $body['data'] ) && is_string( $body['data'] ) ) {
            return $this->decodeBase64Url( $body['data'] );
        }

        if ( isset( $body['attachmentId'] ) && is_string( $body['attachmentId'] ) ) {
            $response = $this->gmailGet(
                sprintf(
                    '%s/users/%s/messages/%s/attachments/%s',
                    $this->baseUrl,
                    rawurlencode( $this->userId ),
                    rawurlencode( $messageId ),
                    rawurlencode( $body['attachmentId'] )
                )
            );
            $payload = $this->decodeJson( $response['body'], 'Gmail attachment response' );

            return isset( $payload['data'] ) && is_string( $payload['data'] )
                ? $this->decodeBase64Url( $payload['data'] )
                : '';
        }

        return '';
    }

    /**
     * @param mixed $headers
     *
     * @return array<string, string>
     */
    private function headersFromPayload( $headers ): array {
        if ( !is_array( $headers ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $headers as $header ) {
            if ( !is_array( $header ) || !isset( $header['name'], $header['value'] ) ) {
                continue;
            }

            $normalized[strtolower( trim( (string) $header['name'] ) )] = trim( (string) $header['value'] );
        }

        return $normalized;
    }

    private function gmailSearchQuery( MessageQuery $query ): string {
        $parts = [];

        if ( $query->since() !== NULL ) {
            $parts[] = 'after:' . $query->since()->getTimestamp();
        }

        foreach ( $query->fromAddresses() as $address ) {
            $parts[] = 'from:' . $address;
        }

        foreach ( $query->toAddresses() as $address ) {
            $parts[] = 'to:' . $address;
        }

        return implode( ' ', $parts );
    }

    private function accessToken(): string {
        if ( $this->accessToken !== NULL && time() < $this->expiresAt ) {
            return $this->accessToken;
        }

        $response = $this->httpRequest(
            'POST',
            'https://oauth2.googleapis.com/token',
            [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            http_build_query( [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ] )
        );
        $payload = $this->decodeJson( $response['body'], 'Gmail token response' );
        $token = $payload['access_token'] ?? NULL;

        if ( !is_string( $token ) || trim( $token ) === '' ) {
            throw new RuntimeException( 'Gmail token response did not include an access_token.' );
        }

        $expiresIn = isset( $payload['expires_in'] ) ? (int) $payload['expires_in'] : 3600;
        $this->accessToken = $token;
        $this->expiresAt = time() + max( 60, $expiresIn - 60 );

        return $this->accessToken;
    }

    /**
     * @return array{status:int,body:string}
     */
    private function gmailGet( string $url ): array {
        return $this->httpRequest(
            'GET',
            $url,
            [
                'Authorization: Bearer ' . $this->accessToken(),
                'Accept: application/json',
            ]
        );
    }

    private function decodeBase64Url( string $value ): string {
        $value = strtr( $value, '-_', '+/' );
        $padding = strlen( $value ) % 4;

        if ( $padding > 0 ) {
            $value .= str_repeat( '=', 4 - $padding );
        }

        $decoded = base64_decode( $value, TRUE );

        return $decoded !== FALSE ? $decoded : '';
    }

    private function extractAddress( ?string $headerValue ): ?string {
        if ( $headerValue === NULL || trim( $headerValue ) === '' ) {
            return NULL;
        }

        if ( preg_match( '/<([^>]+)>/', $headerValue, $matches ) === 1 ) {
            $headerValue = $matches[1];
        }

        $headerValue = strtolower( trim( $headerValue, " \t\n\r\0\x0B<>" ) );

        return $headerValue !== '' ? $headerValue : NULL;
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

    /**
     * @return array<string, mixed>
     */
    private function decodeJson( string $json, string $context ): array {
        $payload = json_decode( $json, TRUE );

        if ( !is_array( $payload ) ) {
            throw new RuntimeException( sprintf( 'Unable to decode %s JSON: %s', $context, $json ) );
        }

        return $payload;
    }

    /**
     * @return array{status:int,body:string}
     */
    private function httpRequest( string $method, string $url, array $headers, ?string $body = NULL ): array {
        if ( $this->httpClient !== NULL ) {
            $response = call_user_func( $this->httpClient, $method, $url, $headers, $body );

            if ( !is_array( $response ) || !isset( $response['status'], $response['body'] ) ) {
                throw new RuntimeException( 'GmailApiMailboxClient HTTP client must return an array with status and body keys.' );
            }

            $status = (int) $response['status'];
            $bodyText = (string) $response['body'];
        } else {
            $headerBlock = implode( "\r\n", $headers );
            $options = [
                'http' => [
                    'method' => $method,
                    'header' => $headerBlock,
                    'ignore_errors' => TRUE,
                    'timeout' => 30,
                ],
            ];

            if ( $body !== NULL ) {
                $options['http']['content'] = $body;
            }

            $context = stream_context_create( $options );
            $result = @file_get_contents( $url, FALSE, $context );
            $responseHeaders = $http_response_header ?? [];
            $statusLine = is_array( $responseHeaders ) ? ( $responseHeaders[0] ?? '' ) : '';
            preg_match( '/\s(\d{3})\s/', (string) $statusLine, $matches );
            $status = isset( $matches[1] ) ? (int) $matches[1] : 0;
            $bodyText = $result !== FALSE ? $result : '';
        }

        if ( $status < 200 || $status >= 300 ) {
            throw new RuntimeException( sprintf( 'Gmail HTTP %s %s failed with status %d: %s', $method, $url, $status, $bodyText ) );
        }

        return [
            'status' => $status,
            'body' => $bodyText,
        ];
    }
}
