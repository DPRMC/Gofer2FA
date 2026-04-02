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
use DPRMC\Gofer2FA\Sites\GitHubChallengeSite;
use DPRMC\Gofer2FA\Sites\GoogleChallengeSite;
use DPRMC\Gofer2FA\Sites\MicrosoftChallengeSite;
use DPRMC\Gofer2FA\Sites\OktaChallengeSite;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use DPRMC\Gofer2FA\ValueObjects\TwoFactorCode;

class Gofer2FA {
    private MailboxClientInterface $mailboxClient;
    private ChallengeSiteRegistry  $siteRegistry;

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
    public function fetchCode( string $siteKey, ?DateTimeInterface $since = NULL, int $limit = 25 ): ?TwoFactorCode {
        $site = $this->siteRegistry->get( $siteKey );
        $query = $site instanceof MessageMatchingChallengeSiteInterface
            ? $site->messageQuery( $since, $limit )
            : new MessageQuery( $site->senderAddresses(), $since, $limit );

        foreach ( $this->mailboxClient->findMessages( $query ) as $message ) {
            if ( !$this->messageMatchesSite( $message, $site, $since ) ) {
                continue;
            }

            $code = $site->parseCode( $message );

            if ( $code !== NULL && $code !== '' ) {
                return new TwoFactorCode(
                    $site->key(),
                    $code,
                    $message->getId(),
                    $message->getFromAddress(),
                    $message->getSubject(),
                    $message->getReceivedAt()
                );
            }
        }

        return NULL;
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

        do {
            $code = $this->fetchCode( $siteKey, $startedAt, $limit );

            if ( $code !== NULL ) {
                return $code;
            }

            if ( $this->asImmutable() >= $deadline ) {
                break;
            }

            sleep( $pollIntervalSeconds );
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
}
