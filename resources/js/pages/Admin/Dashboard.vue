<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Users, MessageSquare, FileText, CreditCard, TrendingUp, Calendar, BookOpen } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Stats {
    total_users: number;
    total_chats: number;
    total_messages: number;
    active_subscriptions: number;
    new_users_this_month: number;
    new_chats_this_month: number;
    total_courses: number;
    active_courses: number;
}

interface RecentUser {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface ExamStat {
    exam_type: string;
    count: number;
}

defineProps<{
    stats: Stats;
    recent_users: RecentUser[];
    exam_stats: ExamStat[];
}>();
</script>

<template>
    <AdminLayout title="Dashboard">
        <template #header>
            Dashboard
        </template>
        
        <template #subtitle>
            Site statistics and management
        </template>
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl shadow-lg border border-blue-200/50 dark:border-blue-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-blue-600 dark:text-blue-400 mb-1">
                                Total Users
                            </p>
                            <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">
                                {{ stats.total_users.toLocaleString() }}
                            </p>
                            <p class="text-xs text-blue-500 dark:text-blue-300 mt-1">
                                +{{ stats.new_users_this_month }} this month
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                <Users class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Chats -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl shadow-lg border border-green-200/50 dark:border-green-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-green-600 dark:text-green-400 mb-1">
                                Total Chats
                            </p>
                            <p class="text-3xl font-bold text-green-900 dark:text-green-100">
                                {{ stats.total_chats.toLocaleString() }}
                            </p>
                            <p class="text-xs text-green-500 dark:text-green-300 mt-1">
                                +{{ stats.new_chats_this_month }} this month
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center shadow-lg">
                                <MessageSquare class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Messages -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl shadow-lg border border-purple-200/50 dark:border-purple-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-purple-600 dark:text-purple-400 mb-1">
                                Total Messages
                            </p>
                            <p class="text-3xl font-bold text-purple-900 dark:text-purple-100">
                                {{ stats.total_messages.toLocaleString() }}
                            </p>
                            <p class="text-xs text-purple-500 dark:text-purple-300 mt-1">
                                AI conversations
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                                <FileText class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Subscriptions -->
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-xl shadow-lg border border-yellow-200/50 dark:border-yellow-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400 mb-1">
                                Active Subscriptions
                            </p>
                            <p class="text-3xl font-bold text-yellow-900 dark:text-yellow-100">
                                {{ stats.active_subscriptions.toLocaleString() }}
                            </p>
                            <p class="text-xs text-yellow-500 dark:text-yellow-300 mt-1">
                                Premium users
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center shadow-lg">
                                <CreditCard class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Users This Month -->
                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900/20 dark:to-indigo-800/20 rounded-xl shadow-lg border border-indigo-200/50 dark:border-indigo-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400 mb-1">
                                New Users This Month
                            </p>
                            <p class="text-3xl font-bold text-indigo-900 dark:text-indigo-100">
                                {{ stats.new_users_this_month.toLocaleString() }}
                            </p>
                            <p class="text-xs text-indigo-500 dark:text-indigo-300 mt-1">
                                Growth rate
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center shadow-lg">
                                <TrendingUp class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Chats This Month -->
                <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl shadow-lg border border-red-200/50 dark:border-red-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-red-600 dark:text-red-400 mb-1">
                                New Chats This Month
                            </p>
                            <p class="text-3xl font-bold text-red-900 dark:text-red-100">
                                {{ stats.new_chats_this_month.toLocaleString() }}
                            </p>
                            <p class="text-xs text-red-500 dark:text-red-300 mt-1">
                                Activity level
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center shadow-lg">
                                <Calendar class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Courses -->
                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-xl shadow-lg border border-emerald-200/50 dark:border-emerald-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400 mb-1">
                                Total Courses
                            </p>
                            <p class="text-3xl font-bold text-emerald-900 dark:text-emerald-100">
                                {{ stats.total_courses.toLocaleString() }}
                            </p>
                            <p class="text-xs text-emerald-500 dark:text-emerald-300 mt-1">
                                Available exams
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                                <BookOpen class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Courses -->
                <div class="bg-gradient-to-br from-teal-50 to-teal-100 dark:from-teal-900/20 dark:to-teal-800/20 rounded-xl shadow-lg border border-teal-200/50 dark:border-teal-700/50 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-teal-600 dark:text-teal-400 mb-1">
                                Active Courses
                            </p>
                            <p class="text-3xl font-bold text-teal-900 dark:text-teal-100">
                                {{ stats.active_courses.toLocaleString() }}
                            </p>
                            <p class="text-xs text-teal-500 dark:text-teal-300 mt-1">
                                Live courses
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-teal-500 rounded-xl flex items-center justify-center shadow-lg">
                                <BookOpen class="h-6 w-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Users -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                                <Users class="h-4 w-4 text-white" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Users</h3>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="user in recent_users" :key="user.id" class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-medium">{{ user.name.charAt(0) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Statistics -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center mr-3">
                                <BookOpen class="h-4 w-4 text-white" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Exam Types</h3>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="exam in exam_stats" :key="exam.exam_type" class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-pink-500 rounded-lg flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">{{ exam.exam_type.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                                            {{ exam.exam_type.replace('_', ' ') }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Exam preparation</p>
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/30 px-3 py-1 rounded-full">
                                    {{ exam.count }} chats
                                </div>
                            </div>
                        </div>
                        <div v-if="exam_stats.length === 0" class="px-6 py-8 text-center">
                            <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <BookOpen class="h-6 w-6 text-gray-400" />
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No exam data available</p>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
</template>
