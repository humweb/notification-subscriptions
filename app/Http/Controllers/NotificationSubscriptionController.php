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

        $subscriptionsData = collect($definedNotificationTypes)->map(function ($typeDetails, $type) use ($user) {
            $configuredChannels = $typeDetails['channels'] ?? [];

            $channels = collect($configuredChannels)->map(function ($channelConfig) use ($user, $type) {
                $subscriptionDetails = $user ? $user->getSubscriptionDetails($type, $channelConfig['name']) : null;

                return [
                    'name' => $channelConfig['name'],
                    'label' => $channelConfig['label'],
                    'subscribed' => (bool) $subscriptionDetails,
                    'digest_interval' => $subscriptionDetails->digest_interval ?? 'immediate',
                    'digest_at_time' => $subscriptionDetails->digest_at_time ?? null,
                    'digest_at_day' => $subscriptionDetails->digest_at_day ?? null,
                ];
            })->all();

            return [
                'type' => $type,
                'label' => $typeDetails['label'] ?? $type,
                'description' => $typeDetails['description'] ?? '',
                'channels' => $channels,
            ];
        })->values()->all();

        return Inertia::render('Profile/NotificationSettings', [
            'subscriptionsData' => $subscriptionsData,
            'availableDigestIntervals' => $availableDigestIntervals,
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

        $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($definedNotificationTypes))],
            'channel' => ['required', 'string'],
            'subscribed' => ['required', 'boolean'],
            'digest_interval' => ['sometimes', 'required_if:subscribed,true', Rule::in(array_keys($availableDigestIntervals))],
            'digest_at_time' => ['nullable', 'date_format:H:i', 'required_if:digest_interval,daily', 'required_if:digest_interval,weekly'],
            'digest_at_day' => ['nullable', 'string', 'alpha', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']), 'required_if:digest_interval,weekly'],
        ]);

        $type = $request->input('type');
        $channelName = $request->input('channel');
        $isSubscribing = $request->input('subscribed');

        $typeConfig = $definedNotificationTypes[$type] ?? null;
        $allowedChannels = collect($typeConfig['channels'] ?? [])->pluck('name')->all();

        if (! $typeConfig || ! in_array($channelName, $allowedChannels)) {
            return back()->withErrors(['channel' => 'Invalid channel for the notification type.'])->withInput();
        }

        if ($isSubscribing) {
            $digestInterval = $request->input('digest_interval', 'immediate');
            $digestAtTime = $request->input('digest_at_time');
            $digestAtDay = $request->input('digest_at_day');

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
