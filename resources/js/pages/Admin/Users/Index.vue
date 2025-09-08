<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Users } from 'lucide-vue-next';

interface UserItem {
    id: number;
    name: string;
    email: string;
    created_at: string;
    subscription_status: string;
}

const props = defineProps<{ 
    filters: { search?: string; sort?: string; direction?: string };
    users: { data: UserItem[]; links: any[]; meta?: any };
}>();

const search = ref(props.filters.search || '');
const sort = ref(props.filters.sort || 'created_at');
const direction = ref(props.filters.direction || 'desc');

watch([search, sort, direction], () => {
    router.get(route('admin.users.index'), {
        search: search.value,
        sort: sort.value,
        direction: direction.value,
    }, {
        preserveState: true,
        replace: true,
    });
});
</script>

<template>
    <AdminLayout title="All Users">
        <template #title-icon>
            <Users class="w-5 h-5" />
        </template>
        <template #subtitle>
            Manage all registered users
        </template>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 mb-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="w-full sm:max-w-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                />
                <div class="flex items-center gap-2 ml-auto">
                    <select v-model="sort" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        <option value="created_at">Created</option>
                        <option value="name">Name</option>
                        <option value="email">Email</option>
                    </select>
                    <select v-model="direction" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscription</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="u in users.data" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-4 py-3 text-sm">{{ u.name }}</td>
                            <td class="px-4 py-3 text-sm">{{ u.email }}</td>
                            <td class="px-4 py-3 text-sm capitalize">{{ u.subscription_status }}</td>
                            <td class="px-4 py-3 text-sm">{{ u.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

