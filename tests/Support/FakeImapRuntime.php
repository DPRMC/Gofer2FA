<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Support;

use DPRMC\Gofer2FA\Contracts\ImapRuntimeInterface;

class FakeImapRuntime implements ImapRuntimeInterface {
    /**
     * @var array<int, int>
     */
    public array $searchResults = [];
    /**
     * @var array<int, array<int, object>>
     */
    public array $overviews = [];
    /**
     * @var array<int, object|null>
     */
    public array $structures = [];
    /**
     * @var array<string, string>
     */
    public array $bodies = [];
    /**
     * @var array<int, string>
     */
    public array $searchCriteria = [];
    public bool $opened = FALSE;
    public bool $closed = FALSE;
    /**
     * @var array<int, int>
     */
    public array $deletedMessages = [];
    public int $expungeCount = 0;

    /**
     * @param array<string, mixed> $parameters
     *
     * @return string
     */
    public function open(
        string $mailbox,
        string $username,
        string $password,
        int    $options = 0,
        int    $retries = 0,
        array  $parameters = []
    ) {
        $this->opened = TRUE;

        return 'fake-imap-stream';
    }

    /**
     * @param mixed $stream
     */
    public function close( $stream ): void {
        $this->closed = TRUE;
    }

    /**
     * @param mixed $stream
     *
     * @return array<int, int>
     */
    public function search( $stream, string $criteria ): array {
        $this->searchCriteria[] = $criteria;

        return $this->searchResults;
    }

    /**
     * @param mixed $stream
     *
     * @return array<int, object>
     */
    public function fetchOverview( $stream, string $sequence, int $options = 0 ): array {
        return $this->overviews[(int) $sequence] ?? [];
    }

    /**
     * @param mixed $stream
     *
     * @return object|null
     */
    public function fetchStructure( $stream, int $messageNumber ) {
        return $this->structures[$messageNumber] ?? NULL;
    }

    /**
     * @param mixed $stream
     */
    public function fetchBody( $stream, int $messageNumber, string $section, int $options = 0 ): string {
        return $this->bodies[$messageNumber . ':' . $section] ?? '';
    }

    /**
     * @param mixed $stream
     */
    public function deleteMessage( $stream, int $messageNumber, int $options = 0 ): void {
        $this->deletedMessages[] = $messageNumber;
    }

    /**
     * @param mixed $stream
     */
    public function expunge( $stream ): void {
        $this->expungeCount++;
    }
}
