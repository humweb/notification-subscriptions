<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Humweb\Notifications\Facades\NotificationSubscriptions as NotificationSubscriptionsManager;

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
        $availableTypes = NotificationSubscriptionsManager::getSubscribableNotificationTypes();

        $subscriptions = collect($availableTypes)->map(function ($details, $type) use ($user) {
            return [
                'type' => $type,
                'label' => $details['label'] ?? $type,
                'description' => $details['description'] ?? '',
                'subscribed' => $user ? $user->isSubscribedTo($type) : false,
                // We can also pass the configured notification class if needed by the frontend, though unlikely for this UI
                // 'class' => $details['class'] ?? null,
            ];
        })->values(); // Ensure it's an array for Vue props

        return Inertia::render('Profile/NotificationSettings', [
            'subscriptionsData' => $subscriptions,
        ]);
    }

    /**
     * Update the user's notification subscription status for a given type.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => ['required', 'string'],
            'subscribed' => ['required', 'boolean'],
        ]);

        $user = Auth::user();
        $type = $request->input('type');
        $subscribed = $request->input('subscribed');

        // Validate that the type is actually defined in the config
        $definedTypes = NotificationSubscriptionsManager::getSubscribableNotificationTypes();
        if (!array_key_exists($type, $definedTypes)) {
            return back()->withErrors(['type' => 'Invalid notification type.']);
        }

        if ($subscribed) {
            $user->subscribe($type);
        } else {
            $user->unsubscribe($type);
        }

        return back()->with('success', 'Notification settings updated.');
    }
} 
