<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
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
    Crown,
    X,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight
} from 'lucide-vue-next';
import SettingsModal from '@/components/SettingsModal.vue';
import UsageIndicator from '@/components/UsageIndicator.vue';
import SubscriptionPrompt from '@/components/SubscriptionPrompt.vue';
import { useSubscription } from '@/composables/useSubscription.js';
import chatApi from '@/services/chatApi.js';
import EditChatModal from '@/components/EditChatModal.vue';
import { useAppearance } from '@/composables/useAppearance';

interface Chat {
    id: string;
    title: string;
    lastMessage?: string;
    timestamp: string;
    isActive?: boolean;
}

const props = defineProps<{
    isMobile?: boolean;
    currentChatId?: string | null;
    isCollapsed?: boolean;
}>();

const emit = defineEmits<{
    chatSelected: [chatId: string | null];
    newChatCreated: [chat: any];
    closeMobile?: [];
    toggleCollapse?: [];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const showUserMenu = ref(false);
const showSettingsModal = ref(false);
const showSubscriptionPrompt = ref(false);
const subscriptionPromptData = ref({});
const searchQuery = ref('');
const isLoading = ref(false);
const showEditModal = ref(false);
const editTargetChatId = ref<string | null>(null);
const editTargetTitle = ref('');

// Subscription management
const {
    hasPremium,
    currentPlanName,
    subscription,
    usage,
    fetchSubscriptionStatus,
    fetchUsageData,
    refreshSubscriptionData,
    hasFeatureAccess,
    getUsagePercentage,
    getRemainingUsage
} = useSubscription();

// Use subscription_type stored on the user model as the source of truth for plan
const userSubscriptionType = computed(() => (user.value?.subscription_type as string) || 'free');

// Normalized display name for the current plan (e.g. 'free' -> 'Free')
const displayPlanName = computed(() => {
    const key = userSubscriptionType.value || 'free';
    return key.charAt(0).toUpperCase() + key.slice(1);
});

// Whether user is on a paid plan (any non-free subscription_type)
const hasPaidPlan = computed(() => userSubscriptionType.value !== 'free');

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
    let list = chats.value;
    if (!searchQuery.value) return list;
    return list.filter(chat => 
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
            isActive: chat.id.toString() === props.currentChatId
        }));
        // Update activeChat to match currentChatId
        activeChat.value = props.currentChatId;
    } catch (error) {
        console.error('Failed to load chats:', error);
    } finally {
        isLoading.value = false;
    }
};

