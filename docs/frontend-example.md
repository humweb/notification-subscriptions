# Frontend Example (Vue/Inertia)

Here's an example of how you might build a notification settings page using Vue and Inertia.

**Controller (`NotificationSubscriptionController.php` - example):**

You would typically create a controller to handle fetching settings and updating them.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Humweb\Notifications\Facades\NotificationSubscriptions as NotificationSettingsFacade;
use Inertia\Inertia;
use Illuminate\Support\Facades\Config; // Added for digest_intervals access
use Illuminate\Validation\Rule; // Added for validation rules

class NotificationSubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $definedNotificationTypes = NotificationSettingsFacade::getSubscribableNotificationTypes();
        // Corrected to use the Facade/Manager for consistency, or use Config if preferred
        $availableDigestIntervals = NotificationSettingsFacade::getDigestIntervals();

        $notificationSettings = collect($definedNotificationTypes)->mapWithKeys(function ($typeDetails, $typeKey) use ($user) {
            $channels = collect($typeDetails['channels'] ?? [])->map(function ($channelConfig) use ($user, $typeKey) {
                $subscription = $user ? $user->getSubscriptionDetails($typeKey, $channelConfig['name']) : null;
                $digestAtTime = $subscription->digest_at_time ?? '09:00:00'; // Default with seconds
                return [
                    'name' => $channelConfig['name'],
                    'label' => $channelConfig['label'],
                    'subscribed' => (bool) $subscription,
                    'digest_interval' => $subscription->digest_interval ?? 'immediate',
                    'digest_at_time' => $digestAtTime ? substr($digestAtTime, 0, 5) : '09:00', // HH:MM
                    'digest_at_day' => $subscription->digest_at_day ?? 'monday',
                ];
            })->all();
            return [$typeKey => [
                'label' => $typeDetails['label'],
                'description' => $typeDetails['description'],
                'channels' => $channels,
            ]];
        })->all();

        return Inertia::render('Profile/NotificationSettings', [
            'notificationSettings' => $notificationSettings,
            'availableDigestIntervals' => $availableDigestIntervals,
            'availableDaysOfWeek' => [
                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $definedNotificationTypes = NotificationSettingsFacade::getSubscribableNotificationTypes();
        $availableDigestIntervals = NotificationSettingsFacade::getDigestIntervals();

        $validatedData = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($definedNotificationTypes))],
            'channel' => ['required', 'string'],
            'subscribed' => ['required', 'boolean'],
            'digest_interval' => ['required', 'string', Rule::in(array_keys($availableDigestIntervals))],
            'digest_at_time' => ['nullable', 'required_if:digest_interval,daily', 'required_if:digest_interval,weekly', 'date_format:H:i'],
            'digest_at_day' => ['nullable', 'required_if:digest_interval,weekly', 'string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
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
            $user->subscribe(
                $type,
                $channelName,
                $validatedData['digest_interval'],
                $validatedData['digest_at_time'] ? $validatedData['digest_at_time'] . ':00' : null, // Append seconds
                $validatedData['digest_at_day']
            );
        } else {
            $user->unsubscribe($type, $channelName);
        }

        return back()->with('success', 'Notification settings updated.');
    }
}
```

**Vue Component (`resources/js/Pages/Profile/NotificationSettings.vue` - example):**

```html
<template>
    <head title="Notification Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
            >
                Notification Settings
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div
                    class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg"
                >
                    <section>
                        <header>
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                Manage Your Notification Preferences
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                            >
                                Choose which communications and channels you'd
                                like to receive, and how often.
                            </p>
                        </header>

                        <div
                            v-if="$page.props.flash.success"
                            class="mt-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 rounded"
                        >
                            {{ $page.props.flash.success }}
                        </div>

                        <div
                            v-if="hasErrors"
                            class="mt-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 rounded"
                        >
                            <p
                                v-for="(error, key) in $page.props.errors"
                                :key="key"
                            >
                                {{ error }}
                            </p>
                        </div>

                        <div class="mt-6 space-y-8">
                            <div
                                v-for="(typeDetails, typeKey) in reactiveNotificationSettings"
                                :key="typeKey"
                                class="border-t border-gray-200 dark:border-gray-700 pt-6 first:border-t-0 first:pt-0"
                            >
                                <h2
                                    class="text-xl font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ typeDetails.label }}
                                </h2>
                                <p
                                    class="text-sm text-gray-600 dark:text-gray-400 mb-3"
                                >
                                    {{ typeDetails.description }}
                                </p>

                                <div
                                    v-for="(channel, channelIndex) in typeDetails.channels"
                                    :key="channel.name"
                                    class="mb-6 p-3 border-l-4 border-gray-200 dark:border-gray-700 rounded bg-gray-50 dark:bg-gray-900/50"
                                >
                                    <h3
                                        class="text-lg font-medium text-gray-800 dark:text-gray-200"
                                    >
                                        {{ channel.label }} ({{ channel.name }})
                                    </h3>

                                    <div class="mt-2">
                                        <label
                                            class="flex items-center cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="channel.subscribed"
                                                @change="toggleSubscription(typeKey, channel, channelIndex)"
                                                class="form-checkbox h-5 w-5 text-indigo-600 dark:text-indigo-400 border-gray-300 dark:border-gray-600 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 rounded"
                                            />
                                            <span
                                                class="ml-2 text-gray-700 dark:text-gray-300"
                                                >Subscribed</span
                                            >
                                        </label>
                                    </div>

                                    <div
                                        v-if="channel.subscribed"
                                        class="mt-4 space-y-3 pl-1"
                                    >
                                        <div>
                                            <label
                                                :for="`interval-${typeKey}-${channel.name}`"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Delivery Preference:</label
                                            >
                                            <select
                                                :id="`interval-${typeKey}-${channel.name}`"
                                                v-model="channel.digest_interval"
                                                @change="updateSubscription(typeKey, channel)"
                                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                            >
                                                <option
                                                    v-for="(label, key) in availableDigestIntervals"
                                                    :key="key"
                                                    :value="key"
                                                >
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div
                                            v-if="channel.digest_interval === 'daily' || channel.digest_interval === 'weekly'"
                                        >
                                            <label
                                                :for="`time-${typeKey}-${channel.name}`"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Time (HH:MM):</label
                                            >
                                            <input
                                                type="time"
                                                :id="`time-${typeKey}-${channel.name}`"
                                                v-model="channel.digest_at_time"
                                                @change="updateSubscription(typeKey, channel)"
                                                class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md"
                                            />
                                        </div>

                                        <div
                                            v-if="channel.digest_interval === 'weekly'"
                                        >
                                            <label
                                                :for="`day-${typeKey}-${channel.name}`"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >Day of the Week:</label
                                            >
                                            <select
                                                :id="`day-${typeKey}-${channel.name}`"
                                                v-model="channel.digest_at_day"
                                                @change="updateSubscription(typeKey, channel)"
                                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                            >
                                                <option
                                                    v-for="(label, key) in availableDaysOfWeek"
                                                    :key="key"
                                                    :value="key"
                                                >
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="!typeDetails.channels || typeDetails.channels.length === 0"
                                    class="pl-4 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    No specific channels configured for this
                                    notification type.
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
    import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
    import { Head, useForm, usePage } from "@inertiajs/vue3";
    import { ref, computed, watch } from "vue";

    const props = defineProps({
        notificationSettings: Object,
        availableDigestIntervals: Object,
        availableDaysOfWeek: Object,
    });

    const reactiveNotificationSettings = ref(
        JSON.parse(JSON.stringify(props.notificationSettings))
    );

    const page = usePage();

    const hasErrors = computed(() => {
        return Object.keys(page.props.errors).length > 0;
    });

    const form = useForm({
        type: "",
        channel: "",
        subscribed: false,
        digest_interval: "immediate",
        digest_at_time: "09:00",
        digest_at_day: "monday",
    });

    function updateSubscription(typeKey, channel) {
        form.type = typeKey;
        form.channel = channel.name;
        form.subscribed = channel.subscribed;
        form.digest_interval = channel.digest_interval;
        form.digest_at_time = channel.digest_at_time;
        form.digest_at_day = channel.digest_at_day;

        form.post(route("profile.notification-settings.store"), {
            // Make sure this route name is correct for your app
            preserveScroll: true,
            onSuccess: () => {
                // Optional: Show a toast message
            },
            onError: (errors) => {
                console.error("Error updating subscription:", errors);
                // Handle errors, perhaps revert optimistic updates if needed
            },
        });
    }

    function toggleSubscription(typeKey, channel, channelIndex) {
        const currentChannelState =
            reactiveNotificationSettings.value[typeKey].channels[channelIndex];
        currentChannelState.subscribed = !currentChannelState.subscribed;

        if (!currentChannelState.subscribed) {
            currentChannelState.digest_interval = "immediate"; // Reset digest options if unsubscribing
        }

        updateSubscription(typeKey, currentChannelState);
    }

    watch(
        () => props.notificationSettings,
        (newSettings) => {
            reactiveNotificationSettings.value = JSON.parse(
                JSON.stringify(newSettings)
            );
        },
        { deep: true }
    );
</script>
```

Make sure to define the route (e.g., `profile.notification-settings.store`) in your `routes/web.php` (or `api.php`) pointing to your controller's `store` method.
