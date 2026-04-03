<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\MailboxClients;

use DateTimeImmutable;
use DPRMC\Gofer2FA\Adapters\ArrayMailboxMessage;
use DPRMC\Gofer2FA\Contracts\DeletableMailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\ImapRuntimeInterface;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\Imap\NativeImapRuntime;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

/**
 * Mailbox client backed by a traditional IMAP inbox.
 *
 * This client owns IMAP-specific message reading and normalization so callers no longer need to translate
 * message metadata, body parts, and attachments into Gofer's expected array shape themselves.
 */
class ImapMailboxClient implements DeletableMailboxClientInterface {
    private string $mailbox;
    private string $username;
    private string $password;
    private int $options;
    private int $retries;
    /**
     * @var array<string, mixed>
     */
    private array $parameters;
    private ImapRuntimeInterface $runtime;

    /**
     * Create an IMAP-backed mailbox client.
     *
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string                 $mailbox,
        string                 $username,
        string                 $password,
        int                    $options = 0,
        int                    $retries = 0,
        array                  $parameters = [],
        ?ImapRuntimeInterface  $runtime = NULL
    ) {
        $this->mailbox = $mailbox;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->retries = $retries;
        $this->parameters = $parameters;
        $this->runtime = $runtime ?: new NativeImapRuntime();
    }

    /**
     * Fetch candidate mailbox messages from IMAP and normalize them for Gofer.
     *
     * @return iterable<MailboxMessageInterface>
     */
    public function findMessages( MessageQuery $query ): iterable {
        $stream = $this->runtime->open(
            $this->mailbox,
            $this->username,
            $this->password,
            $this->options,
            $this->retries,
            $this->parameters
        );

        try {
            foreach ( $this->normalizedMessages( $stream, $query ) as $message ) {
                yield new ArrayMailboxMessage( $message );
            }
        } finally {
            $this->runtime->close( $stream );
        }
    }

    /**
     * Delete an IMAP message by normalized id or UID fallback.
     */
    public function deleteMessage( string $messageId ): void {
        $stream = $this->runtime->open(
            $this->mailbox,
            $this->username,
            $this->password,
            $this->options,
            $this->retries,
            $this->parameters
        );

        try {
            foreach ( $this->runtime->search( $stream, 'ALL' ) as $uid ) {
                $overview = $this->runtime->fetchOverview( $stream, (string) $uid, $this->imapUidFlag() )[0] ?? NULL;

                if ( !is_object( $overview ) ) {
                    continue;
                }

                $normalizedId = isset( $overview->message_id ) ? trim( (string) $overview->message_id ) : (string) $uid;

                if ( $normalizedId !== $messageId ) {
                    continue;
                }

                $this->runtime->deleteMessage( $stream, (int) $uid, $this->imapUidFlag() );
                $this->runtime->expunge( $stream );

                return;
            }
        } finally {
            $this->runtime->close( $stream );
        }
    }

    /**
     * @param mixed $stream
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizedMessages( $stream, MessageQuery $query ): array {
        $uids = $this->runtime->search( $stream, $this->searchCriteria( $query ) );
        rsort( $uids );
        $normalized = [];

        foreach ( $uids as $uid ) {
            $overview = $this->runtime->fetchOverview( $stream, (string) $uid, $this->imapUidFlag() )[0] ?? NULL;

            if ( !is_object( $overview ) ) {
                continue;
            }

            $message = $this->normalizeMessage( $stream, (int) $uid, $overview );

            if ( !$this->messageMatchesQuery( $message, $query ) ) {
                continue;
            }

            $normalized[] = $message;

            if ( count( $normalized ) >= $query->limit() ) {
                break;
            }
        }

        return $normalized;
    }

    private function searchCriteria( MessageQuery $query ): string {
        if ( $query->since() === NULL ) {
            return 'ALL';
        }

        return 'SINCE "' . $query->since()->format( 'd-M-Y' ) . '"';
    }

    /**
     * @param mixed $stream
     * @param object $overview
     *
     * @return array<string, mixed>
     */
    private function normalizeMessage( $stream, int $uid, object $overview ): array {
        $structure = $this->runtime->fetchStructure( $stream, $uid );
        $content = $this->extractContent( $stream, $uid, $structure );

        return [
            'id' => isset( $overview->message_id ) ? trim( (string) $overview->message_id ) : (string) $uid,
            'from_address' => $this->normalizeAddress( $overview->from ?? NULL ),
            'to_address' => $this->normalizeAddress( $overview->to ?? NULL ),
            'subject' => isset( $overview->subject ) ? $this->decodeMimeHeader( (string) $overview->subject ) : NULL,
            'text_body' => $content['text_body'],
            'html_body' => $content['html_body'],
            'received_at' => isset( $overview->date ) ? (string) $overview->date : NULL,
            'attachments' => $content['attachments'],
        ];
    }

    /**
     * @param mixed $stream
     * @param object|null $structure
     *
     * @return array{text_body:?string,html_body:?string,attachments:array<int, array<string, string|null>>}
     */
    private function extractContent( $stream, int $uid, $structure ): array {
        $result = [
            'text_body' => NULL,
            'html_body' => NULL,
            'attachments' => [],
        ];

        if ( !is_object( $structure ) ) {
            $body = $this->runtime->fetchBody( $stream, $uid, '', $this->imapUidFlag() );
            $result['text_body'] = $body !== '' ? $body : NULL;

            return $result;
        }

        $this->walkParts( $stream, $uid, $structure, '', $result );

        return $result;
    }

