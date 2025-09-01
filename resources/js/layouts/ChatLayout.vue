<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ChatSidebar from '@/components/ChatSidebar.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import SettingsModal from '@/components/SettingsModal.vue';
import SubscriptionPrompt from '@/components/SubscriptionPrompt.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';
import UsageIndicator from '@/components/UsageIndicator.vue';

import { useSubscription } from '@/composables/useSubscription.js';
import chatApi from '@/services/chatApi.js';
import streamingChatService from '@/services/streamingChatService.js';
import { Send, User, Bot, Paperclip, Mic, Settings, Menu, Download, BarChart3 } from 'lucide-vue-next';

interface Message {
    id: string;
    content: string;
    role: 'user' | 'assistant';
    timestamp: string;
    isStreaming?: boolean;
    streamingContent?: string;
    sessionId?: string;
}

const page = usePage();
const user = computed(() => page.props.auth.user);

const currentMessage = ref('');
const isTyping = ref(false);
const isLoading = ref(false);

// Real chat data
const messages = ref<Message[]>([]);
const currentChat = ref<{ id: string; title: string } | null>(null);
const showMobileSidebar = ref(false);
const showSettingsModal = ref(false);
const showSubscriptionPrompt = ref(false);
const subscriptionPromptData = ref({});

// Success modal state
const showSuccessModal = ref(false);

// Check for success parameter in URL or page props
const checkForSuccess = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const hasSuccessParam = urlParams.get('success') === 'true';
    const hasSuccessProp = page.props.showSubscriptionSuccess;
    
    if (hasSuccessParam || hasSuccessProp) {
        showSuccessModal.value = true;
        // Clean up URL without reloading
        if (hasSuccessParam) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
};

// Check on mount and when page props change
watch(() => page.props, checkForSuccess, { immediate: true });

// Cleanup streaming connections when component unmounts
onUnmounted(() => {
    streamingChatService.stopAllStreaming();
});

// Subscription management
const {
    hasPremium,
    usage,
    hasFeatureAccess,
    getRemainingUsage,
    getUsagePercentage,
    fetchSubscriptionStatus
} = useSubscription();

// Handle chat selection from sidebar
const handleChatSelected = async (chatId: string | null) => {
    // Close mobile sidebar when a chat is selected
    showMobileSidebar.value = false;
    
    if (!chatId) {
        currentChat.value = null;
        messages.value = [];
        return;
    }

    try {
        isLoading.value = true;
        const chatData = await chatApi.getChat(chatId);
        currentChat.value = chatData.chat;
        messages.value = chatData.messages;
    } catch (error) {
        console.error('Failed to load chat:', error);
    } finally {
        isLoading.value = false;
    }
};

// Handle new chat creation from sidebar
const handleNewChatCreated = (newChat: any) => {
    // Close mobile sidebar when a new chat is created
    showMobileSidebar.value = false;
    
    // Set the new chat as current
    currentChat.value = {
        id: newChat.id.toString(),
        title: newChat.title || 'New Chat'
    };
    
    // Clear messages for new chat
    messages.value = [];
    
    // Stop any active streaming
    if (streamingChatService) {
        streamingChatService.stopAllStreaming();
    }
    
    // Reset typing state
    isTyping.value = false;
};

