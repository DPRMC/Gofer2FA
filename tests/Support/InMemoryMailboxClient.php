<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Support;

use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

class InMemoryMailboxClient implements MailboxClientInterface {
    /**
     * @var array<int, MailboxMessageInterface>
     */
    private array $messages;

    /**
     * @var array<int, MessageQuery>
     */
    private array $queries = [];

    /**
     * @param array<int, MailboxMessageInterface> $messages
     */
    public function __construct( array $messages ) {
        $this->messages = $messages;
    }

    public function findMessages( MessageQuery $query ): iterable {
        $this->queries[] = $query;

        return $this->messages;
    }

    /**
     * @return array<int, MessageQuery>
     */
    public function queries(): array {
        return $this->queries;
    }
}
