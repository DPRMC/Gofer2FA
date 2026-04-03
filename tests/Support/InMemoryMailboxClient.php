<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Support;

use DPRMC\Gofer2FA\Contracts\DeletableMailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;

class InMemoryMailboxClient implements DeletableMailboxClientInterface {
    /**
     * @var array<int, MailboxMessageInterface>
     */
    private array $messages;

    /**
     * @var array<int, MessageQuery>
     */
    private array $queries = [];
    /**
     * @var array<int, string>
     */
    private array $deletedMessageIds = [];

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

    /**
     * Delete a message by id from the in-memory mailbox.
     */
    public function deleteMessage( string $messageId ): void {
        $this->deletedMessageIds[] = $messageId;
        $this->messages = array_values( array_filter(
            $this->messages,
            static fn( MailboxMessageInterface $message ): bool => $message->getId() !== $messageId
        ) );
    }

    /**
     * @return array<int, string>
     */
    public function deletedMessageIds(): array {
        return $this->deletedMessageIds;
    }
}
