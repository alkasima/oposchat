<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { type BreadcrumbItem, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();



const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <SettingsLayout title="Profile Settings" description="Update your personal information and account details">
        <Head title="Profile settings" />

        <div class="space-y-8">
            <!-- Profile Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Información del perfil</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Actualiza tu nombre y correo electrónico</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-3">
                        <Label for="name" class="text-sm font-medium text-gray-700 dark:text-gray-300">Full name</Label>
                        <Input 
                            id="name" 
                            class="h-12 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500" 
                            v-model="form.name" 
                            required 
                            autocomplete="name" 
                            placeholder="Enter your full name" 
                        />
                        <InputError class="text-xs" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-3">
                        <Label for="email" class="text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="h-12 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Enter your email address"
                        />
                        <InputError class="text-xs" :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            Tu correo electrónico se encuentra sin verificar.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="font-medium underline hover:no-underline"
                            >
                                Clica aquí para reenviar el correo de verificación.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                            Un nuevo link de verificación se ha enviado a tu correo electrónico.
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-4">
                        <Button 
                            :disabled="form.processing"
                            class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400 font-medium">
                                ¡Cambios guardados con éxito!
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>

            <!-- Account Actions -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <DeleteUser />
            </div>
        </div>
    </SettingsLayout>
</template>
