<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';



const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <SettingsLayout title="Password Settings" description="Update your password and security settings">
        <Head title="Password settings" />

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Update Password</h2>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Ensure your account is using a long, random password to stay secure</p>
            </div>

            <form @submit.prevent="updatePassword" class="space-y-6">
                <div class="grid gap-3">
                    <Label for="current_password" class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</Label>
                    <Input
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="h-12 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500"
                        autocomplete="current-password"
                        placeholder="Enter your current password"
                    />
                    <InputError class="text-xs" :message="form.errors.current_password" />
                </div>

                <div class="grid gap-3">
                    <Label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">New Password</Label>
                    <Input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="h-12 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500"
                        autocomplete="new-password"
                        placeholder="Enter your new password"
                    />
                    <InputError class="text-xs" :message="form.errors.password" />
                </div>

                <div class="grid gap-3">
                    <Label for="password_confirmation" class="text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="h-12 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500"
                        autocomplete="new-password"
                        placeholder="Confirm your new password"
                    />
                    <InputError class="text-xs" :message="form.errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4 pt-4">
                    <Button 
                        :disabled="form.processing"
                        class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2"
                    >
                        {{ form.processing ? 'Updating...' : 'Update Password' }}
                    </Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-show="form.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400 font-medium">
                            Password updated successfully!
                        </p>
                    </Transition>
                </div>
            </form>
        </div>
    </SettingsLayout>
</template>