const createNewChat = async () => {
    // Instead of creating a chat immediately, just clear the current chat
    // The chat will be created when the user sends their first message
    
    // Clear previous active states
    chats.value.forEach((chat) => {
        chat.isActive = false;
    });
    
    // Clear active chat
    activeChat.value = null;
    
    // Emit events to parent to clear current chat
    emit('chatSelected', null);
    
    // Show a message that they can start typing
    console.log('Ready for new conversation - start typing to create a chat');
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

const openEditChat = (chat: Chat, event: Event) => {
    event.stopPropagation();
    editTargetChatId.value = chat.id.toString();
    editTargetTitle.value = chat.title || '';
    showEditModal.value = true;
};

const handleEditSave = async (newTitle: string) => {
    if (!editTargetChatId.value) return;
    try {
        const updated = await chatApi.updateChat(editTargetChatId.value, { title: newTitle });
        // Update local list
        const idx = chats.value.findIndex(c => c.id.toString() === editTargetChatId.value);
        if (idx !== -1) {
            chats.value[idx].title = updated.title;
        }
        showEditModal.value = false;
        editTargetChatId.value = null;
        editTargetTitle.value = '';
    } catch (error) {
        console.error('Failed to update chat title:', error);
        alert('Could not update chat title. Please try again.');
    }
};

const openSettings = () => {
    showUserMenu.value = false;
    showSettingsModal.value = true;
};

const handleUpgradeClick = () => {
    showSubscriptionPrompt.value = false;
    router.visit(route('pricing'));
};

const handleWaitClick = () => {
    showSubscriptionPrompt.value = false;
    // Show a toast or notification that the limit will reset tomorrow
    // For now, just close the modal
};

// Watch for currentChatId changes to update active state
watch(() => props.currentChatId, (newChatId) => {
    activeChat.value = newChatId;
    // Update all chats' active state
    chats.value.forEach(chat => {
        chat.isActive = chat.id.toString() === newChatId;
    });
}, { immediate: true });

// Theme management
const { appearance } = useAppearance();
const prefersDark = () => typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const isDark = computed(() => appearance.value === 'dark');

// Load chats when component mounts
onMounted(async () => {
    await loadChats();
    await fetchSubscriptionStatus();
});
</script>

<template>
    <div class="flex flex-col h-full transition-all duration-300" 
         :class="{ 
             'w-20': isCollapsed, 
             'w-80': !isCollapsed,
             'bg-gray-900 text-white': isDark,
             'bg-white text-gray-900 border-r border-gray-200': !isDark
         }">
        <!-- Header -->
        <div class="p-4 border-b" :class="isDark ? 'border-gray-700' : 'border-gray-200'">
            <div class="flex items-center justify-between mb-4">
                <Link :href="route('home')" class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-1" 
                         :class="isDark ? 'bg-gradient-to-br from-white to-white' : 'bg-gradient-to-br from-orange-500 to-orange-600'">
                        <img src="/images/logo.png" alt="OposChat" class="w-6 h-6 object-contain" />
