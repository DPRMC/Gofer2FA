<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Adapters;

use DPRMC\Gofer2FA\Contracts\MailboxClientInterface;
use DPRMC\Gofer2FA\Contracts\MailboxMessageInterface;
use DPRMC\Gofer2FA\ValueObjects\MessageQuery;
use UnexpectedValueException;

/**
 * Mailbox client adapter that delegates inbox lookups to an application-provided callback.
 *
 * This is the main integration point for Laravel applications that already have mailbox access logic.
 * `Gofer2FA` builds a `MessageQuery`, this client passes it to the callback, and returned payloads are
 * normalized into mailbox message objects for the parser flow.
 */
class CallbackMailboxClient implements MailboxClientInterface {
    /**
     * @var callable
     */
    private $resolver;

    /**
     * Create a mailbox client backed by an application-provided callback.
     */
    public function __construct( callable $resolver ) {
        $this->resolver = $resolver;
    }

    /**
     * Execute the callback and normalize each returned message payload.
     */
    public function findMessages( MessageQuery $query ): iterable {
        $messages = call_user_func( $this->resolver, $query );

        foreach ( $messages as $message ) {
            yield $this->normalizeMessage( $message );
        }
    }

    /**
     * @param mixed $message
     */
    private function normalizeMessage( $message ): MailboxMessageInterface {
        if ( $message instanceof MailboxMessageInterface ) {
            return $message;
        }

        if ( is_array( $message ) ) {
            return new ArrayMailboxMessage( $message );
        }

        throw new UnexpectedValueException( 'Mailbox resolvers must return MailboxMessageInterface instances or arrays.' );
    }
}
