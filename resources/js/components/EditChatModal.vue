<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';

interface Props {
    isOpen: boolean;
    initialTitle: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'save', title: string): void }>();

const title = ref('');
const error = ref('');

watch(() => props.isOpen, (open) => {
    if (open) {
        title.value = props.initialTitle || '';
        error.value = '';
        setTimeout(() => {
            const input = document.getElementById('edit-chat-title');
            if (input) input.focus();
        }, 0);
    }
});

const handleSave = () => {
    const trimmed = title.value.trim();
    if (!trimmed) {
        error.value = 'Title is required';
        return;
    }
    if (trimmed.length > 255) {
        error.value = 'Title must be at most 255 characters';
        return;
    }
    emit('save', trimmed);
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="emit('close')"></div>

        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Rename chat</h2>
            </div>

            <div class="p-6 space-y-4">
                <label for="edit-chat-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                <input
                    id="edit-chat-title"
                    v-model="title"
                    type="text"
                    class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    placeholder="Enter chat title"
                    maxlength="255"
                />
                <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
            </div>

            <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 dark:border-gray-700">
                <Button @click="emit('close')" variant="outline" class="text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700">Cancel</Button>
                <Button @click="handleSave" class="bg-orange-500 hover:bg-orange-600 text-white">Save</Button>
            </div>
        </div>
    </div>
    
    <!-- Accessibility: ESC to close -->
    <input type="text" class="hidden" @keydown.esc.prevent="emit('close')" />
</template>


