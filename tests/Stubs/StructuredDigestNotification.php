<?php

namespace Humweb\Notifications\Tests\Stubs;

use Humweb\Notifications\Digest\DigestMessage;
use Illuminate\Notifications\Notification;

class StructuredDigestNotification extends Notification
{
    public string $first;

    public string $second;

    public function __construct(string $first, string $second)
    {
        $this->first = $first;
        $this->second = $second;
    }

    public function toDigest($notifiable, DigestMessage $digest, $data): void
    {
        $digest->heading('New Activity')
            ->line("Digest for {$this->first} and {$this->second}")
            ->button('View', 'https://example.com')
            ->bulletList(['Alpha detail', 'Beta detail']);
    }
}
