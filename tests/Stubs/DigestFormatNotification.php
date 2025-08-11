<?php

namespace Humweb\Notifications\Tests\Stubs;

use Illuminate\Notifications\Notification;

class DigestFormatNotification extends Notification
{
    public string $first;

    public string $second;

    public function __construct(string $first, string $second)
    {
        $this->first = $first;
        $this->second = $second;
    }

    public function toDigestFormat($notifiable, $data): string
    {
        return "Digest for {$this->first} and {$this->second}";
    }
}
