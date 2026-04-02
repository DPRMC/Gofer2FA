<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Tests;

use DPRMC\Gofer2FA\Adapters\ArrayMailboxAttachment;
use PHPUnit\Framework\TestCase;

class ArrayMailboxAttachmentTest extends TestCase {
    public function testItNormalizesAttachmentAttributes(): void {
        $attachment = new ArrayMailboxAttachment( [
            'filename' => ' code.html ',
            'content_type' => 'Text/HTML ',
            'content' => '<p>Verification code 123456</p>',
        ] );

        $this->assertSame( 'code.html', $attachment->getFilename() );
        $this->assertSame( 'text/html', $attachment->getContentType() );
        $this->assertSame( '<p>Verification code 123456</p>', $attachment->getContent() );
    }
}
