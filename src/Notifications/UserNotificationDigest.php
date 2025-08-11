<?php

namespace Humweb\Notifications\Notifications;

use Humweb\Notifications\Digest\DigestMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Str;

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
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = Config::get('notification-subscriptions.digest_subject', 'Your Notification Digest');
        $view = Config::get('notification-subscriptions.digest_markdown_view', 'notification-subscriptions::digest');

        $mailMessage = (new MailMessage)
            ->subject($subject);

        // Add intro immediately for BC with tests expecting introLines
        $intro = 'Here is a summary of your recent notifications:';
        $mailMessage->line($intro);

        [$components, $usedStructuredComponents] = $this->compileComponents($notifiable);

        if ($usedStructuredComponents) {
            $mailMessage->markdown($view, [
                'subject' => $subject,
                'components' => $components,
            ]);
        } else {
            // Render as simple text lines for backward compatibility
            foreach ($components as $component) {
                if ($component['type'] === 'line') {
                    $mailMessage->line($component['text']);
                } elseif ($component['type'] === 'separator') {
                    $mailMessage->line('---');
                }
            }
            $mailMessage->line('You can manage your notification preferences in your profile settings.');
        }

        return $mailMessage;
    }

    /**
     * Build the digest components array and indicate if any structured components were used.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    public function compileComponents(mixed $notifiable): array
    {
        $components = [];

        foreach ($this->pendingNotificationsData as $item) {
            $notificationInstance = null;
            if (! empty($item['class']) && class_exists($item['class'])) {
                try {
                    $data = $item['data'] ?? [];
                    if (is_array($data) && ! $this->isAssoc($data)) {
                        $notificationInstance = new $item['class'](...$data);
                    } elseif (is_array($data)) {
                        $notificationInstance = app()->makeWith($item['class'], $data);
                    } else {
                        $notificationInstance = new $item['class']($data);
                    }
                } catch (\Throwable $e) {
                    // ignore instantiation issues; fall back to summary
                }
            }

            $summary = 'Notification: '.$this->toTitleCase(class_basename($item['class'])).' (Received: '.$item['created_at']->format('Y-m-d H:i').')';

            if ($notificationInstance && method_exists($notificationInstance, 'toDigest')) {
                $builder = new DigestMessage();
                $notificationInstance->toDigest($notifiable, $builder, $item['data']);
                $components = array_merge($components, $builder->components());
            } elseif ($notificationInstance && method_exists($notificationInstance, 'toDigestFormat')) {
                $text = $notificationInstance->toDigestFormat($notifiable, $item['data']);
                $components[] = ['type' => 'line', 'text' => $text];
            } elseif ($notificationInstance && method_exists($notificationInstance, 'toArray')) {
                $arrayData = $notificationInstance->toArray($notifiable);
                $text = 'Update: '.($arrayData['title'] ?? class_basename($item['class'])).' - '.($arrayData['message'] ?? 'Details in app.');
                $components[] = ['type' => 'line', 'text' => $text];
            } else {
                $components[] = ['type' => 'line', 'text' => $summary];
            }

            $components[] = ['type' => 'separator'];
        }

        $usedStructuredComponents = false;
        foreach ($components as $c) {
            if (in_array($c['type'], ['heading', 'panel', 'button', 'list'], true)) {
                $usedStructuredComponents = true;
                break;
            }
        }

        return [$components, $usedStructuredComponents];
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
                    'type' => $this->toTitleCase(class_basename($item['class'])),
                    'data' => $item['data'], // The raw data, frontend can decide how to render
                    'received_at' => $item['created_at']->toIso8601String(),
                    // Consider adding a method to original notifications like `toDigestEntry()`
                    // for a structured summary if `data` is too raw for generic display.
                ];
            })->all(),
        ];
    }

    public function toTitleCase(string $str): string
    {
        return ucwords(Str::of(Str::snake($str))->replace('_', ' '));
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
