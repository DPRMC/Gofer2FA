<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\MailboxClients;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxMessage;
use DPRMC\Gofer2FA\Contracts\DeletableMailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\Mime\MimeMessageParser;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use RuntimeException;

/**
 * Mailbox client for SES inbound mail stored in Amazon S3.
 *
 * This client lists MIME message objects in S3, downloads the newest raw messages, parses them through
 * `MimeMessageParser`, and yields normalized mailbox message objects for Gofer.
 */
class SesS3MailboxClient implements DeletableMailboxClientInterface {
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $region;
    private string $bucket;
    private string $prefix;
    private ?string $sessionToken;
    /**
     * @var callable|null
     */
    private $httpClient;
    private MimeMessageParser $parser;
    private string $service = 's3';
    private string $endpointBase;

    /**
     * Create an SES/S3-backed mailbox client.
     */
    public function __construct(
        string             $accessKeyId,
        string             $secretAccessKey,
        string             $region,
        string             $bucket,
        string             $prefix = '',
        ?string            $sessionToken = NULL,
        ?callable          $httpClient = NULL,
        ?MimeMessageParser $parser = NULL,
        ?string            $endpointBase = NULL
    ) {
        $this->accessKeyId = trim( $accessKeyId );
        $this->secretAccessKey = trim( $secretAccessKey );
        $this->region = trim( $region );
        $this->bucket = trim( $bucket );
        $this->prefix = trim( $prefix, '/' );
        $this->sessionToken = $sessionToken !== NULL && trim( $sessionToken ) !== '' ? trim( $sessionToken ) : NULL;
        $this->httpClient = $httpClient;
        $this->parser = $parser ?: new MimeMessageParser();
        $this->endpointBase = $endpointBase ?: sprintf( 'https://%s.s3.%s.amazonaws.com', $this->bucket, $this->region );
    }

    /**
     * Fetch candidate mailbox messages from S3 and normalize them for Gofer.
     *
     * @return iterable<MailboxMessageInterface>
     */
    public function findMessages( MessageQuery $query ): iterable {
        foreach ( $this->fetchNormalizedMessages( $query ) as $message ) {
            yield new ArrayMailboxMessage( $message );
        }
    }

