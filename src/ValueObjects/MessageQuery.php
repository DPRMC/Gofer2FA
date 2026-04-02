<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\ValueObjects;

use DateTimeInterface;

class MessageQuery {
    /**
     * @var string[]
     */
    private array $fromAddresses;
    /**
     * @var string[]
     */
    private array $toAddresses;
    private ?DateTimeInterface $since;
    private int $limit;

    /**
     * Create a normalized mailbox query value object.
     *
     * @param string[] $fromAddresses
     * @param string[] $toAddresses
     */
    public function __construct( array $fromAddresses = [], ?DateTimeInterface $since = NULL, int $limit = 25, array $toAddresses = [] ) {
        $this->fromAddresses = $this->normalizeAddresses( $fromAddresses );
        $this->toAddresses = $this->normalizeAddresses( $toAddresses );
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
     * Return the normalized recipient addresses to filter on.
     *
     * @return string[]
     */
    public function toAddresses(): array {
        return $this->toAddresses;
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
        return new self( $this->fromAddresses, $since, $this->limit, $this->toAddresses );
    }

    /**
     * Return a copy of the query with a different search limit.
     */
    public function withLimit( int $limit ): self {
        return new self( $this->fromAddresses, $this->since, $limit, $this->toAddresses );
    }

    /**
     * Return a copy of the query with different recipient addresses.
     *
     * @param string[] $toAddresses
     */
    public function withToAddresses( array $toAddresses ): self {
        return new self( $this->fromAddresses, $this->since, $this->limit, $toAddresses );
    }

    /**
     * @param string[] $addresses
     *
     * @return string[]
     */
    private function normalizeAddresses( array $addresses ): array {
        $normalized = [];

        foreach ( $addresses as $address ) {
            $address = strtolower( trim( (string) $address ) );

            if ( $address !== '' ) {
                $normalized[] = $address;
            }
        }

        return array_values( array_unique( $normalized ) );
    }
}