// Send message to current chat using streaming
const sendMessage = async () => {
    if (!currentMessage.value.trim() || !currentChat.value) return;

    // Check if user has exceeded usage limits
    if (!hasFeatureAccess('chat_messages')) {
        const chatUsage = usage.value.chat_messages;
        subscriptionPromptData.value = {
            title: 'Daily Limit Reached',
            message: 'You\'ve reached your daily message limit. Upgrade to premium for unlimited messages.',
            usageInfo: {
                feature_name: 'Chat Messages',
                usage: chatUsage?.usage || 0,
                limit: chatUsage?.limit || 0,
                percentage: chatUsage?.percentage || 0
            }
        };
        showSubscriptionPrompt.value = true;
        return;
    }

    const messageContent = currentMessage.value;
    currentMessage.value = '';
    isTyping.value = true;

    // Add user message immediately
    const userMessage = {
        id: `temp-${Date.now()}`,
        content: messageContent,
        role: 'user' as const,
        timestamp: 'now',
    };
    messages.value.push(userMessage);

    // Update chat title if this is the first message
    if (messages.value.length === 1) {
        currentChat.value!.title = 'New Chat';
    }

    // Create a temporary assistant message for streaming
    const assistantMessage = {
        id: `streaming-${Date.now()}`,
        content: '',
        role: 'assistant' as const,
        timestamp: 'now',
        isStreaming: true,
        streamingContent: '',
        sessionId: '',
    };
    messages.value.push(assistantMessage);

    try {
        // Start streaming
        streamingChatService.startStreaming(
            currentChat.value.id,
            messageContent,
            // onChunk callback
            (chunk, accumulatedContent, formattedContent) => {
                // Update the streaming message content
                const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
                if (messageIndex !== -1) {
                    messages.value[messageIndex].streamingContent = formattedContent;
                    messages.value[messageIndex].content = accumulatedContent;
                }
            },
            // onComplete callback
            (messageId, finalContent) => {
                // Update the message with final content and remove streaming state
                const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
                if (messageIndex !== -1) {
                    // Keep the current content (which is already properly formatted during streaming)
                    // instead of replacing with raw finalContent
                    messages.value[messageIndex].id = messageId;
                    messages.value[messageIndex].content = messages.value[messageIndex].content || finalContent;
                    messages.value[messageIndex].isStreaming = false;
                    messages.value[messageIndex].streamingContent = '';
                    messages.value[messageIndex].sessionId = '';
                }
                isTyping.value = false;
            },
            // onError callback
            (error) => {
                console.error('Streaming error:', error);
                
                // Try fallback to regular chat API
                fallbackToRegularChat(messageContent, assistantMessage.id);
            }
        );

        // Update session ID for the streaming message
        setTimeout(() => {
            const sessionId = streamingChatService.getSessionId(currentChat.value!.id);
            if (sessionId) {
                const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
                if (messageIndex !== -1) {
                    messages.value[messageIndex].sessionId = sessionId;
                }
            }
        }, 100);

    } catch (error) {
        console.error('Failed to start streaming:', error);
        // Remove the streaming message
        const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
        if (messageIndex !== -1) {
            messages.value.splice(messageIndex, 1);
        }
        isTyping.value = false;
        // Re-add the message to input on error
        currentMessage.value = messageContent;
    }
};

// Stop streaming for a specific message
const stopStreaming = async (sessionId: string) => {
    try {
        await streamingChatService.stopStreaming(currentChat.value!.id);
        
        // Find and update the streaming message
        const messageIndex = messages.value.findIndex(m => m.sessionId === sessionId);
        if (messageIndex !== -1) {
            messages.value[messageIndex].isStreaming = false;
            messages.value[messageIndex].streamingContent = '';
            messages.value[messageIndex].sessionId = '';
        }
        
        isTyping.value = false;
    } catch (error) {
        console.error('Failed to stop streaming:', error);
    }
};

// Fallback to regular chat API when streaming fails
const fallbackToRegularChat = async (messageContent: string, streamingMessageId: string) => {
    try {
        console.log('Falling back to regular chat API...');
        
        const response = await chatApi.sendMessage(currentChat.value!.id, messageContent);
        
        // Replace the streaming message with the regular response
        const messageIndex = messages.value.findIndex(m => m.id === streamingMessageId);
        if (messageIndex !== -1) {
            messages.value[messageIndex] = response.assistantMessage;
        }
        
        // Update chat title if it changed
        if (response.chat.title !== currentChat.value!.title) {
            currentChat.value!.title = response.chat.title;
        }
        
    } catch (error) {
        console.error('Fallback also failed:', error);
        // Remove the streaming message and show error
        const messageIndex = messages.value.findIndex(m => m.id === streamingMessageId);
        if (messageIndex !== -1) {
            messages.value.splice(messageIndex, 1);
        }
        // Re-add the message to input on error
        currentMessage.value = messageContent;
    } finally {
        isTyping.value = false;
    }
};

// Create new chat if none exists
const createNewChatIfNeeded = async () => {
    if (!currentChat.value && currentMessage.value.trim()) {
        try {
            const newChat = await chatApi.createChat();
            currentChat.value = { id: newChat.id.toString(), title: newChat.title };
            messages.value = [];
            // Note: The sidebar will be updated when the user creates a new chat via the button
        } catch (error) {
            console.error('Failed to create new chat:', error);
            throw error; // Re-throw to handle in the calling function
        }
    }
};

const handleKeyDown = async (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        try {
            await createNewChatIfNeeded();
            await sendMessage();
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    }
};

