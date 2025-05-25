<?php

namespace Humweb\Notifications\Console;

use Humweb\Notifications\Models\NotificationSubscription;
use Humweb\Notifications\Models\PendingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Config;

class SendNotificationDigests extends Command
{
    protected $signature = 'notifications:send-digests';
    protected $description = 'Process and send scheduled notification digests.';

    public function handle(): void
    {
        $this->info('Processing notification digests...');

        $now = Carbon::now();

        // Fetch subscriptions that are due for a digest
        // This query can be complex and might need optimization based on DB size
        $dueSubscriptions = NotificationSubscription::query()
            ->where('digest_interval', '!= ', 'immediate')
            ->where(function ($query) use ($now) {
                // Daily digests
                $query->where(function ($q) use ($now) {
                    $q->where('digest_interval', 'daily')
                      ->whereNotNull('digest_at_time')
                      ->whereTime('digest_at_time', ' <=', $now->format('H:i:s'))
                      ->where(function ($subQ) use ($now) {
                          $subQ->whereNull('last_digest_sent_at')
                               ->orWhereDate('last_digest_sent_at', '<', $now->toDateString());
                      });
                })
                // Weekly digests
                ->orWhere(function ($q) use ($now) {
                    $q->where('digest_interval', 'weekly')
                      ->whereNotNull('digest_at_day')
                      ->whereNotNull('digest_at_time')
                      ->where('digest_at_day', strtolower($now->format('l'))) // 'l' gives full day name, e.g., Monday
                      ->whereTime('digest_at_time', ' <=', $now->format('H:i:s'))
                      ->where(function ($subQ) use ($now) {
                            // Check if last sent was before this week's digest slot
                            $subQ->whereNull('last_digest_sent_at')
                                 ->orWhere('last_digest_sent_at', '<', $now->copy()->startOfWeek()->modify('next '.$q->getModel()->digest_at_day)->setTimeFromTimeString($q->getModel()->digest_at_time));
                      });
                });
            })
            ->get();

        if ($dueSubscriptions->isEmpty()) {
            $this->info('No digests due at this time.');
            return;
        }

        $this->info("Found {" . $dueSubscriptions->count() . "} subscriptions due for digest.");

        foreach ($dueSubscriptions as $subscription) {
            $pending = PendingNotification::where('user_id', $subscription->user_id)
                ->where('notification_type', $subscription->type)
                ->where('channel', $subscription->channel)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($pending->isEmpty()) {
                // Update last_digest_sent_at even if nothing to send to prevent re-processing immediately
                $subscription->update(['last_digest_sent_at' => $now]);
                continue;
            }

            $this->line("Processing digest for User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}. {" . $pending->count() . "} items.");

            // Group pending notifications by their original notification class
            // to potentially create more structured digest notifications.
            // For now, we'll pass all as a single collection.
            $notificationDataForDigest = $pending->map(function ($item) {
                return [
                    'class' => $item->notification_class,
                    'data' => $item->notification_data,
                    'created_at' => $item->created_at
                ];
            });

            // Dynamically create and dispatch the digest notification
            // You'll need to create a `UserNotificationDigest` notification class
            $digestNotificationClass = Config::get('notification-subscriptions.digest_notification_class', 'App\Notifications\UserNotificationDigest'); 
            
            if (!class_exists($digestNotificationClass)) {
                $this->error("Digest notification class '{$digestNotificationClass}' not found. Skipping digest for User ID: {$subscription->user_id}.");
                continue;
            }

            try {
                $recipient = $subscription->user;
                if (!$recipient) {
                    $this->warn("User not found for subscription ID: {$subscription->id}. Skipping.");
                    // Clean up orphaned pending notifications if user is gone?
                    // PendingNotification::where('user_id', $subscription->user_id)->delete();
                    continue;
                }

                // The UserNotificationDigest should be designed to accept the channel and data
                LaravelNotification::send($recipient, new $digestNotificationClass($subscription->channel, $notificationDataForDigest));
                
                // Delete sent pending notifications
                PendingNotification::whereIn('id', $pending->pluck('id'))->delete();

                // Update last sent timestamp
                $subscription->update(['last_digest_sent_at' => $now]);

                $this->info("Digest sent for User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}");

            } catch (\Exception $e) {
                $this->error("Failed to send digest for User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}. Error: " . $e->getMessage());
                // Optionally, log the full error or re-queue with backoff
            }
        }

        $this->info('Digest processing complete.');
    }
} 
