<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests\Support;

use DPRMC\Gofer2FA\Contracts\Pop3RuntimeInterface;

class FakePop3Runtime implements Pop3RuntimeInterface {
    /**
     * @var array<int, array{number:int,size:int}>
     */
    public array $messages = [];
    /**
     * @var array<int, string>
     */
    public array $rawMessages = [];
    public bool $opened = FALSE;
    public bool $authenticated = FALSE;
    public bool $closed = FALSE;
    public bool $lastUseTls = FALSE;
    public bool $lastUseStartTls = FALSE;

    /**
     * @return string
     */
    public function open( string $host, int $port, bool $useTls, bool $useStartTls = FALSE, int $timeout = 30 ) {
        $this->opened = TRUE;
        $this->lastUseTls = $useTls;
        $this->lastUseStartTls = $useStartTls;

        return 'fake-pop3-connection';
    }

    /**
     * @param mixed $connection
     */
    public function authenticate( $connection, string $username, string $password ): void {
        $this->authenticated = TRUE;
    }

    /**
     * @param mixed $connection
     *
     * @return array<int, array{number:int,size:int}>
     */
    public function listMessages( $connection ): array {
        return $this->messages;
    }

    /**
     * @param mixed $connection
     */
    public function retrieveMessage( $connection, int $messageNumber ): string {
        return $this->rawMessages[$messageNumber] ?? '';
    }

    /**
     * @param mixed $connection
     */
    public function close( $connection ): void {
        $this->closed = TRUE;
    }
}
