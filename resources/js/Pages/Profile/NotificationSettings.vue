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
                                Choose which communications you'd like to receive.
                            </p>
                        </header>

                        <div v-if="$page.props.flash.success" class="mt-4 p-4 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 rounded">
                            {{ $page.props.flash.success }}
                        </div>

                         <div v-if="hasErrors" class="mt-4 p-4 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 rounded">
                            <p v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</p>
                        </div>

                        <div class="mt-6 space-y-6">
                            <div v-for="subscription in subscriptions" :key="subscription.type" class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input 
                                        :id="subscription.type"
                                        :name="subscription.type"
                                        type="checkbox"
                                        :checked="subscription.subscribed"
                                        @change="toggleSubscription(subscription)"
                                        class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:focus:ring-indigo-600 rounded"
                                    />
                                </div>
                                <div class="ml-3 text-sm">
                                    <label :for="subscription.type" class="font-medium text-gray-700 dark:text-gray-300">{{ subscription.label }}</label>
                                    <p class="text-gray-500 dark:text-gray-400">{{ subscription.description }}</p>
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'; // Adjust if your layout path is different
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    subscriptionsData: Array,
});

// Make subscriptions reactive so checkboxes update immediately
const subscriptions = ref(props.subscriptionsData);

const page = usePage();

const hasErrors = computed(() => {
    return Object.keys(page.props.errors).length > 0;
});

const form = useForm({
    type: '',
    subscribed: false,
});

const toggleSubscription = (subscription) => {
    form.type = subscription.type;
    form.subscribed = !subscription.subscribed; // The new desired state

    form.post(route('profile.notification-settings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Update the local state to reflect the change immediately
            const index = subscriptions.value.findIndex(s => s.type === subscription.type);
            if (index !== -1) {
                subscriptions.value[index].subscribed = form.subscribed;
            }
        },
        onError: () => {
            // If there was an error, revert the checkbox (though controller should handle this by not changing db)
            // For a better UX, you might want to reload props or handle errors more gracefully
        }
    });
};

</script> 
