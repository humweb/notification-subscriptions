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
                                Choose which communications and channels you'd like to receive.
                            </p>
                        </header>

                        <div v-if="$page.props.flash.success" class="mt-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 rounded">
                            {{ $page.props.flash.success }}
                        </div>

                        <div v-if="hasErrors" class="mt-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 rounded">
                            <p v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</p>
                        </div>

                        <div class="mt-6 space-y-8">
                            <div v-for="(notificationType, typeIndex) in reactiveSubscriptionsData" :key="notificationType.type" class="border-t border-gray-200 dark:border-gray-700 pt-6 first:border-t-0 first:pt-0">
                                <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100">{{ notificationType.label }}</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 mb-3">{{ notificationType.description }}</p>
                                
                                <div class="space-y-4">
                                    <div v-for="(channel, channelIndex) in notificationType.channels" :key="channel.name" class="flex items-start pl-4">
                                        <div class="flex items-center h-5">
                                            <input 
                                                :id="`${notificationType.type}-${channel.name}`"
                                                :name="`${notificationType.type}-${channel.name}`"
                                                type="checkbox"
                                                :checked="channel.subscribed"
                                                @change="toggleSubscription(notificationType.type, channel, typeIndex, channelIndex)"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:focus:ring-indigo-600 rounded"
                                            />
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label :for="`${notificationType.type}-${channel.name}`" class="font-medium text-gray-700 dark:text-gray-300">{{ channel.label }}</label>
                                        </div>
                                    </div>
                                    <div v-if="!notificationType.channels || notificationType.channels.length === 0" class="pl-4 text-sm text-gray-500 dark:text-gray-400">
                                        No specific channels configured for this notification type.
                                    </div>
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
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    subscriptionsData: Array,
});

// Use ref for local reactive state to allow immediate UI updates
const reactiveSubscriptionsData = ref(JSON.parse(JSON.stringify(props.subscriptionsData))); // Deep copy to avoid prop mutation issues

const page = usePage();

const hasErrors = computed(() => {
    return Object.keys(page.props.errors).length > 0;
});

const form = useForm({
    type: '',
    channel: '',
    subscribed: false,
});

const toggleSubscription = (notificationTypeString, channelObject, typeIndex, channelIndex) => {
    form.type = notificationTypeString;
    form.channel = channelObject.name;
    form.subscribed = !channelObject.subscribed; // The new desired state

    form.post(route('profile.notification-settings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Update the local reactive state to reflect the change immediately
            if (reactiveSubscriptionsData.value[typeIndex] && reactiveSubscriptionsData.value[typeIndex].channels[channelIndex]) {
                 reactiveSubscriptionsData.value[typeIndex].channels[channelIndex].subscribed = form.subscribed;
            }
        },
        onError: (errors) => {
            // If there was an error from the backend, revert the checkbox state
            // This is a simple revert; a more sophisticated UX might show the error near the checkbox
            if (reactiveSubscriptionsData.value[typeIndex] && reactiveSubscriptionsData.value[typeIndex].channels[channelIndex]) {
                 reactiveSubscriptionsData.value[typeIndex].channels[channelIndex].subscribed = !form.subscribed;
            }
             // Optionally, display errors more specifically or clear general flash errors if handled inline
        }
    });
};

</script> 
