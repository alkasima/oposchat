<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { BarChart3 } from 'lucide-vue-next';

const props = defineProps<{ 
    kpis: Record<string, number>;
    message_series: { day: string; count: number }[];
}>();
</script>

<template>
    <AdminLayout title="Reports">
        <template #title-icon>
            <BarChart3 class="w-5 h-5" />
        </template>
        <template #subtitle>
            Key metrics and recent activity
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <p class="text-xs text-gray-500">Total Users</p>
                <p class="text-2xl font-semibold">{{ kpis.total_users }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <p class="text-xs text-gray-500">Active Subscriptions</p>
                <p class="text-2xl font-semibold">{{ kpis.active_subscriptions }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <p class="text-xs text-gray-500">Messages (30d)</p>
                <p class="text-2xl font-semibold">{{ kpis.messages_last_30d }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <p class="text-xs text-gray-500">Chats (30d)</p>
                <p class="text-2xl font-semibold">{{ kpis.chats_last_30d }}</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-3">Messages per day (last 14 days)</h3>
            <div class="space-y-2">
                <div v-for="p in message_series" :key="p.day" class="flex items-center gap-3">
                    <div class="w-24 text-xs text-gray-500">{{ p.day }}</div>
                    <div class="flex-1 h-3 bg-gray-100 dark:bg-gray-700 rounded">
                        <div class="h-3 bg-blue-500 rounded" :style="{ width: Math.min(100, p.count) + '%' }"></div>
                    </div>
                    <div class="w-12 text-right text-xs">{{ p.count }}</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

