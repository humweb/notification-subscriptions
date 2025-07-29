<?php

namespace Humweb\Notifications\Console;

use Humweb\Notifications\Models\NotificationSubscription;
use Humweb\Notifications\Models\PendingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as LaravelNotification;

class SendNotificationDigests extends Command
{
    protected $signature = 'notifications:send-digests';

    protected $description = 'Process and send scheduled notification digests.';

    public function handle(): void
    {
        Log::info('SendNotificationDigests command started.');
        $this->info('Processing notification digests...');

        $now = Carbon::now();
        Log::info('Current time (Carbon::now()): '.$now->toDateTimeString());

        // Fetch subscriptions that are due for a digest
        $dueSubscriptionsQuery = NotificationSubscription::query()
            ->where(function ($outerQuery) use ($now) {
                $outerQuery->where(function ($dailyQuery) use ($now) {
                    // Daily digests
                    $dailyQuery->where('digest_interval', 'daily')
                        ->whereTime('digest_at_time', '<=', $now->toTimeString())
                        ->where(function ($subQ) use ($now) {
                            $subQ->whereNull('last_digest_sent_at')
                                ->orWhereDate('last_digest_sent_at', '<', $now->toDateString())
                                ->orWhere(function ($todayQuery) {
                                    $todayQuery->whereDate('last_digest_sent_at', now()->toDateString())
                                        ->whereTime('last_digest_sent_at', '<', DB::raw('digest_at_time'));
                                });
                        });
                })->orWhere(function ($weeklyQuery) use ($now) {
                    // Weekly digests
                    $weeklyQuery->where('digest_interval', 'weekly')
                        ->whereRaw('LOWER(digest_at_day) = ?', [strtolower($now->format('l'))])
                        ->whereTime('digest_at_time', '<=', $now->toTimeString())
                        ->where(function ($subQ) use ($now) {
                            // Calculate date in PHP to avoid DB-specific date functions
                            $sixDaysAgo = $now->copy()->subDays(6);
                            $subQ->whereNull('last_digest_sent_at')
                                ->orWhere('last_digest_sent_at', '<', $sixDaysAgo);
                        });
                });
            });

        $dueSubscriptions = $dueSubscriptionsQuery->get();

        if ($dueSubscriptions->isEmpty()) {
            $this->info('No digests due at this time.');

            return;
        }

        $this->info('Found {'.$dueSubscriptions->count().'} subscriptions due for digest.');
        Log::info('Found '.$dueSubscriptions->count().' subscriptions due for digest. IDs: '.$dueSubscriptions->pluck('id')->implode(', '));

        foreach ($dueSubscriptions as $subscription) {
            Log::info("Processing subscription ID: {$subscription->id}, User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}, Digest Interval: {$subscription->digest_interval}, Digest At Time: {$subscription->digest_at_time}, Digest At Day: {$subscription->digest_at_day}, Last Sent: {$subscription->last_digest_sent_at}");

            $pending = PendingNotification::where('user_id', $subscription->user_id)
                ->where('notification_type', $subscription->type)
                ->where('channel', $subscription->channel)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($pending->isEmpty()) {
                Log::info("No pending notifications for subscription ID: {$subscription->id}. Updating last_digest_sent_at to: ".$now->toDateTimeString());
                $subscription->update(['last_digest_sent_at' => $now]);
                Log::info("last_digest_sent_at updated for subscription ID: {$subscription->id}. Current value in model: ".($subscription->last_digest_sent_at ? $subscription->last_digest_sent_at->toDateTimeString() : 'null'));

                continue;
            }

            $this->line("Processing digest for User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}. {".$pending->count().'} items.');
            Log::info("Found {$pending->count()} pending items for subscription ID: {$subscription->id}.");

            $notificationDataForDigest = $pending->map(function ($item) {
                return [
                    'class'      => $item->notification_class,
                    'data'       => $item->notification_data,
                    'created_at' => $item->created_at,
                ];
            });

            $digestNotificationClass = Config::get('notification-subscriptions.digest_notification_class',
                'App\\Notifications\\UserNotificationDigest');
            Log::info("Using digest notification class: {$digestNotificationClass} for subscription ID: {$subscription->id}");

            if (! class_exists($digestNotificationClass)) {
                $this->error("Digest notification class '{$digestNotificationClass}' not found. Skipping digest for User ID: {$subscription->user_id}.");
                Log::error("Digest notification class '{$digestNotificationClass}' not found for subscription ID: {$subscription->id}.");

                continue;
            }

            try {
                $recipient = $subscription->user;
                if (! $recipient) {
                    $this->warn("User not found for subscription ID: {$subscription->id}. Skipping.");
                    Log::warn("User not found for subscription ID: {$subscription->id}.");

                    continue;
                }

                Log::info("Attempting to send digest to User ID: {$recipient->id} for subscription ID: {$subscription->id} via channel: {$subscription->channel}");
                LaravelNotification::send($recipient,
                    new $digestNotificationClass($subscription->channel, $notificationDataForDigest));
                Log::info("Digest notification supposedly sent for subscription ID: {$subscription->id}.");

                PendingNotification::whereIn('id', $pending->pluck('id'))->delete();
                Log::info("Deleted {$pending->count()} pending notifications for subscription ID: {$subscription->id}.");

                $subscription->update(['last_digest_sent_at' => $now]);
                Log::info("last_digest_sent_at updated after sending digest for subscription ID: {$subscription->id} to: ".$now->toDateTimeString().'. Current value in model: '.($subscription->last_digest_sent_at ? $subscription->last_digest_sent_at->toDateTimeString() : 'null'));

                $this->info("Digest sent for User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}");

            } catch (\Exception $e) {
                $this->error("Failed to send digest for User ID: {$subscription->user_id}, Type: {$subscription->type}, Channel: {$subscription->channel}. Error: ".$e->getMessage());
                Log::error("Exception during digest sending for subscription ID: {$subscription->id}. Error: ".$e->getMessage(),
                    ['exception' => $e]);
            }
        }

        $this->info('Digest processing complete.');
        Log::info('SendNotificationDigests command finished.');
    }
}
