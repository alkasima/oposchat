<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { 
    MessageSquarePlus, 
    User, 
    Settings, 
    LogOut, 
    MoreHorizontal,
    Edit3,
    Trash2,
    Search,
    Crown
} from 'lucide-vue-next';
import SettingsModal from '@/components/SettingsModal.vue';
import UsageIndicator from '@/components/UsageIndicator.vue';
import SubscriptionPrompt from '@/components/SubscriptionPrompt.vue';
import { useSubscription } from '@/composables/useSubscription.js';
import chatApi from '@/services/chatApi.js';

interface Chat {
    id: string;
    title: string;
    lastMessage?: string;
    timestamp: string;
    isActive?: boolean;
}

const emit = defineEmits<{
    chatSelected: [chatId: string | null];
    newChatCreated: [chat: any];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const showUserMenu = ref(false);
const showSettingsModal = ref(false);
const showSubscriptionPrompt = ref(false);
const subscriptionPromptData = ref({});
const searchQuery = ref('');
const isLoading = ref(false);

// Subscription management
const {
    hasPremium,
    usage,
    fetchSubscriptionStatus,
    hasFeatureAccess,
    getUsagePercentage,
    getRemainingUsage
} = useSubscription();

// Computed properties for usage indicators
const usagePercentage = computed(() => {
    const chatUsage = usage.value.chat_messages;
    return chatUsage ? getUsagePercentage('chat_messages') : 0;
});

const isNearLimit = computed(() => {
    const chatUsage = usage.value.chat_messages;
    return chatUsage ? chatUsage.percentage >= 80 : false;
});

const hasReachedLimit = computed(() => {
    const chatUsage = usage.value.chat_messages;
    return chatUsage ? chatUsage.remaining <= 0 : false;
});

// Helper function to handle subscription errors
const handleSubscriptionError = (error) => {
    if (error.response?.status === 429) {
        return {
            type: 'usage_limit_exceeded',
            message: 'You have reached your daily limit for conversations.',
            resetTime: error.response.data.reset_time
        };
    } else if (error.response?.status === 403) {
        return {
            type: 'subscription_required',
            message: 'This feature requires a premium subscription.'
        };
    }
    return {
        type: 'error',
        message: 'An error occurred. Please try again.'
    };
};

// Real chat data from API
const chats = ref<Chat[]>([]);
const activeChat = ref<string | null>(null);

const filteredChats = computed(() => {
    if (!searchQuery.value) return chats.value;
    return chats.value.filter(chat => 
        chat.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        chat.lastMessage?.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});



const logout = () => {
    router.post(route('logout'), {}, {
        onFinish: () => {
            // Clear any local storage or session data if needed
            localStorage.clear();
            sessionStorage.clear();
        }
    });
};

// Load chats from API
const loadChats = async () => {
    try {
        isLoading.value = true;
        const chatData = await chatApi.getChats();
        chats.value = chatData.map(chat => ({
            ...chat,
            isActive: chat.id === activeChat.value
        }));
    } catch (error) {
        console.error('Failed to load chats:', error);
    } finally {
        isLoading.value = false;
    }
};

const createNewChat = async () => {
    // Check if user can create new conversations
    if (!hasFeatureAccess('chat_messages')) {
        const chatUsage = usage.value.chat_messages;
        subscriptionPromptData.value = {
            type: 'usage_limit_exceeded',
            title: 'Daily Limit Reached',
            message: `You've reached your daily limit of ${chatUsage?.limit || 0} conversations. Upgrade to premium for unlimited access.`,
            resetTime: chatUsage?.reset_time
        };
        showSubscriptionPrompt.value = true;
        return;
    }

    try {
        const newChat = await chatApi.createChat();
        
        // Add to chat list
        chats.value.unshift({
            ...newChat,
            isActive: true
        });
        
        // Clear previous active states
        chats.value.forEach((chat, index) => {
            if (index > 0) chat.isActive = false;
        });
        
        // Update active chat
        activeChat.value = newChat.id.toString();
        
        // Emit events to parent
        emit('newChatCreated', newChat);
        emit('chatSelected', newChat.id.toString());
        
        // Refresh subscription status to update usage
        await fetchSubscriptionStatus();
    } catch (error) {
        const errorInfo = handleSubscriptionError(error);
        if (errorInfo.type === 'usage_limit_exceeded' || errorInfo.type === 'subscription_required') {
            subscriptionPromptData.value = {
                type: errorInfo.type,
                title: errorInfo.type === 'usage_limit_exceeded' ? 'Daily Limit Reached' : 'Premium Feature',
                message: errorInfo.message,
                resetTime: errorInfo.resetTime
            };
            showSubscriptionPrompt.value = true;
        } else {
            console.error('Failed to create chat:', error);
        }
    }
};

const selectChat = (chatId: string) => {
    // Update active chat
    activeChat.value = chatId;
    chats.value.forEach(chat => {
        chat.isActive = chat.id.toString() === chatId;
    });
    
    // Emit event to parent component
    emit('chatSelected', chatId);
};

const deleteChat = async (chatId: string, event: Event) => {
    event.stopPropagation();
    
    if (!confirm('Are you sure you want to delete this chat?')) {
        return;
    }
    
    try {
        await chatApi.deleteChat(chatId);
        chats.value = chats.value.filter(chat => chat.id.toString() !== chatId);
        
        // If deleted chat was active, clear selection
        if (activeChat.value === chatId) {
            activeChat.value = null;
            emit('chatSelected', null);
        }
    } catch (error) {
        console.error('Failed to delete chat:', error);
    }
};

const openSettings = () => {
    showUserMenu.value = false;
    showSettingsModal.value = true;
};

const handleUpgradeClick = () => {
    showSubscriptionPrompt.value = false;
    showSettingsModal.value = true;
};

// Load chats when component mounts
onMounted(async () => {
    await loadChats();
    await fetchSubscriptionStatus();
});
</script>

<template>
    <div class="w-80 bg-gray-900 text-white flex flex-col h-full">
        <!-- Header -->
        <div class="p-4 border-b border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <Link :href="route('dashboard')" class="flex items-center space-x-2">
                    <div class="w-14 h-14 bg-gradient-to-br from-white to-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-2">
    <img src="/images/logo.png" alt="OposChat" class="w-full h-full rounded-full" />
</div>
                    <span class="font-semibold text-lg">OPOSCHAT</span>
                </Link>
            </div>
            
            <!-- New Chat Button -->
            <Button 
                @click="createNewChat"
                class="w-full bg-gray-800 hover:bg-gray-700 text-white border-gray-600 justify-start mb-3"
                variant="outline"
            >
                <MessageSquarePlus class="w-4 h-4 mr-2" />
                New chat
            </Button>

            <!-- Search -->
            <div class="relative">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search chats..."
                    class="w-full bg-gray-800 border border-gray-600 rounded-lg pl-10 pr-4 py-2 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                />
            </div>
        </div>

        <!-- Usage Indicator -->
        <div class="px-4 pb-2">
            <UsageIndicator
                :has-premium="hasPremium"
                :usage="usage"
                :usage-percentage="usagePercentage"
                :is-near-limit="isNearLimit"
                :has-reached-limit="hasReachedLimit"
                @upgrade="handleUpgradeClick"
            />
        </div>

        <!-- Chat History -->
        <div class="flex-1 overflow-y-auto p-2">
            <!-- Loading State -->
            <div v-if="isLoading" class="text-center py-8">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500 mx-auto"></div>
                <p class="text-gray-400 text-sm mt-2">Loading chats...</p>
            </div>
            
            <!-- Chat List -->
            <div v-else class="space-y-1">
                <div v-for="chat in filteredChats" :key="chat.id" 
                     @click="selectChat(chat.id.toString())"
                     class="group relative p-3 rounded-lg hover:bg-gray-800 cursor-pointer transition-colors"
                     :class="{ 'bg-gray-800': chat.isActive }">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-medium text-white truncate">
                                {{ chat.title || 'New Chat' }}
                            </h3>
                            <p v-if="chat.lastMessage" class="text-xs text-gray-400 truncate mt-1">
                                {{ chat.lastMessage }}
                            </p>
                            <p v-if="chat.timestamp" class="text-xs text-gray-500 mt-1">
                                {{ chat.timestamp }}
                            </p>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity flex space-x-1">
                            <Button 
                                size="sm" 
                                variant="ghost" 
                                class="h-6 w-6 p-0 text-gray-400 hover:text-red-400"
                                @click="deleteChat(chat.id.toString(), $event)"
                                title="Delete chat"
                            >
                                <Trash2 class="w-3 h-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && filteredChats.length === 0" class="text-center py-8">
                <MessageSquarePlus class="w-12 h-12 text-gray-600 mx-auto mb-3" />
                <p class="text-gray-400 text-sm mb-2">No chats yet</p>
                <p class="text-gray-500 text-xs">Start a new conversation to get started</p>
            </div>
        </div>

        <!-- User Profile Section -->
        <div class="p-4 border-t border-gray-700">
            <div class="relative">
                <Button 
                    @click="showUserMenu = !showUserMenu"
                    class="w-full bg-gray-800 hover:bg-gray-700 text-white justify-start p-3"
                    variant="ghost"
                >
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                        <Crown v-if="hasPremium" class="w-4 h-4 text-yellow-400" />
                        <User v-else class="w-4 h-4" />
                    </div>
                    <div class="flex-1 text-left">
                        <div class="text-sm font-medium flex items-center">
                            {{ user?.name || 'User' }}
                            <span v-if="hasPremium" class="ml-2 px-2 py-0.5 text-xs bg-yellow-500 text-black rounded-full font-medium">
                                PRO
                            </span>
                        </div>
                        <div class="text-xs text-gray-400">{{ user?.email }}</div>
                    </div>
                </Button>

                <!-- User Menu Dropdown -->
                <div v-if="showUserMenu" 
                     class="absolute bottom-full left-0 right-0 mb-2 bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-2 z-50">
                    <button @click="openSettings"
                            class="w-full flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                        <Settings class="w-4 h-4 mr-3" />
                        Settings
                    </button>
                    <Separator class="my-1 bg-gray-700" />
                    <button @click="logout" 
                            class="w-full flex items-center px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                        <LogOut class="w-4 h-4 mr-3" />
                        Sign out
                    </button>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <SettingsModal 
            :is-open="showSettingsModal" 
            @close="showSettingsModal = false"
            :must-verify-email="false"
        />

        <!-- Subscription Prompt -->
        <SubscriptionPrompt
            :show="showSubscriptionPrompt"
            :type="subscriptionPromptData.type"
            :title="subscriptionPromptData.title"
            :message="subscriptionPromptData.message"
            :reset-time="subscriptionPromptData.resetTime"
            @close="showSubscriptionPrompt = false"
            @upgrade="handleUpgradeClick"
        />
    </div>
</template>