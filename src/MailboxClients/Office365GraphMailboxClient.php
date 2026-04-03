<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\MailboxClients;

use DateTimeImmutable;
use DateTimeInterface;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxMessage;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use RuntimeException;

/**
 * Mailbox client for Microsoft 365 mailboxes queried through Microsoft Graph.
 *
 * This class standardizes the Graph-to-Gofer mapping that previously lived in ad hoc bootstrap code.
 * Callers provide tenant credentials and mailbox coordinates, `findMessages()` performs the Graph calls,
 * and the client yields normalized `MailboxMessageInterface` objects for the rest of the parsing flow.
 */
class Office365GraphMailboxClient implements MailboxClientInterface {
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $mailboxUser;
    private string $mailFolder;
    private string $graphBaseUrl;
    /**
     * @var callable|null
     */
    private $httpClient;
    private ?string $accessToken = NULL;
    private int $expiresAt = 0;

    /**
     * Create a Graph-backed mailbox client for a specific Microsoft 365 mailbox.
     */
    public function __construct(
        string    $tenantId,
        string    $clientId,
        string    $clientSecret,
        string    $mailboxUser,
        string    $mailFolder = 'inbox',
        string    $graphBaseUrl = 'https://graph.microsoft.com/v1.0',
        ?callable $httpClient = NULL
    ) {
        $this->tenantId = trim( $tenantId );
        $this->clientId = trim( $clientId );
        $this->clientSecret = trim( $clientSecret );
        $this->mailboxUser = trim( $mailboxUser );
        $this->mailFolder = trim( $mailFolder ) !== '' ? trim( $mailFolder ) : 'inbox';
        $this->graphBaseUrl = rtrim( trim( $graphBaseUrl ), '/' );
        $this->httpClient = $httpClient;
    }

    /**
     * Fetch candidate mailbox messages from Microsoft Graph and normalize them for Gofer.
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
        $response = $this->graphGet( $this->messagesUrl( $query ) );
        $payload = $this->decodeJson( $response['body'], 'messages response' );
        $messages = $payload['value'] ?? [];

        if ( !is_array( $messages ) ) {
            throw new RuntimeException( 'Microsoft messages response did not include a valid value array.' );
        }

        $normalized = [];

        foreach ( $messages as $message ) {
            if ( !is_array( $message ) ) {
                continue;
            }

            $normalizedMessage = [
                'id' => isset( $message['id'] ) ? (string) $message['id'] : NULL,
                'from_address' => $this->emailAddress( $message['from'] ?? NULL ),
                'to_address' => $this->firstRecipient( $message['toRecipients'] ?? NULL ),
                'subject' => isset( $message['subject'] ) ? (string) $message['subject'] : NULL,
                'text_body' => $this->textBody( $message['body'] ?? NULL, $message['bodyPreview'] ?? NULL ),
                'html_body' => $this->htmlBody( $message['body'] ?? NULL ),
                'received_at' => isset( $message['receivedDateTime'] ) ? (string) $message['receivedDateTime'] : NULL,
                'attachments' => [],
            ];

            if ( !$this->messageMatchesQuery( $normalizedMessage, $query ) ) {
                continue;
            }

            if ( !empty( $message['hasAttachments'] ) && !empty( $normalizedMessage['id'] ) ) {
                $normalizedMessage['attachments'] = $this->fetchAttachments( (string) $normalizedMessage['id'] );
            }

            $normalized[] = $normalizedMessage;
        }

        return $normalized;
    }

    private function messagesUrl( MessageQuery $query ): string {
        return sprintf(
            '%s/users/%s/mailFolders/%s/messages?%s',
            $this->graphBaseUrl,
            rawurlencode( $this->mailboxUser ),
            rawurlencode( $this->mailFolder ),
            http_build_query( [
                '$top' => $query->limit(),
                '$orderby' => 'receivedDateTime desc',
                '$select' => 'id,subject,from,toRecipients,receivedDateTime,body,bodyPreview,hasAttachments',
            ] )
        );
    }

    /**
     * @return array{status:int,body:string}
     */
    private function graphGet( string $url ): array {
        return $this->httpRequest(
            'GET',
            $url,
            [
                'Authorization: Bearer ' . $this->accessToken(),
                'Accept: application/json',
            ]
        );
    }

