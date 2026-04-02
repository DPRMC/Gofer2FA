<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\Contracts\MessageMatchingChallengeSiteInterface;
use DPRMC\Gofer2FA\Sites\ForwardedCostarChallengeSite;
use DPRMC\Gofer2FA\Sites\GitHubChallengeSite;
use DPRMC\Gofer2FA\Sites\GoogleChallengeSite;
use DPRMC\Gofer2FA\Sites\MicrosoftChallengeSite;
use DPRMC\Gofer2FA\Sites\OktaChallengeSite;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use DPRMC\Gofer2FA\ValueObjects\TwoFactorCode;

/**
 * Main application service for finding 2FA codes in a mailbox.
 *
 * This class coordinates the full flow: resolve a registered site parser, build the mailbox query, fetch
 * candidate messages, ask the site whether each message belongs to it, and return the first successfully
 * parsed `TwoFactorCode`. It also provides polling behavior through `waitForCode()`.
 */
class Gofer2FA {
    private MailboxClientInterface $mailboxClient;
    private ChallengeSiteRegistry  $siteRegistry;
    private bool                   $debug = FALSE;

    /**
     * Create the main 2FA service with a mailbox client and optional site registry.
     */
    public function __construct( MailboxClientInterface $mailboxClient, ?ChallengeSiteRegistry $siteRegistry = NULL ) {
        $this->mailboxClient = $mailboxClient;
        $this->siteRegistry  = $siteRegistry ?: new ChallengeSiteRegistry();
    }

    /**
     * Create the service preloaded with the built-in site parsers.
     */
    public static function withDefaultSites( MailboxClientInterface $mailboxClient ): self {
        return new self( $mailboxClient, new ChallengeSiteRegistry( [
                                                                        new ForwardedCostarChallengeSite(),
                                                                        new GitHubChallengeSite(),
                                                                        new GoogleChallengeSite(),
                                                                        new MicrosoftChallengeSite(),
                                                                        new OktaChallengeSite(),
                                                                    ] ) );
    }

    /**
     * Register a single site parser.
     */
    public function registerSite( ChallengeSiteInterface $site ): self {
        $this->siteRegistry->register( $site );

        return $this;
    }

    /**
     * Register multiple site parsers in sequence.
     *
     * @param iterable<\DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface> $sites
     */
    public function registerSites( iterable $sites ): self {
        foreach ( $sites as $site ) {
            $this->registerSite( $site );
        }

        return $this;
    }