    /**
     * Delete a raw SES/S3 message object by S3 object key.
     */
    public function deleteMessage( string $messageId ): void {
        $this->signedRequest( 'DELETE', '/' . str_replace( '%2F', '/', rawurlencode( $messageId ) ) );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchNormalizedMessages( MessageQuery $query ): array {
        $objects = $this->listObjects( max( $query->limit() * 3, 25 ) );
        usort(
            $objects,
            static fn( array $left, array $right ): int => strcmp( $right['last_modified'] ?? '', $left['last_modified'] ?? '' )
        );
        $normalized = [];

        foreach ( $objects as $object ) {
            $key = (string) ( $object['key'] ?? '' );

            if ( $key === '' ) {
                continue;
            }

            $parsed = $this->parser->parse( $this->getObject( $key ), $key );

            if ( !$this->messageMatchesQuery( $parsed, $query ) ) {
                continue;
            }

            if ( ( $parsed['received_at'] ?? NULL ) === NULL && isset( $object['last_modified'] ) ) {
                $parsed['received_at'] = (string) $object['last_modified'];
            }

            $normalized[] = $parsed;

            if ( count( $normalized ) >= $query->limit() ) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, array{key:string,last_modified:string}>
     */
    private function listObjects( int $maxKeys ): array {
        $query = [
            'list-type' => '2',
            'max-keys' => (string) $maxKeys,
        ];

        if ( $this->prefix !== '' ) {
            $query['prefix'] = $this->prefix . '/';
        }

        $response = $this->signedRequest( 'GET', '/', $query );
        $xml = @simplexml_load_string( $response['body'] );

        if ( $xml === FALSE ) {
            throw new RuntimeException( 'Unable to decode S3 ListObjectsV2 XML response.' );
        }

        $objects = [];

        foreach ( $xml->Contents ?? [] as $content ) {
            $objects[] = [
                'key' => (string) $content->Key,
                'last_modified' => (string) $content->LastModified,
            ];
        }

        return $objects;
    }

    private function getObject( string $key ): string {
        $response = $this->signedRequest( 'GET', '/' . str_replace( '%2F', '/', rawurlencode( $key ) ) );

        return $response['body'];
    }

    /**
     * @param array<string, string> $query
     *
     * @return array{status:int,body:string}
     */
    private function signedRequest( string $method, string $canonicalUri, array $query = [] ): array {
        $amzDate = gmdate( 'Ymd\THis\Z' );
        $dateStamp = gmdate( 'Ymd' );
        ksort( $query );
        $canonicalQueryString = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
        $host = parse_url( $this->endpointBase, PHP_URL_HOST );

        if ( !is_string( $host ) || $host === '' ) {
            throw new RuntimeException( 'Invalid S3 endpoint host.' );
        }

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => hash( 'sha256', '' ),
            'x-amz-date' => $amzDate,
        ];

        if ( $this->sessionToken !== NULL ) {
            $headers['x-amz-security-token'] = $this->sessionToken;
        }

        ksort( $headers );
        $canonicalHeaders = '';

        foreach ( $headers as $name => $value ) {
            $canonicalHeaders .= $name . ':' . trim( $value ) . "\n";
        }

        $signedHeaders = implode( ';', array_keys( $headers ) );
        $canonicalRequest = implode(
            "\n",
            [
                $method,
                $canonicalUri,
                $canonicalQueryString,
                $canonicalHeaders,
                $signedHeaders,
                hash( 'sha256', '' ),
            ]
        );
        $credentialScope = $dateStamp . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $stringToSign = implode(
            "\n",
            [
                'AWS4-HMAC-SHA256',
                $amzDate,
                $credentialScope,
                hash( 'sha256', $canonicalRequest ),
            ]
        );
        $signingKey = $this->signatureKey( $dateStamp );
        $signature = hash_hmac( 'sha256', $stringToSign, $signingKey );
        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKeyId,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        $requestHeaders = [
            'Authorization: ' . $authorization,
            'x-amz-content-sha256: ' . $headers['x-amz-content-sha256'],
            'x-amz-date: ' . $headers['x-amz-date'],
        ];

        if ( $this->sessionToken !== NULL ) {
            $requestHeaders[] = 'x-amz-security-token: ' . $this->sessionToken;
        }

        $url = $this->endpointBase . $canonicalUri . ( $canonicalQueryString !== '' ? '?' . $canonicalQueryString : '' );

        return $this->httpRequest( $method, $url, $requestHeaders );
    }

    private function signatureKey( string $dateStamp ): string {
        $kDate = hash_hmac( 'sha256', $dateStamp, 'AWS4' . $this->secretAccessKey, TRUE );
        $kRegion = hash_hmac( 'sha256', $this->region, $kDate, TRUE );
        $kService = hash_hmac( 'sha256', $this->service, $kRegion, TRUE );

        return hash_hmac( 'sha256', 'aws4_request', $kService, TRUE );
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
     * @return array{status:int,body:string}
     */
    private function httpRequest( string $method, string $url, array $headers ): array {
        if ( $this->httpClient !== NULL ) {
            $response = call_user_func( $this->httpClient, $method, $url, $headers, NULL );

            if ( !is_array( $response ) || !isset( $response['status'], $response['body'] ) ) {
                throw new RuntimeException( 'SesS3MailboxClient HTTP client must return an array with status and body keys.' );
            }

            $status = (int) $response['status'];
            $bodyText = (string) $response['body'];
        } else {
            $headerBlock = implode( "\r\n", $headers );
            $context = stream_context_create( [
                'http' => [
                    'method' => $method,
                    'header' => $headerBlock,
                    'ignore_errors' => TRUE,
                    'timeout' => 30,
                ],
            ] );
            $result = @file_get_contents( $url, FALSE, $context );
            $responseHeaders = $http_response_header ?? [];
            $statusLine = is_array( $responseHeaders ) ? ( $responseHeaders[0] ?? '' ) : '';
            preg_match( '/\s(\d{3})\s/', (string) $statusLine, $matches );
            $status = isset( $matches[1] ) ? (int) $matches[1] : 0;
            $bodyText = $result !== FALSE ? $result : '';
        }

        if ( $status < 200 || $status >= 300 ) {
            throw new RuntimeException( sprintf( 'SES S3 HTTP %s %s failed with status %d: %s', $method, $url, $status, $bodyText ) );
        }

        return [
            'status' => $status,
            'body' => $bodyText,
        ];
    }
}