// Premium features
const exportCurrentChat = async () => {
    if (!currentChat.value) return;
    
    if (!hasPremium.value) {
        subscriptionPromptData.value = {
            title: 'Premium Feature',
            message: 'Chat export is a premium feature. Upgrade to access this functionality.'
        };
        showSubscriptionPrompt.value = true;
        return;
    }

    try {
        const response = await chatApi.exportChat(currentChat.value.id);
        
        // Create and download the file
        const blob = new Blob([JSON.stringify(response.export_data, null, 2)], {
            type: 'application/json'
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = response.filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (error) {
        if (error.response?.status === 403) {
            subscriptionPromptData.value = {
                type: 'subscription_required',
                title: 'Premium Feature',
                message: error.response.data.details?.user_message || 'This feature requires a premium subscription.'
            };
            showSubscriptionPrompt.value = true;
        } else {
            console.error('Failed to export chat:', error);
        }
    }
};

const viewAnalytics = async () => {
    if (!hasPremium.value) {
        subscriptionPromptData.value = {
            title: 'Premium Feature',
            message: 'Advanced analytics is a premium feature. Upgrade to access detailed insights.'
        };
        showSubscriptionPrompt.value = true;
        return;
    }

    try {
        const analytics = await chatApi.getAnalytics();
        console.log('Analytics:', analytics);
        // TODO: Show analytics in a modal or navigate to analytics page
        alert('Analytics feature coming soon! Check console for data.');
    } catch (error) {
        if (error.response?.status === 403) {
            subscriptionPromptData.value = {
                type: 'subscription_required',
                title: 'Premium Feature',
                message: error.response.data.details?.user_message || 'This feature requires a premium subscription.'
            };
            showSubscriptionPrompt.value = true;
        } else {
            console.error('Failed to get analytics:', error);
        }
    }
};

const handleUpgradeClick = () => {
    showSubscriptionPrompt.value = false;
    showSettingsModal.value = true;
};
</script>

<template>
    <Head title="OposChat - Dashboard" />
    
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Left Sidebar - Hidden on mobile, shown on desktop -->
        <div class="hidden lg:block">
            <ChatSidebar @chat-selected="handleChatSelected" @new-chat-created="handleNewChatCreated" />
        </div>
        
        <!-- Mobile Sidebar Overlay -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            leave-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div v-if="showMobileSidebar" class="fixed inset-0 z-50 lg:hidden">
                <div class="absolute inset-0 bg-white bg-opacity-20 backdrop-blur-sm" @click="showMobileSidebar = false"></div>
                <Transition
                    enter-active-class="transition-transform duration-300"
                    leave-active-class="transition-transform duration-300"
                    enter-from-class="-translate-x-full"
                    leave-to-class="-translate-x-full"
                >
                    <div v-if="showMobileSidebar" class="relative w-80 h-full shadow-2xl">
                        <ChatSidebar 
                            :is-mobile="true"
                            @chat-selected="handleChatSelected" 
                            @new-chat-created="handleNewChatCreated"
                            @close-mobile="showMobileSidebar = false"
                        />
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col">
            <!-- Chat Header -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <!-- Mobile Menu Button -->
                        <Button 
                            @click="showMobileSidebar = true"
                            variant="ghost" 
                            size="sm" 
                            class="lg:hidden p-2"
                        >
                            <Menu class="w-5 h-5" />
                        </Button>
                        
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ currentChat?.title || 'OposChat' }}
                        </h1>
                    </div>
                    <div class="flex items-center space-x-2">
                        <!-- Premium Features -->
                        <div v-if="currentChat" class="hidden md:flex items-center space-x-2">
                            <!-- Export Chat Button -->
                            <Button 
                                @click="exportCurrentChat"
                                variant="ghost" 
                                size="sm" 
                                class="p-2 text-gray-500 hover:text-gray-700"
                                :class="{ 'text-yellow-600': hasPremium }"
                                title="Export Chat"
                            >
                                <Download class="w-4 h-4" />
                            </Button>
                            
                            <!-- Analytics Button -->
                            <Button 
                                @click="viewAnalytics"
                                variant="ghost" 
                                size="sm" 
                                class="p-2 text-gray-500 hover:text-gray-700"
                                :class="{ 'text-yellow-600': hasPremium }"
                                title="View Analytics"
                            >
                                <BarChart3 class="w-4 h-4" />
                            </Button>
                        </div>
                        
                        <span class="hidden md:inline text-sm text-gray-500">Profile</span>
                        <span class="hidden md:inline text-sm text-gray-500">Exams</span>
                        <span class="hidden sm:inline text-sm text-gray-500">Hello, {{ user?.name || 'User' }}</span>
                        
                        <!-- Settings Button -->
                        <Button 
                            @click="showSettingsModal = true"
                            variant="ghost" 
                            size="sm" 
                            class="p-2"
                        >
                            <Settings class="w-4 h-4" />
                        </Button>
                        
                        <Button size="sm" variant="outline" class="text-xs hidden sm:inline-flex">
                            {{ user?.name?.toUpperCase() || 'USER' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Usage Indicator -->
            <UsageIndicator
                v-if="!hasPremium && usage.chat_messages"
                feature="chat_messages"
                feature-name="Chat Messages"
                :usage="usage.chat_messages"
                :has-premium="hasPremium"
                @upgrade="handleUpgradeClick"
                class="mx-4 mt-2"
            />

            <!-- Chat Messages Area -->
            <div class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900 chat-scrollbar">
                <div class="max-w-4xl mx-auto px-6 py-6">
                    <!-- Loading State -->
                    <div v-if="isLoading" class="flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"></div>
                        <span class="ml-3 text-gray-600 dark:text-gray-400">Loading chat...</span>
                    </div>
                    
                    <!-- Welcome State -->
                    <div v-else-if="!currentChat" class="flex flex-col items-center justify-center min-h-[60vh] relative">
                        <!-- Background Image -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-10">
                            <img src="/images/dashboard-background.svg" alt="Background" class="max-w-md max-h-96 object-contain" />
                        </div>
                        
                        <!-- Content -->
                        <div class="relative z-10 text-center">
                            <div class="w-20 h-20 bg-orange-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                                <Bot class="w-10 h-10 text-white" />
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                                Welcome to OposChat
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto leading-relaxed">
                                Start a conversation to get help with your civil service exam preparation. 
                                Our AI assistant is here to guide you through your studies.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                    AI-Powered Learning
                                </div>
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                    Exam Preparation
                                </div>
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                                    24/7 Available
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div v-else class="space-y-6">
                        <ChatMessage 
                            v-for="message in messages" 
                            :key="message.id" 
                            :message="message" 
                            :on-stop-streaming="stopStreaming"
                        />
                        
                        <!-- Typing Indicator -->
                        <div v-if="isTyping" class="flex items-start space-x-4">
                            <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center">
                                <Bot class="w-4 h-4 text-white" />
                            </div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                                <div class="flex space-x-1">
                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Input -->
            <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4">
                <div class="max-w-4xl mx-auto">
                    <div class="flex items-end space-x-4">
                        <!-- Attachment Button -->
                        <Button variant="ghost" size="sm" class="text-gray-500 hover:text-gray-700 p-2">
                            <Paperclip class="w-5 h-5" />
                        </Button>
                        
                        <!-- Message Input -->
                        <div class="flex-1 relative">
                            <textarea
                                v-model="currentMessage"
                                @keydown="handleKeyDown"
                                placeholder="Type a message..."
                                rows="1"
                                class="w-full resize-none pr-12 py-3 px-4 text-base border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                                style="min-height: 48px; max-height: 120px;"
                                :disabled="isTyping"
                            ></textarea>
                            <Button
                                @click="async () => { 
                                    try {
                                        await createNewChatIfNeeded(); 
                                        await sendMessage(); 
                                    } catch (error) {
                                        console.error('Failed to send message:', error);
                                    }
                                }"
                                :disabled="!currentMessage.trim() || isTyping"
                                class="absolute right-2 bottom-2 bg-orange-500 hover:bg-orange-600 text-white p-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                size="sm"
                            >
                                <Send class="w-4 h-4" />
                            </Button>
                        </div>
                        
                        <!-- Voice Button -->
                        <Button variant="ghost" size="sm" class="text-gray-500 hover:text-gray-700 p-2">
                            <Mic class="w-5 h-5" />
                        </Button>
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex items-center justify-center mt-4 space-x-6 text-xs text-gray-500">
                        <a href="#" class="hover:text-gray-700 transition-colors">About us</a>
                        <a href="#" class="hover:text-gray-700 transition-colors">Privacy policy</a>
                        <a href="#" class="hover:text-gray-700 transition-colors">Contact us</a>
                        <div class="flex space-x-2">
                            <span class="cursor-pointer hover:scale-110 transition-transform">📷</span>
                            <span class="cursor-pointer hover:scale-110 transition-transform">📱</span>
                            <span class="cursor-pointer hover:scale-110 transition-transform">🐦</span>
                            <span class="cursor-pointer hover:scale-110 transition-transform">📧</span>
                        </div>
                    </div>
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

        <!-- Subscription Success Modal -->
        <SubscriptionSuccessModal 
            :show="showSuccessModal"
            :subscription="subscription"
            @close="showSuccessModal = false"
        />
    </div>
</template>