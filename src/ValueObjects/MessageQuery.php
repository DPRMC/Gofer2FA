<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\ValueObjects;

use DateTimeInterface;

class MessageQuery {
    /**
     * @var string[]
     */
    private array $fromAddresses;
    private ?DateTimeInterface $since;
    private int $limit;

    /**
     * Create a normalized mailbox query value object.
     *
     * @param string[] $fromAddresses
     */
    public function __construct( array $fromAddresses = [], ?DateTimeInterface $since = NULL, int $limit = 25 ) {
        $normalized = [];

        foreach ( $fromAddresses as $fromAddress ) {
            $fromAddress = strtolower( trim( (string) $fromAddress ) );

            if ( $fromAddress !== '' ) {
                $normalized[] = $fromAddress;
            }
        }

        $this->fromAddresses = array_values( array_unique( $normalized ) );
        $this->since = $since;
        $this->limit = $limit > 0 ? $limit : 25;
    }

    /**
     * Return the normalized sender addresses to filter on.
     *
     * @return string[]
     */
    public function fromAddresses(): array {
        return $this->fromAddresses;
    }

    /**
     * Return the lower bound for message received timestamps.
     */
    public function since(): ?DateTimeInterface {
        return $this->since;
    }

    /**
     * Return the suggested message search limit.
     */
    public function limit(): int {
        return $this->limit;
    }

    /**
     * Return a copy of the query with a different received-after timestamp.
     */
    public function withSince( ?DateTimeInterface $since ): self {
        return new self( $this->fromAddresses, $since, $this->limit );
    }

    /**
     * Return a copy of the query with a different search limit.
     */
    public function withLimit( int $limit ): self {
        return new self( $this->fromAddresses, $this->since, $limit );
    }
}
