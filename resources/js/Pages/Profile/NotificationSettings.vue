<template>

    <Head title="Notification Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Notification Settings</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <section>
                        <header>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Manage Your Notification Preferences
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Choose which communications and channels you'd like to receive, and how often.
                            </p>
                        </header>

                        <div v-if="$page.props.flash.success"
                            class="mt-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 rounded">
                            {{ $page.props.flash.success }}
                        </div>

                        <div v-if="hasErrors"
                            class="mt-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 rounded">
                            <p v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</p>
                        </div>

                        <div class="mt-6 space-y-8">
                            <div v-for="(typeDetails, typeKey) in reactiveNotificationSettings" :key="typeKey"
                                class="border-t border-gray-200 dark:border-gray-700 pt-6 first:border-t-0 first:pt-0">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ typeDetails.label }}
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ typeDetails.description }}
                                </p>

                                <div v-for="(channel, channelIndex) in typeDetails.channels" :key="channel.name"
                                    class="mb-6 p-3 border-l-4 border-gray-200 dark:border-gray-700 rounded bg-gray-50 dark:bg-gray-900/50">
                                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">
                                        {{ channel.label }} ({{ channel.name }})
                                    </h3>

                                    <div class="mt-2">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" :checked="channel.subscribed"
                                                @change="toggleSubscription(typeKey, channel, channelIndex)"
                                                class="form-checkbox h-5 w-5 text-indigo-600 dark:text-indigo-400 border-gray-300 dark:border-gray-600 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 rounded" />
                                            <span class="ml-2 text-gray-700 dark:text-gray-300">Subscribed</span>
                                        </label>
                                    </div>

                                    <div v-if="channel.subscribed" class="mt-4 space-y-3 pl-1">
                                        <div>
                                            <label :for="`interval-${typeKey}-${channel.name}`"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Delivery
                                                Preference:</label>
                                            <select :id="`interval-${typeKey}-${channel.name}`"
                                                v-model="channel.digest_interval"
                                                @change="updateSubscription(typeKey, channel)"
                                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                                <option v-for="(label, key) in availableDigestIntervals" :key="key"
                                                    :value="key">
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div
                                            v-if="channel.digest_interval === 'daily' || channel.digest_interval === 'weekly'">
                                            <label :for="`time-${typeKey}-${channel.name}`"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Time
                                                (HH:MM):</label>
                                            <input type="time" :id="`time-${typeKey}-${channel.name}`"
                                                v-model="channel.digest_at_time"
                                                @change="updateSubscription(typeKey, channel)"
                                                class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md" />
                                        </div>

                                        <div v-if="channel.digest_interval === 'weekly'">
                                            <label :for="`day-${typeKey}-${channel.name}`"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Day
                                                of the
                                                Week:</label>
                                            <select :id="`day-${typeKey}-${channel.name}`"
                                                v-model="channel.digest_at_day"
                                                @change="updateSubscription(typeKey, channel)"
                                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                                <option v-for="(label, key) in availableDaysOfWeek" :key="key"
                                                    :value="key">
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!typeDetails.channels || typeDetails.channels.length === 0"
                                    class="pl-4 text-sm text-gray-500 dark:text-gray-400">
                                    No specific channels configured for this notification type.
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue"; // Added watch

const props = defineProps({
    notificationSettings: Object,
    availableDigestIntervals: Object,
    availableDaysOfWeek: Object,
});

// Use ref for local reactive state of notificationSettings to allow immediate UI updates and v-model binding
const reactiveNotificationSettings = ref(JSON.parse(JSON.stringify(props.notificationSettings)));

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
    form.subscribed = channel.subscribed; // This will be true if we're updating digest prefs
    form.digest_interval = channel.digest_interval;
    form.digest_at_time = channel.digest_at_time;
    form.digest_at_day = channel.digest_at_day;

    form.post(route("profile.notification-settings.store"), {
        preserveScroll: true,
        onSuccess: () => {
            // Update the main prop to sync with parent if necessary, though local changes are already reflected.
            // For this setup, direct modification of reactiveNotificationSettings is enough.
            // Inertia's success handling might reload props, ensure this doesn't cause conflicts.
        },
        onError: (errors) => {
            console.error("Error updating subscription:", errors);
            // Potentially revert optimistic updates if the server rejects the change
            // For now, we rely on Inertia to show validation errors and the user to correct them.
            // A full revert would involve finding the original state of this specific channel and resetting it.
        },
    });
}

function toggleSubscription(typeKey, channel, channelIndex) {
    // Optimistically update the UI
    const newSubscribedState = !reactiveNotificationSettings.value[typeKey].channels[channelIndex].subscribed;
    reactiveNotificationSettings.value[typeKey].channels[channelIndex].subscribed = newSubscribedState;

    // If unsubscribing, set digest_interval to 'immediate' as a sensible default for the form submission
    // The UI for digest options will hide automatically due to v-if="channel.subscribed"
    if (!newSubscribedState) {
        reactiveNotificationSettings.value[typeKey].channels[channelIndex].digest_interval = 'immediate';
    }

    // Now call updateSubscription which will prepare the form with current channel state
    updateSubscription(typeKey, reactiveNotificationSettings.value[typeKey].channels[channelIndex]);
}

// Watch for changes in props.notificationSettings and update local reactive state
// This handles cases where Inertia reloads page props (e.g., after form submission if not handled carefully)
watch(() => props.notificationSettings, (newSettings) => {
    reactiveNotificationSettings.value = JSON.parse(JSON.stringify(newSettings));
}, { deep: true });

</script>