</div>
                    <span v-if="!isCollapsed" class="font-semibold text-lg" :class="isDark ? 'text-white' : 'text-gray-900'">OPOSCHAT</span>
                </Link>

                <!-- Collapse Toggle Button -->
                <Button 
                    @click="emit('toggleCollapse')"
                    variant="ghost" 
                    size="sm" 
                    class="p-2.5 rounded-xl transition-all duration-200 inline-flex"
                    :class="isDark ? 'text-gray-400 hover:bg-gray-700 hover:text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <ChevronsRight v-if="isCollapsed" class="w-4 h-4" />
                    <ChevronsLeft v-else class="w-4 h-4" />
                </Button>
                
                
            </div>
            
            <!-- New Chat Button -->
            <Button 
                @click="createNewChat"
                class="mb-3"
                :class="{ 
                    'w-full justify-start': !isCollapsed, 
                    'w-8 h-8 p-0': isCollapsed,
                    'bg-gray-800 hover:bg-gray-700 text-white border-gray-600': isDark,
                    'bg-gray-100 hover:bg-gray-200 text-gray-900 border-gray-300': !isDark
                }"
                variant="outline"
                :title="isCollapsed ? 'New chat' : ''"
            >
                <MessageSquarePlus class="w-3 h-3" :class="{ 'mr-2': !isCollapsed }" />
                <span v-if="!isCollapsed" :class="isDark ? 'text-white' : 'text-gray-900'">New chat</span>
            </Button>

            <!-- Search -->
            <div v-if="!isCollapsed" class="relative">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                        :class="isDark ? 'text-gray-400' : 'text-gray-500'" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search chats..."
                    class="w-full rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    :class="isDark 
                        ? 'bg-gray-800 border border-gray-600 text-white placeholder-gray-400' 
                        : 'bg-gray-50 border border-gray-300 text-gray-900 placeholder-gray-500'"
                />
            </div>
        </div>


        <!-- Chat History -->
        <div class="flex-1 overflow-y-auto p-2 scrollbar-thin">
            <!-- Loading State -->
            <div v-if="isLoading" class="text-center py-8">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500 mx-auto"></div>
                <p class="text-sm mt-2" :class="isDark ? 'text-gray-400' : 'text-gray-600'">Loading chats...</p>
            </div>
            
            <!-- Chat List -->
            <div v-else class="space-y-1">
                <div v-for="chat in filteredChats" :key="chat.id" 
                     @click="selectChat(chat.id.toString())"
                     class="group relative rounded-lg cursor-pointer transition-colors"
                     :class="{ 
                         'p-3': !isCollapsed,
                         'p-2 flex justify-center': isCollapsed,
                         'bg-gray-800 hover:bg-gray-700': isDark && chat.isActive,
                         'bg-gray-100 hover:bg-gray-200': !isDark && chat.isActive,
                         'hover:bg-gray-800': isDark && !chat.isActive,
                         'hover:bg-gray-100': !isDark && !chat.isActive
                     }"
                     :title="isCollapsed ? (chat.title || 'New Chat') : ''">
                    <!-- Collapsed View - Just Icon -->
                    <div v-if="isCollapsed" class="w-6 h-6 rounded-lg flex items-center justify-center"
                         :class="chat.isActive 
                             ? (isDark ? 'bg-orange-500' : 'bg-orange-500')
                             : (isDark ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-200 hover:bg-gray-300')">
                        <svg class="w-3 h-3" :class="chat.isActive ? 'text-white' : (isDark ? 'text-gray-300' : 'text-gray-600')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    
                    <!-- Expanded View - Full Chat Info -->
                    <div v-else class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-medium truncate" 
                                :class="isDark ? 'text-white' : 'text-gray-900'">
                                {{ chat.title || 'New Chat' }}
                            </h3>
                            <p v-if="chat.lastMessage" class="text-xs truncate mt-1"
                               :class="isDark ? 'text-gray-400' : 'text-gray-600'">
                                {{ chat.lastMessage }}
                            </p>
                            <p v-if="chat.timestamp" class="text-xs mt-1"
                               :class="isDark ? 'text-gray-500' : 'text-gray-500'">
                                {{ chat.timestamp }}
                            </p>
                        </div>
                        <div class="opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity flex space-x-1">
                            <Button 
                                size="sm" 
                                variant="ghost" 
                                class="h-6 w-6 p-0 md:h-5 md:w-5"
                                :class="isDark ? 'text-gray-400 hover:text-blue-400' : 'text-gray-500 hover:text-blue-600'"
                                @click="openEditChat(chat, $event)"
                                title="Rename chat"
                            >
                                <Edit3 class="w-3 h-3 md:w-2.5 md:h-2.5" />
                            </Button>
                            <Button 
                                size="sm" 
                                variant="ghost" 
                                class="h-6 w-6 p-0 md:h-5 md:w-5"
                                :class="isDark ? 'text-gray-400 hover:text-red-400' : 'text-gray-500 hover:text-red-600'"
                                @click="deleteChat(chat.id.toString(), $event)"
                                title="Delete chat"
                            >
                                <Trash2 class="w-3 h-3 md:w-2.5 md:h-2.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && filteredChats.length === 0" class="text-center py-8">
                <MessageSquarePlus class="w-12 h-12 mx-auto mb-3" 
                                   :class="isDark ? 'text-gray-600' : 'text-gray-400'" />
                <p class="text-sm mb-2" :class="isDark ? 'text-gray-400' : 'text-gray-600'">No chats yet</p>
                <p class="text-xs" :class="isDark ? 'text-gray-500' : 'text-gray-500'">Start a new conversation to get started</p>
            </div>
        </div>

        <!-- User Profile Section -->
        <div class="p-8 border-t" :class="isDark ? 'border-gray-700' : 'border-gray-200'">
            <div class="relative">
                <Button 
                    @click="showUserMenu = !showUserMenu"
                    class=""
                    :class="{ 
                        'w-full justify-center p-3': !isCollapsed, 
                        'w-10 h-10 p-0': isCollapsed,
                        'bg-gray-800 hover:bg-gray-700 text-white': isDark,
                        'bg-gray-100 hover:bg-gray-200 text-gray-900': !isDark
                    }"
                    variant="ghost"
                    :title="isCollapsed ? 'User Menu' : ''"
                >
                    <div class="w-6 h-6 rounded-full flex items-center justify-center" :class="{ 'mr-3': !isCollapsed, 'bg-orange-500': isCollapsed }">
                        <User class="w-3 h-3" :class="isCollapsed ? 'text-white' : (isDark ? 'text-white' : 'text-gray-600')" />
                    </div>
                    <div v-if="!isCollapsed" class="flex-1 text-center">
                        <div class="text-sm font-medium flex items-center justify-center mb-2" :class="isDark ? 'text-white' : 'text-gray-900'">
                            <span>{{ user?.name || 'User' }}</span>
                            <span v-if="hasPaidPlan" class="ml-2 px-1 py-0.5 text-xs bg-yellow-500 text-black rounded-full font-medium">
                                {{ displayPlanName }}
                            </span>
                        </div>
                        <div class="text-xs mb-1 px-3 py-2 rounded-lg flex items-center justify-center gap-2" :class="isDark ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-600'">
                            <span class="truncate">{{ user?.email }}</span>
                        </div>
                        <div class="text-xs flex items-center justify-center gap-2" :class="isDark ? 'text-gray-500' : 'text-gray-600'">
                            <span>Plan: {{ displayPlanName }}</span>
                            <button 
                                @click="refreshSubscriptionData"
                                class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                                title="Refresh subscription status"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </Button>

                <!-- User Menu Dropdown -->
                <div v-if="showUserMenu"
                     class="absolute bottom-full left-0 right-0 mb-2 rounded-lg shadow-lg py-1 z-50"
                     :class="isDark
                         ? 'bg-gray-800 border border-gray-700'
                         : 'bg-gray-100 border border-gray-200'">

                    <!-- Usage Indicators -->
                    <div v-if="usage.chat_messages && !usage.chat_messages.unlimited" class="px-2 py-1">
                        <UsageIndicator
                            feature="chat_messages"
                            feature-name="Chat Messages"
                            :usage="usage.chat_messages"
                            :has-premium="hasPremium"
                            @upgrade="handleUpgradeClick"
                        />
                    </div>

                    <div v-if="usage.file_uploads && !usage.file_uploads.unlimited" class="px-2 py-1">
                        <UsageIndicator
                            feature="file_uploads"
                            feature-name="File Uploads"
                            :usage="usage.file_uploads"
                            :has-premium="hasPremium"
                            @upgrade="handleUpgradeClick"
                        />
                    </div>

                    <button @click="openSettings"
                            class="w-full flex items-center px-3 py-1 text-sm transition-colors"
                            :class="isDark
                                ? 'text-gray-300 hover:bg-gray-700 hover:text-white'
                                : 'text-gray-700 hover:bg-gray-200 hover:text-gray-900'">
                        <Settings class="w-4 h-4 mr-3" />
                        Settings
                    </button>
                    <Separator class="my-1" :class="isDark ? 'bg-gray-700' : 'bg-gray-200'" />
                    <button @click="logout"
                            class="w-full flex items-center px-3 py-1 text-sm transition-colors"
                            :class="isDark
                                ? 'text-gray-300 hover:bg-gray-700 hover:text-white'
                                : 'text-gray-700 hover:bg-gray-200 hover:text-gray-900'">
                        <LogOut class="w-4 h-4 mr-3" />
                        Sign out
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Chat Modal -->
        <EditChatModal
            :is-open="showEditModal"
            :initial-title="editTargetTitle"
            @close="showEditModal = false"
            @save="handleEditSave"
        />

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
            :show-wait-option="subscriptionPromptData.showWaitOption"
            :usage-info="subscriptionPromptData.usageInfo"
            @close="showSubscriptionPrompt = false"
            @upgrade="handleUpgradeClick"
            @wait="handleWaitClick"
        />
    </div>
</template>