    private function accessToken(): string {
        if ( $this->accessToken !== NULL && time() < $this->expiresAt ) {
            return $this->accessToken;
        }

        $response = $this->httpRequest(
            'POST',
            sprintf(
                'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
                rawurlencode( $this->tenantId )
            ),
            [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            http_build_query( [
                'client_id' => $this->clientId,
                'scope' => 'https://graph.microsoft.com/.default',
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ] )
        );

        $payload = $this->decodeJson( $response['body'], 'token response' );
        $token = $payload['access_token'] ?? NULL;

        if ( !is_string( $token ) || trim( $token ) === '' ) {
            throw new RuntimeException( 'Microsoft token response did not include an access_token.' );
        }

        $expiresIn = isset( $payload['expires_in'] ) ? (int) $payload['expires_in'] : 3600;
        $this->accessToken = $token;
        $this->expiresAt = time() + max( 60, $expiresIn - 60 );

        return $this->accessToken;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchAttachments( string $messageId ): array {
        $response = $this->graphGet(
            sprintf(
                '%s/users/%s/messages/%s/attachments?%s',
                $this->graphBaseUrl,
                rawurlencode( $this->mailboxUser ),
                rawurlencode( $messageId ),
                http_build_query( [
                    '$select' => 'id,name,contentType',
                ] )
            )
        );
        $payload = $this->decodeJson( $response['body'], 'attachments response' );
        $attachments = $payload['value'] ?? [];

        if ( !is_array( $attachments ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $attachments as $attachment ) {
            if ( !is_array( $attachment ) ) {
                continue;
            }

            $attachmentId = isset( $attachment['id'] ) ? (string) $attachment['id'] : NULL;
            $contentType = isset( $attachment['contentType'] ) ? strtolower( trim( (string) $attachment['contentType'] ) ) : NULL;
            $filename = isset( $attachment['name'] ) ? (string) $attachment['name'] : NULL;

            $normalized[] = [
                'filename' => $filename,
                'content_type' => $contentType,
                'content' => $attachmentId !== NULL && $attachmentId !== ''
                    ? $this->fetchAttachmentContent( $messageId, $attachmentId, $contentType, $filename )
                    : NULL,
            ];
        }

        return $normalized;
    }

    private function fetchAttachmentContent(
        string  $messageId,
        string  $attachmentId,
        ?string $contentType = NULL,
        ?string $filename = NULL
    ): ?string {
        if ( !$this->attachmentCanContainText( $contentType, $filename ) ) {
            return NULL;
        }

        $response = $this->graphGet(
            sprintf(
                '%s/users/%s/messages/%s/attachments/%s/$value',
                $this->graphBaseUrl,
                rawurlencode( $this->mailboxUser ),
                rawurlencode( $messageId ),
                rawurlencode( $attachmentId )
            )
        );

        return $response['body'] !== '' ? $response['body'] : NULL;
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

        return in_array( $extension, [ 'txt', 'csv', 'log', 'json', 'xml', 'html', 'htm' ], TRUE );
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
     * @param mixed $from
     */
    private function emailAddress( $from ): ?string {
        if ( !is_array( $from ) ) {
            return NULL;
        }

        $address = $from['emailAddress']['address'] ?? NULL;

        return is_string( $address ) && trim( $address ) !== ''
            ? strtolower( trim( $address ) )
            : NULL;
    }

    /**
     * @param mixed $recipients
     */
    private function firstRecipient( $recipients ): ?string {
        if ( !is_array( $recipients ) || !isset( $recipients[0] ) || !is_array( $recipients[0] ) ) {
            return NULL;
        }

        $address = $recipients[0]['emailAddress']['address'] ?? NULL;

        return is_string( $address ) && trim( $address ) !== ''
            ? strtolower( trim( $address ) )
            : NULL;
    }

    /**
     * @param mixed $body
     * @param mixed $bodyPreview
     */
    private function textBody( $body, $bodyPreview ): ?string {
        if ( is_array( $body ) && ( $body['contentType'] ?? NULL ) === 'text' && isset( $body['content'] ) ) {
            return (string) $body['content'];
        }

        if ( is_string( $bodyPreview ) && trim( $bodyPreview ) !== '' ) {
            return $bodyPreview;
        }

        return NULL;
    }

    /**
     * @param mixed $body
     */
    private function htmlBody( $body ): ?string {
        if ( !is_array( $body ) || ( $body['contentType'] ?? NULL ) !== 'html' || !isset( $body['content'] ) ) {
            return NULL;
        }

        return (string) $body['content'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson( string $json, string $context ): array {
        $payload = json_decode( $json, TRUE );

        if ( !is_array( $payload ) ) {
            throw new RuntimeException( sprintf( 'Unable to decode Office 365 %s JSON: %s', $context, $json ) );
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
                throw new RuntimeException( 'Office365GraphMailboxClient HTTP client must return an array with status and body keys.' );
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
            throw new RuntimeException( sprintf( 'Office 365 HTTP %s %s failed with status %d: %s', $method, $url, $status, $bodyText ) );
        }

        return [
            'status' => $status,
            'body' => $bodyText,
        ];
    }
}
