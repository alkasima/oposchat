<template>
    <AdminLayout title="User Plan Management">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Plan Management</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Manually update user subscription plans without requiring payment
                </p>
            </div>

            <!-- Search Section -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Search User</h2>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <Input
                            v-model="searchQuery"
                            placeholder="Search by email or name..."
                            @input="searchUsers"
                            class="w-full"
                        />
                    </div>
                    <Button @click="searchUsers" :disabled="searchQuery.length < 2">
                        Search
                    </Button>
                </div>

                <!-- Search Results -->
                <div v-if="searchResults.length > 0" class="mt-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Results:</h3>
                    <div class="space-y-2">
                        <div
                            v-for="user in searchResults"
                            :key="user.id"
                            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                            @click="selectUser(user)"
                        >
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</div>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Click to manage plan
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plan Update Modal -->
            <div v-if="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
                    <div class="mt-3">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Update User Plan
                            </h3>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- User Info -->
                        <div v-if="selectedUser" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="grid grid-cols-1 gap-2 text-sm">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                                    <span class="text-gray-900 dark:text-white ml-2">{{ selectedUser.name }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Email:</span>
                                    <span class="text-gray-900 dark:text-white ml-2">{{ selectedUser.email }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Current Plan:</span>
                                    <span class="text-gray-900 dark:text-white ml-2">{{ selectedUser.current_plan }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                                    <span class="text-gray-900 dark:text-white ml-2">{{ selectedUser.subscription_status }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Plan Update Form -->
                        <form @submit.prevent="updateUserPlan" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    New Plan
                                </label>
                                <select
                                    v-model="updateForm.plan_key"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    required
                                >
                                    <option value="">Select a plan</option>
                                    <option value="free">Free</option>
                                    <option value="premium">Premium</option>
                                    <option value="plus">Plus</option>
                                    <option value="academy">Academy</option>
                                </select>
                            </div>

                            <div v-if="updateForm.plan_key && updateForm.plan_key !== 'free'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Duration (Months)
                                </label>
                                <Input
                                    v-model.number="updateForm.duration_months"
                                    type="number"
                                    min="1"
                                    max="12"
                                    placeholder="1"
                                    class="w-full"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Reason for Change
                                </label>
                                <Textarea
                                    v-model="updateForm.reason"
                                    placeholder="Enter the reason for this plan change..."
                                    rows="3"
                                    class="w-full"
                                    required
                                />
                            </div>

                            <div class="flex gap-3 pt-4">
                                <Button
                                    type="submit"
                                    :disabled="isUpdating"
                                    class="flex-1 bg-blue-600 hover:bg-blue-700"
                                >
                                    {{ isUpdating ? 'Updating...' : 'Update Plan' }}
                                </Button>
                                <Button
                                    type="button"
                                    @click="closeModal"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Users List -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Users</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    User
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Current Plan
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ user.name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ user.email }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="getPlanBadgeClass(user.current_plan || 'Free')">
                                        {{ user.current_plan || 'Free' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ user.subscription_status || 'none' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <Button @click="selectUser(user)" size="sm" variant="outline">
                                        Manage Plan
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps({
    users: Object
});

// Debug: Log the users data to console
console.log('Users data received:', props.users);
if (props.users && props.users.data) {
    console.log('First user data:', props.users.data[0]);
}

const searchQuery = ref('');
const searchResults = ref([]);
const selectedUser = ref(null);
const isUpdating = ref(false);
const showModal = ref(false);

const updateForm = reactive({
    plan_key: '',
    duration_months: 1,
    reason: ''
});

const searchUsers = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }

    try {
        const response = await fetch(`/admin/user-plans/search?q=${encodeURIComponent(searchQuery.value)}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json();
        searchResults.value = data.users || [];
    } catch (error) {
        console.error('Search failed:', error);
        searchResults.value = [];
    }
};

const selectUser = async (user) => {
    try {
        const response = await fetch(`/admin/user-plans/${user.id}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const data = await response.json();
        
        if (data.success) {
            selectedUser.value = data.user;
            updateForm.plan_key = data.user.plan_key;
            updateForm.duration_months = 1;
            updateForm.reason = '';
            showModal.value = true;
        } else {
            alert('Failed to load user details');
        }
    } catch (error) {
        console.error('Failed to load user details:', error);
        alert('Failed to load user details');
    }
};

const updateUserPlan = async () => {
    if (!selectedUser.value) return;

    isUpdating.value = true;
    
    try {
        const response = await fetch(`/admin/user-plans/${selectedUser.value.id}/update-plan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(updateForm)
        });

        const data = await response.json();

        if (data.success) {
            alert(`Plan updated successfully to ${data.user.current_plan}`);
            closeModal();
            // Refresh the page to show updated data
            window.location.reload();
        } else {
            alert('Failed to update plan: ' + data.message);
        }
    } catch (error) {
        console.error('Update failed:', error);
        alert('Failed to update plan');
    } finally {
        isUpdating.value = false;
    }
};

const closeModal = () => {
    showModal.value = false;
    selectedUser.value = null;
    updateForm.plan_key = '';
    updateForm.duration_months = 1;
    updateForm.reason = '';
    searchQuery.value = '';
    searchResults.value = [];
};

const clearSelection = () => {
    closeModal();
};

const getPlanBadgeClass = (plan) => {
    const classes = {
        'Free': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'Premium': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'Plus': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'Academy': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'
    };
    return classes[plan] || classes['Free'];
};
</script>
