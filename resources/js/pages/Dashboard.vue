<script setup lang="ts">
import ChatLayout from '@/layouts/ChatLayout.vue';

interface Props {
    chatId?: string | number | null;
}

const props = defineProps<Props>();
</script>

<template>
    <ChatLayout :initial-chat-id="props.chatId" />
</template>
