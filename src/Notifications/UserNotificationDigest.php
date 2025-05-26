<?php

namespace Humweb\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class UserNotificationDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public string $channel;

    public Collection $pendingNotificationsData;

    /**
     * Create a new notification instance.
     *
     * @param  string  $channel  The channel this digest is for (e.g., 'mail', 'database')
     * @param  Collection  $pendingNotificationsData  Collection of pending notification items
     */
    public function __construct(string $channel, Collection $pendingNotificationsData)
    {
        $this->channel = $channel;
        $this->pendingNotificationsData = $pendingNotificationsData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        // The digest is already targeted for a specific channel by the SendNotificationDigests command.
        return [$this->channel];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Your Notification Digest')
            ->line('Here is a summary of your recent notifications:');

        foreach ($this->pendingNotificationsData as $item) {
            // Attempt to get a human-readable summary for each pending item.
            // This is a basic example. You'd likely want more sophisticated rendering
            // based on $item['class'] and $item['data'].
            $notificationInstance = null;
            if (class_exists($item['class'])) {
                try {
                    // Create the original notification instance with its data
                    // The data stored should be the constructor arguments array
                    $notificationInstance = new $item['class'](...(is_array($item['data']) ? $item['data'] : [$item['data']]));
                } catch (\Throwable $e) {
                    // Could not instantiate, perhaps constructor args changed or class is complex
                }
            }

            $summary = 'Notification: '.class_basename($item['class']).' (Received: '.$item['created_at']->format('Y-m-d H:i').')';

            // If the original notification has a toText() or toArray() method, you could use it.
            // For now, a generic line.
            if ($notificationInstance && method_exists($notificationInstance, 'toDigestFormat')) {
                $summary = $notificationInstance->toDigestFormat($notifiable, $item['data']);
                $mailMessage->line($summary);
            } elseif ($notificationInstance && method_exists($notificationInstance, 'toArray')) {
                // Fallback to a generic array representation if available
                $arrayData = $notificationInstance->toArray($notifiable);
                $mailMessage->line('Update: '.($arrayData['title'] ?? class_basename($item['class'])).' - '.($arrayData['message'] ?? 'Details in app.'));
            } else {
                $mailMessage->line($summary);
            }
            $mailMessage->line('---'); // Separator
        }

        $mailMessage->line('You can manage your notification preferences in your profile settings.');

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        // This is for database type digests
        return [
            'title' => 'Your Notification Digest',
            'summary' => 'You have '.$this->pendingNotificationsData->count().' new notifications.',
            'items' => $this->pendingNotificationsData->map(function ($item) {
                return [
                    'type' => class_basename($item['class']),
                    'data' => $item['data'], // The raw data, frontend can decide how to render
                    'received_at' => $item['created_at']->toIso8601String(),
                    // Consider adding a method to original notifications like `toDigestEntry()`
                    // for a structured summary if `data` is too raw for generic display.
                ];
            })->all(),
        ];
    }

    // TODO: Add toBroadcast, toVonage, etc. methods if digests need to go via other channels.
}
