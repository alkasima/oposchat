<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Settings } from 'lucide-vue-next';

const props = defineProps<{ settings: Record<string, any> }>();

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as any)?.success || '');

const keysForm = useForm({
    OPENAI_API_KEY: props.settings.openai_api_key || '',
    OPENAI_MODEL: props.settings.openai_model || '',
    GEMINI_API_KEY: props.settings.gemini_api_key || '',
    GEMINI_MODEL: props.settings.gemini_model || '',
    PINECONE_API_KEY: props.settings.pinecone_api_key || '',
    PINECONE_ENVIRONMENT: props.settings.pinecone_environment || '',
    PINECONE_INDEX_NAME: props.settings.pinecone_index_name || '',
    STRIPE_KEY: props.settings.stripe_key || '',
    STRIPE_SECRET: props.settings.stripe_secret || '',
    STRIPE_WEBHOOK_SECRET: props.settings.stripe_webhook_secret || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitKeys() {
    keysForm.post(route('admin.settings.update-keys'), {
        preserveScroll: true,
    });
}

function submitPassword() {
    passwordForm.post(route('admin.settings.update-password'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset('current_password', 'password', 'password_confirmation');
        }
    });
}
</script>

<template>
    <AdminLayout title="Settings">
        <template #title-icon>
            <Settings class="w-5 h-5" />
        </template>
        <template #subtitle>
            Manage API keys and change password
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold mb-4">API Keys</h3>
                <div v-if="keysForm.recentlySuccessful || flashSuccess" class="mb-4 rounded-md border border-green-200 bg-green-50 text-green-800 px-4 py-2 text-sm dark:border-green-900/50 dark:bg-green-900/30 dark:text-green-200">
                    {{ flashSuccess || 'Settings updated successfully' }}
                </div>
                <form @submit.prevent="submitKeys" class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">OpenAI API Key</label>
                        <input v-model="keysForm.OPENAI_API_KEY" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="sk-..." />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">OpenAI Model</label>
                            <input v-model="keysForm.OPENAI_MODEL" type="text" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="gpt-4o-mini" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Gemini API Key</label>
                            <input v-model="keysForm.GEMINI_API_KEY" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="AI..." />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Gemini Model</label>
                            <input v-model="keysForm.GEMINI_MODEL" type="text" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="gemini-1.5-flash" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Pinecone API Key</label>
                            <input v-model="keysForm.PINECONE_API_KEY" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Pinecone Env</label>
                            <input v-model="keysForm.PINECONE_ENVIRONMENT" type="text" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Pinecone Index</label>
                            <input v-model="keysForm.PINECONE_INDEX_NAME" type="text" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stripe Key</label>
                            <input v-model="keysForm.STRIPE_KEY" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stripe Secret</label>
                            <input v-model="keysForm.STRIPE_SECRET" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stripe Webhook Secret</label>
                            <input v-model="keysForm.STRIPE_WEBHOOK_SECRET" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50" :disabled="keysForm.processing">
                            Save Keys
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold mb-4">Change Password</h3>
                <div v-if="passwordForm.recentlySuccessful || flashSuccess" class="mb-4 rounded-md border border-green-200 bg-green-50 text-green-800 px-4 py-2 text-sm dark:border-green-900/50 dark:bg-green-900/30 dark:text-green-200">
                    {{ flashSuccess || 'Password updated successfully' }}
                </div>
                <form @submit.prevent="submitPassword" class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Current Password</label>
                        <input v-model="passwordForm.current_password" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" required />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">New Password</label>
                            <input v-model="passwordForm.password" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" required />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Confirm Password</label>
                            <input v-model="passwordForm.password_confirmation" type="password" class="w-full rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" required />
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50" :disabled="passwordForm.processing">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

