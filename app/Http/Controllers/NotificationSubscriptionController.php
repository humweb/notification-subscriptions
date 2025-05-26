<?php

namespace App\Http\Controllers;

use Humweb\Notifications\Facades\NotificationSubscriptions as NotificationSubscriptionsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationSubscriptionController extends Controller
{
    /**
     * Display a listing of the notification subscription settings for the current user.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();
        $definedNotificationTypes = NotificationSubscriptionsManager::getSubscribableNotificationTypes();
        $availableDigestIntervals = Config::get('notification-subscriptions.digest_intervals', []);

        // Prepare settings in the structure expected by the Vue component in README
        $notificationSettings = collect($definedNotificationTypes)->mapWithKeys(function ($typeDetails, $typeKey) use ($user) {
            $channels = collect($typeDetails['channels'] ?? [])->map(function ($channelConfig) use ($user, $typeKey) {
                $subscription = $user ? $user->getSubscriptionDetails($typeKey, $channelConfig['name']) : null;
                $digestAtTime = $subscription->digest_at_time ?? '09:00:00'; // Default with seconds

                return [
                    'name' => $channelConfig['name'],
                    'label' => $channelConfig['label'],
                    'subscribed' => (bool) $subscription,
                    'digest_interval' => $subscription->digest_interval ?? 'immediate',
                    // Format time to HH:MM for the input field
                    'digest_at_time' => $digestAtTime ? substr($digestAtTime, 0, 5) : '09:00',
                    'digest_at_day' => $subscription->digest_at_day ?? 'monday', // Default day
                ];
            })->all();

            return [$typeKey => [
                'label' => $typeDetails['label'] ?? $typeKey,
                'description' => $typeDetails['description'] ?? '',
                'channels' => $channels,
            ]];
        })->all();

        return Inertia::render('Profile/NotificationSettings', [
            'notificationSettings' => $notificationSettings, // Changed from subscriptionsData
            'availableDigestIntervals' => $availableDigestIntervals,
            'availableDaysOfWeek' => [ // Added as per README example
                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
            ],
        ]);
    }

    /**
     * Update the user's notification subscription status for a given type.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $definedNotificationTypes = NotificationSubscriptionsManager::getSubscribableNotificationTypes();
        $availableDigestIntervals = Config::get('notification-subscriptions.digest_intervals', []);

        $validatedData = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($definedNotificationTypes))],
            'channel' => ['required', 'string'],
            'subscribed' => ['required', 'boolean'],
            'digest_interval' => ['required', 'string', Rule::in(array_keys($availableDigestIntervals))],
            'digest_at_time' => ['nullable', 'required_if:digest_interval,daily', 'required_if:digest_interval,weekly', 'date_format:H:i'],
            'digest_at_day' => ['nullable', 'required_if:digest_interval,weekly', 'string', 'alpha', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
        ]);

        $type = $validatedData['type'];
        $channelName = $validatedData['channel'];
        $isSubscribing = $validatedData['subscribed'];

        $typeConfig = $definedNotificationTypes[$type] ?? null;
        $allowedChannels = collect($typeConfig['channels'] ?? [])->pluck('name')->all();

        if (! $typeConfig || ! in_array($channelName, $allowedChannels)) {
            return back()->withErrors(['channel' => 'Invalid channel for the notification type.'])->withInput();
        }

        if ($isSubscribing) {
            $digestInterval = $validatedData['digest_interval'];
            $digestAtTime = $validatedData['digest_at_time'];
            $digestAtDay = $validatedData['digest_at_day'];
            
            // Ensure time has seconds for storage if provided
            if ($digestAtTime && count(explode(':', $digestAtTime)) === 2) {
                $digestAtTime .= ':00';
            }

            $user->subscribe($type, $channelName, $digestInterval, $digestAtTime, $digestAtDay);
        } else {
            $user->unsubscribe($type, $channelName);
        }

        return back()->with('success', 'Notification settings updated.');
    }
}