    /**
     * @param mixed $stream
     * @param object $part
     * @param array{text_body:?string,html_body:?string,attachments:array<int, array<string, string|null>>} $result
     */
    private function walkParts( $stream, int $uid, object $part, string $section, array &$result ): void {
        $parameters = $this->partParameters( $part );
        $isAttachment = isset( $parameters['filename'] ) || isset( $parameters['name'] ) || strtolower( (string) ( $part->disposition ?? '' ) ) === 'attachment';

        if ( isset( $part->parts ) && is_array( $part->parts ) && $part->parts !== [] ) {
            foreach ( $part->parts as $index => $childPart ) {
                if ( !is_object( $childPart ) ) {
                    continue;
                }

                $childSection = $section === '' ? (string) ( $index + 1 ) : $section . '.' . ( $index + 1 );
                $this->walkParts( $stream, $uid, $childPart, $childSection, $result );
            }

            if ( $section === '' && (int) ( $part->type ?? -1 ) === 0 && $result['text_body'] === NULL ) {
                $body = $this->decodeBody(
                    $this->runtime->fetchBody( $stream, $uid, '1', $this->imapUidFlag() ),
                    (int) ( $part->encoding ?? 0 )
                );

                if ( $body !== '' ) {
                    $result['text_body'] = $body;
                }
            }

            return;
        }

        $bodySection = $section !== '' ? $section : '1';
        $body = $this->decodeBody(
            $this->runtime->fetchBody( $stream, $uid, $bodySection, $this->imapUidFlag() ),
            (int) ( $part->encoding ?? 0 )
        );
        $subtype = strtolower( (string) ( $part->subtype ?? '' ) );

        if ( $isAttachment ) {
            $filename = $parameters['filename'] ?? $parameters['name'] ?? NULL;
            $contentType = $this->partContentType( $part, $subtype );

            $result['attachments'][] = [
                'filename' => $filename,
                'content_type' => $contentType,
                'content' => $this->attachmentCanContainText( $contentType, $filename ) ? $body : NULL,
            ];

            return;
        }

        if ( (int) ( $part->type ?? -1 ) === 0 && $subtype === 'plain' && $result['text_body'] === NULL ) {
            $result['text_body'] = $body !== '' ? $body : NULL;
        }

        if ( (int) ( $part->type ?? -1 ) === 0 && $subtype === 'html' && $result['html_body'] === NULL ) {
            $result['html_body'] = $body !== '' ? $body : NULL;
        }
    }

    /**
     * @param object $part
     *
     * @return array<string, string>
     */
    private function partParameters( object $part ): array {
        $parameters = [];

        foreach ( [ 'parameters', 'dparameters' ] as $property ) {
            $list = $part->{$property} ?? [];

            if ( !is_array( $list ) ) {
                continue;
            }

            foreach ( $list as $parameter ) {
                if ( !is_object( $parameter ) || !isset( $parameter->attribute, $parameter->value ) ) {
                    continue;
                }

                $parameters[strtolower( (string) $parameter->attribute )] = $this->decodeMimeHeader( (string) $parameter->value );
            }
        }

        return $parameters;
    }

    private function partContentType( object $part, string $subtype ): ?string {
        $primaryTypes = [
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            7 => 'other',
        ];

        $type = $primaryTypes[(int) ( $part->type ?? 7 )] ?? 'other';

        return $subtype !== '' ? strtolower( $type . '/' . $subtype ) : NULL;
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

    private function decodeBody( string $body, int $encoding ): string {
        if ( $body === '' ) {
            return '';
        }

        if ( $encoding === 3 ) {
            $decoded = base64_decode( $body, TRUE );

            return $decoded !== FALSE ? $decoded : '';
        }

        if ( $encoding === 4 ) {
            return quoted_printable_decode( $body );
        }

        return $body;
    }

    private function decodeMimeHeader( string $value ): string {
        if ( trim( $value ) === '' ) {
            return '';
        }

        if ( function_exists( 'imap_mime_header_decode' ) ) {
            $parts = imap_mime_header_decode( $value );

            if ( is_array( $parts ) ) {
                $decoded = '';

                foreach ( $parts as $part ) {
                    if ( is_object( $part ) && isset( $part->text ) ) {
                        $decoded .= (string) $part->text;
                    }
                }

                if ( $decoded !== '' ) {
                    return $decoded;
                }
            }
        }

        return $value;
    }

    private function normalizeAddress( ?string $headerValue ): ?string {
        if ( $headerValue === NULL || trim( $headerValue ) === '' ) {
            return NULL;
        }

        $decoded = $this->decodeMimeHeader( $headerValue );

        if ( preg_match( '/<([^>]+)>/', $decoded, $matches ) === 1 ) {
            $decoded = $matches[1];
        }

        $decoded = strtolower( trim( $decoded, " \t\n\r\0\x0B<>" ) );

        return $decoded !== '' ? $decoded : NULL;
    }

    private function imapUidFlag(): int {
        return defined( 'FT_UID' ) ? (int) FT_UID : 1;
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
