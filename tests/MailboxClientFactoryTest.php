<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DPRMC\Gofer2FA\Adapters\CallbackMailboxClient;
use DPRMC\Gofer2FA\MailboxClientFactory;
use DPRMC\Gofer2FA\MailboxClients\GmailApiMailboxClient;
use DPRMC\Gofer2FA\MailboxClients\ImapMailboxClient;
use DPRMC\Gofer2FA\MailboxClients\Office365GraphMailboxClient;
use DPRMC\Gofer2FA\MailboxClients\Pop3MailboxClient;
use DPRMC\Gofer2FA\MailboxClients\SesS3MailboxClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MailboxClientFactoryTest extends TestCase {
    public function testItBuildsSupportedMailboxDrivers(): void {
        $factory = new MailboxClientFactory();

        $this->assertInstanceOf( Office365GraphMailboxClient::class, $factory->make( [
            'driver' => 'office365_graph',
            'tenant' => 'tenant',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'mailbox_user' => 'mailbox@example.com',
        ] ) );

        $this->assertInstanceOf( ImapMailboxClient::class, $factory->make( [
            'driver' => 'imap',
            'mailbox' => '{imap.example.com:993/imap/ssl}INBOX',
            'username' => 'user@example.com',
            'password' => 'secret',
        ] ) );

        $this->assertInstanceOf( GmailApiMailboxClient::class, $factory->make( [
            'driver' => 'gmail_api',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'refresh_token' => 'refresh-token',
        ] ) );

        $this->assertInstanceOf( SesS3MailboxClient::class, $factory->make( [
            'driver' => 'ses_s3',
            'access_key_id' => 'aws-key',
            'secret_access_key' => 'aws-secret',
            'region' => 'us-east-1',
            'bucket' => 'mail-bucket',
        ] ) );

        $this->assertInstanceOf( Pop3MailboxClient::class, $factory->make( [
            'driver' => 'pop3',
            'host' => 'pop.example.com',
            'username' => 'user@example.com',
            'password' => 'secret',
        ] ) );

        $this->assertInstanceOf( CallbackMailboxClient::class, $factory->make( [
            'driver' => 'callback',
            'resolver' => static fn(): array => [],
        ] ) );
    }

    public function testItRejectsUnsupportedDrivers(): void {
        $this->expectException( InvalidArgumentException::class );

        ( new MailboxClientFactory() )->make( [ 'driver' => 'unknown' ] );
    }
}
