<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
    showPlan?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showPlan: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(() => props.user.avatar && props.user.avatar !== '');

const subscriptionType = computed(() => props.user.subscription_type || 'free');

const displayPlanName = computed(() => {
    const key = subscriptionType.value || 'free';
    return key.charAt(0).toUpperCase() + key.slice(1);
});
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
        <span
            v-if="showPlan"
            class="truncate text-xs"
            :class="subscriptionType !== 'free' ? 'text-yellow-600 font-medium' : 'text-muted-foreground'"
        >
            Plan: {{ displayPlanName }}
        </span>
    </div>
</template>