    /**
     * Enable or disable console debug output for mailbox checks.
     */
    public function setDebug( bool $debug = TRUE ): self {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Return all currently registered site parsers.
     *
     * @return array<string, \DPRMC\Gofer2FA\Contracts\ChallengeSiteInterface>
     */
    public function sites(): array {
        return $this->siteRegistry->all();
    }

    /**
     * Search the mailbox once for the most recent matching code for a site.
     */
    public function fetchCode( string $siteKey,
                               ?DateTimeInterface $since = NULL,
                               int $limit = 25 ): ?TwoFactorCode {
        $site = $this->siteRegistry->get( $siteKey );
        $query = $site instanceof MessageMatchingChallengeSiteInterface
            ? $site->messageQuery( $since, $limit )
            : new MessageQuery( $site->senderAddresses(), $since, $limit );
        $rows = [];
        $resolvedCode = NULL;

        $this->debugFetchContext( $site, $query );

        foreach ( $this->mailboxClient->findMessages( $query ) as $index => $message ) {
            $matchesSite = $this->messageMatchesSite( $message, $site, $since );
            $code = $matchesSite ? $site->parseCode( $message ) : NULL;

            $rows[] = $this->debugRow( is_int( $index ) ? $index : count( $rows ), $message, $matchesSite, $code );

            if ( $resolvedCode === NULL && $code !== NULL && $code !== '' ) {
                $resolvedCode = new TwoFactorCode(
                    $site->key(),
                    $code,
                    $message->getId(),
                    $message->getFromAddress(),
                    $message->getSubject(),
                    $message->getReceivedAt()
                );
            }
        }

        $this->debugMailboxRows( $rows );

        return $resolvedCode;
    }

    /**
     * Poll the mailbox until a matching 2FA code is found or the timeout expires.
     *
     * The search starts at `$since` when provided; otherwise it starts from the
     * moment this method is called so older messages are ignored during polling.
     *
     * @param string                 $siteKey             Registered site/parser key to search for.
     * @param int                    $timeoutSeconds      Maximum number of seconds to keep polling.
     * @param int                    $pollIntervalSeconds Number of seconds to wait between polls.
     * @param DateTimeInterface|null $since               Only consider messages received at or after this timestamp.
     * @param int                    $limit               Maximum number of messages the mailbox client should inspect per poll.
     *
     * @throws \Exception
     */
    public function waitForCode(
        string             $siteKey,
        int                $timeoutSeconds = 60,
        int                $pollIntervalSeconds = 5,
        ?DateTimeInterface $since = NULL,
        int                $limit = 25
    ): ?TwoFactorCode {
        $startedAt           = $this->asImmutable( $since ?: new DateTimeImmutable() );
        $deadline            = $this->asImmutable()->add( new DateInterval( sprintf( 'PT%dS', max( $timeoutSeconds, 1 ) ) ) );
        $pollIntervalSeconds = max( $pollIntervalSeconds, 1 );
        $attempt             = 1;

        do {
            $this->debug( sprintf( 'Gofer debug: mailbox poll attempt %d for site "%s".', $attempt, $siteKey ) );
            $code = $this->fetchCode( $siteKey, $startedAt, $limit );

            if ( $code !== NULL ) {
                return $code;
            }

            if ( $this->asImmutable() >= $deadline ) {
                break;
            }

            sleep( $pollIntervalSeconds );
            $attempt++;
        } while ( TRUE );

        return NULL;
    }

    private function messageMatchesSite(
        MailboxMessageInterface $message,
        ChallengeSiteInterface  $site,
        ?DateTimeInterface      $since = NULL
    ): bool {
        if ( $site instanceof MessageMatchingChallengeSiteInterface && !$site->matchesMessage( $message ) ) {
            return FALSE;
        }

        if ( !$site instanceof MessageMatchingChallengeSiteInterface ) {
            $fromAddress = strtolower( trim( (string) $message->getFromAddress() ) );
            $expectedSenders = array_map( 'strtolower', $site->senderAddresses() );

            if ( $fromAddress === '' || !in_array( $fromAddress, $expectedSenders, TRUE ) ) {
                return FALSE;
            }
        }

        if ( $since === NULL || $message->getReceivedAt() === NULL ) {
            return TRUE;
        }

        return $message->getReceivedAt() >= $since;
    }

    private function asImmutable( ?DateTimeInterface $value = NULL ): DateTimeImmutable {
        if ( $value instanceof DateTimeImmutable ) {
            return $value;
        }

        if ( $value instanceof DateTimeInterface ) {
            return new DateTimeImmutable( $value->format( DateTimeInterface::ATOM ) );
        }

        return new DateTimeImmutable();
    }

    private function debugFetchContext( ChallengeSiteInterface $site, MessageQuery $query ): void {
        $this->debug( sprintf( 'Gofer debug: parser %s for site "%s".', get_class( $site ), $site->key() ) );
        $this->debug( sprintf(
            'Gofer debug: mailbox filters from=%s to=%s since=%s limit=%d',
            $this->debugList( $query->fromAddresses() ),
            $this->debugList( $query->toAddresses() ),
            $query->since() ? $query->since()->format( DATE_ATOM ) : 'NULL',
            $query->limit()
        ) );
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function debugMailboxRows( array $rows ): void {
        if ( !$this->debug ) {
            return;
        }

        echo "Gofer debug: mailbox rows\n";

        if ( $rows === [] ) {
            echo "(no messages returned)\n";

            return;
        }

        $columns = [
            [ 'label' => '#', 'key' => 'index', 'width' => 3 ],
            [ 'label' => 'ID', 'key' => 'id', 'width' => 18 ],
            [ 'label' => 'FROM', 'key' => 'from', 'width' => 28 ],
            [ 'label' => 'TO', 'key' => 'to', 'width' => 28 ],
            [ 'label' => 'RECEIVED', 'key' => 'received_at', 'width' => 20 ],
            [ 'label' => 'MATCH', 'key' => 'matches_site', 'width' => 7 ],
            [ 'label' => 'CODE', 'key' => 'code', 'width' => 8 ],
            [ 'label' => 'SUBJECT', 'key' => 'subject', 'width' => 40 ],
        ];

        echo $this->debugFormatRow( $columns, array_column( $columns, 'label' ) ) . PHP_EOL;
        echo $this->debugFormatDivider( $columns ) . PHP_EOL;

        foreach ( $rows as $row ) {
            echo $this->debugFormatRow( $columns, [
                $row['index'],
                $row['id'],
                $row['from'],
                $row['to'],
                $row['received_at'],
                $row['matches_site'],
                $row['code'],
                $row['subject'],
            ] ) . PHP_EOL;
        }
    }

    private function debug( string $message ): void {
        if ( !$this->debug ) {
            return;
        }

        echo $message . PHP_EOL;
    }

    /**
     * @return array<string, string>
     */
    private function debugRow( int $index, MailboxMessageInterface $message, bool $matchesSite, ?string $code ): array {
        return [
            'index' => (string) ( $index + 1 ),
            'id' => $message->getId() ?? 'NULL',
            'from' => $message->getFromAddress() ?? 'NULL',
            'to' => $message->getToAddress() ?? 'NULL',
            'received_at' => $message->getReceivedAt() ? $message->getReceivedAt()->format( 'Y-m-d H:i:s' ) : 'NULL',
            'matches_site' => $matchesSite ? 'yes' : 'no',
            'code' => $code ?? 'NULL',
            'subject' => $message->getSubject() ?? 'NULL',
        ];
    }

    /**
     * @param array<int, array<string, int|string>> $columns
     * @param array<int, string> $values
     */
    private function debugFormatRow( array $columns, array $values ): string {
        $parts = [];

        foreach ( $columns as $index => $column ) {
            $parts[] = str_pad( $this->debugTruncate( $values[$index] ?? '', (int) $column['width'] ), (int) $column['width'] );
        }

        return implode( ' | ', $parts );
    }

    /**
     * @param array<int, array<string, int|string>> $columns
     */
    private function debugFormatDivider( array $columns ): string {
        $parts = [];

        foreach ( $columns as $column ) {
            $parts[] = str_repeat( '-', (int) $column['width'] );
        }

        return implode( '-+-', $parts );
    }

    private function debugList( array $values ): string {
        return $values === [] ? '[]' : '[' . implode( ', ', $values ) . ']';
    }

    private function debugTruncate( string $value, int $width ): string {
        $value = preg_replace( '/\s+/', ' ', trim( $value ) ) ?? '';

        if ( strlen( $value ) <= $width ) {
            return $value;
        }

        if ( $width <= 3 ) {
            return substr( $value, 0, $width );
        }

        return substr( $value, 0, $width - 3 ) . '...';
    }
}
