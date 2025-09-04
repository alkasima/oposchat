<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminSidebar from '@/components/Admin/AdminSidebar.vue';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <Head :title="title ? `${title} - Admin Panel` : 'Admin Panel'" />
    
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <AdminSidebar />
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            <slot name="header" />
                        </h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <slot name="subtitle" />
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Quick Actions -->
                        <slot name="actions" />
                        
                        <!-- User Menu -->
                        <div class="flex items-center space-x-3">
                            <a 
                                :href="route('home')" 
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                            >
                                View Site
                            </a>
                            <a 
                                :href="route('dashboard')" 
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                            >
                                User Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
