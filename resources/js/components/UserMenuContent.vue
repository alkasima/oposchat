<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import UsageIndicator from '@/components/UsageIndicator.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { refreshCSRFToken, getCSRFToken } from '@/utils/csrf.js';
import { useSubscription } from '@/composables/useSubscription.js';

interface Props {
    user: User;
}

interface Emits {
    (e: 'upgrade'): void;
}

const emit = defineEmits<Emits>();

// Subscription management
const {
    hasPremium,
    usage,
    hasFeatureAccess,
    getRemainingUsage,
    getUsagePercentage,
    fetchSubscriptionStatus
} = useSubscription();

const handleLogout = async () => {
    try {
        // First attempt with current token
        router.post(route('logout'), {}, {
            onError: async (errors) => {
                // Check if it's a CSRF error
                const isCSRFError = errors && (
                    errors.status === 419 ||
                    Object.values(errors).some(error => 
                        typeof error === 'string' && (
                            error.includes('CSRF') || 
                            error.includes('419') || 
                            error.includes('expired') ||
                            error.includes('token')
                        )
                    )
                );
                
                if (isCSRFError) {
                    try {
                        // Refresh CSRF token and try again
                        await refreshCSRFToken();
                        
                        // Retry logout with fresh token
                        router.post(route('logout'), {}, {
                            onFinish: () => router.flushAll()
                        });
                    } catch (refreshError) {
                        console.error('Failed to refresh CSRF token:', refreshError);
                        // Fallback: use form submission
                        submitLogoutForm();
                    }
                } else {
                    console.error('Logout error:', errors);
                    // Fallback: use form submission
                    submitLogoutForm();
                }
            },
            onFinish: () => router.flushAll()
        });
    } catch (error) {
        console.error('Logout failed:', error);
        // Fallback: use form submission
        submitLogoutForm();
    }
};

const submitLogoutForm = () => {
    // Create a form and submit it directly as fallback
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('logout');
    
    // Add CSRF token
    const csrfToken = getCSRFToken();
    if (csrfToken) {
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = csrfToken;
        form.appendChild(tokenInput);
    }
    
    // Add to body and submit
    document.body.appendChild(form);
    form.submit();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    
    <!-- Usage Indicator -->
    <div v-if="!hasPremium && usage.chat_messages" class="px-2 py-2">
        <UsageIndicator
            feature="chat_messages"
            feature-name="Chat Messages"
            :usage="usage.chat_messages"
            :has-premium="hasPremium"
            @upgrade="emit('upgrade')"
        />
    </div>
    
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem>
        <button class="flex w-full items-center" @click="handleLogout">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </button>
    </DropdownMenuItem>
</template>
