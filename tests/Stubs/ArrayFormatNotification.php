<?php

namespace Humweb\Notifications\Tests\Stubs;

use Illuminate\Notifications\Notification;

class ArrayFormatNotification extends Notification
{
    public string $title;
    public string $message;

    // Constructor designed for associative/named args
    public function __construct(string $title, string $message)
    {
        $this->title = $title;
        $this->message = $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
