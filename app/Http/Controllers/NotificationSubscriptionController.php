<?php

namespace App\Http\Controllers;

use Humweb\Notifications\Facades\NotificationSubscriptions as NotificationSubscriptionsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $subscriptionsData = collect($definedNotificationTypes)->map(function ($typeDetails, $type) use ($user) {
            $configuredChannels = $typeDetails['channels'] ?? [];

            $channels = collect($configuredChannels)->map(function ($channelConfig) use ($user, $type) {
                return [
                    'name' => $channelConfig['name'],
                    'label' => $channelConfig['label'],
                    'subscribed' => $user ? $user->isSubscribedTo($type, $channelConfig['name']) : false,
                ];
            })->all(); // Use all() to get a plain array

            return [
                'type' => $type,
                'label' => $typeDetails['label'] ?? $type,
                'description' => $typeDetails['description'] ?? '',
                'channels' => $channels,
            ];
        })->values()->all(); // Ensure it's a plain array for Vue props

        return Inertia::render('Profile/NotificationSettings', [
            'subscriptionsData' => $subscriptionsData,
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

        $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($definedNotificationTypes))],
            'channel' => ['required', 'string'],
            'subscribed' => ['required', 'boolean'],
        ]);

        $type = $request->input('type');
        $channelName = $request->input('channel');
        $subscribed = $request->input('subscribed');

        // Further validate that the channel is valid for the given type
        $typeConfig = $definedNotificationTypes[$type] ?? null;
        $allowedChannels = collect($typeConfig['channels'] ?? [])->pluck('name')->all();

        if (! $typeConfig || ! in_array($channelName, $allowedChannels)) {
            return back()->withErrors(['channel' => 'Invalid channel for the notification type.'])->withInput();
        }

        if ($subscribed) {
            $user->subscribe($type, $channelName);
        } else {
            $user->unsubscribe($type, $channelName);
        }

        return back()->with('success', 'Notification settings updated.');
    }
}
