<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ChatSidebar from '@/components/ChatSidebar.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import SettingsModal from '@/components/SettingsModal.vue';
import SubscriptionPrompt from '@/components/SubscriptionPrompt.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';
import CourseSelector from '@/components/CourseSelector.vue';
import ToastContainer from '@/components/ui/toast/ToastContainer.vue';

import { useSubscription } from '@/composables/useSubscription.js';
import chatApi from '@/services/chatApi.js';
import streamingChatService from '@/services/streamingChatService.js';
import { Send, User, Bot, Paperclip, Settings, Download, BarChart3, Pencil, Sun, Moon, Mic, Square, ChevronsRight } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { useAudioRecording } from '@/composables/useAudioRecording';

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
const props = defineProps<{ initialChatId?: string | number | null }>();
const user = computed(() => page.props.auth.user);

const currentMessage = ref('');
const isTyping = ref(false);
const isLoading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isProcessingFile = ref(false);
const imagePreviewUrl = ref<string | null>(null);
const chatContainer = ref<HTMLDivElement | null>(null);

// Real chat data
const messages = ref<Message[]>([]);
const currentChat = ref<{ id: string; title: string; course_id?: number } | null>(null);
const currentCourse = ref<{ id: number; name: string } | null>(null);
const showCourseRequired = ref(false);
const showMobileSidebar = ref(false);

// Persist selected course per chat in localStorage
const storageKeyForChat = (chatId: string) => `oposchat:selectedCourse:${chatId}`;

// Persist current chat ID in localStorage
const CURRENT_CHAT_KEY = 'oposchat:currentChatId';

const saveSelectedCourse = () => {
    if (currentChat.value?.id) {
        const key = storageKeyForChat(currentChat.value.id.toString());
        if (currentCourse.value) {
            localStorage.setItem(key, JSON.stringify(currentCourse.value));
        } else {
            localStorage.removeItem(key);
        }
    }
};

const loadSelectedCourse = (chatId: string) => {
    try {
        const raw = localStorage.getItem(storageKeyForChat(chatId));
        if (raw) {
            const stored = JSON.parse(raw);
            if (stored && typeof stored.id === 'number') {
                currentCourse.value = { id: stored.id, name: stored.name };
                if (currentChat.value && !currentChat.value.course_id) {
                    currentChat.value.course_id = stored.id;
                }
            }
        }
    } catch (e) {
        // ignore malformed storage
    }
};

// Save current chat ID to localStorage
const saveCurrentChat = (chatId: string) => {
    try {
        localStorage.setItem(CURRENT_CHAT_KEY, chatId);
    } catch (e) {
        console.error('Failed to save current chat:', e);
    }
};

// Load current chat ID from localStorage
const loadCurrentChat = () => {
    try {
        return localStorage.getItem(CURRENT_CHAT_KEY);
    } catch (e) {
        console.error('Failed to load current chat:', e);
        return null;
    }
};

// Clear current chat from localStorage
const clearCurrentChat = () => {
    try {
        localStorage.removeItem(CURRENT_CHAT_KEY);
    } catch (e) {
        console.error('Failed to clear current chat:', e);
    }
};

// Helper to focus/open the course selector when required
const courseSelectorRef = ref<HTMLElement | null>(null);
const focusCourseSelector = () => {
    if (courseSelectorRef.value) {
        courseSelectorRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};
const isExamSectionCollapsed = ref(true);
const isSidebarCollapsed = ref(false);
const showSettingsModal = ref(false);
const showSubscriptionPrompt = ref(false);
const subscriptionPromptData = ref({});

// Success modal state
const showSuccessModal = ref(false);

// Audio recording
const { isRecording, isSupported, error: recordingError, startRecording, stopRecording, checkSupport } = useAudioRecording();
const isTranscribing = ref(false);

// Mobile zoom/scroll fix
let resizeTimeout: ReturnType<typeof setTimeout> | null = null;
const handleMobileZoom = () => {
    // Clear previous timeout
    if (resizeTimeout) {
        clearTimeout(resizeTimeout);
    }
    
    // Debounce to avoid excessive calls
    resizeTimeout = setTimeout(() => {
        // Force recalculation of viewport on mobile to prevent input bar scrolling
        if (window.innerWidth < 1024) {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
    }, 150);
};

// Check audio recording support on mount
onMounted(() => {
    checkSupport();
    window.addEventListener('resize', handleResize);
    
    // Add mobile zoom/scroll fix
    handleMobileZoom();
    window.addEventListener('resize', handleMobileZoom);
    window.addEventListener('orientationchange', handleMobileZoom);
});

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

// Handle window resize to close mobile sidebar when switching to desktop
const handleResize = () => {
    if (window.innerWidth >= 1024) {
        showMobileSidebar.value = false;
    }
};

// Cleanup streaming connections when component unmounts
onUnmounted(() => {
    streamingChatService.stopAllStreaming();
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('resize', handleMobileZoom);
    window.removeEventListener('orientationchange', handleMobileZoom);
    if (resizeTimeout) {
        clearTimeout(resizeTimeout);
    }
});

// Subscription management
const {
    hasPremium,
    usage,
    hasFeatureAccess,
    getRemainingUsage,
    getUsagePercentage,
    currentPlanName,
    fetchSubscriptionStatus,
    fetchUsageData
} = useSubscription();

// Track if user has manually scrolled up
const userScrolledUp = ref(false);
const lastScrollPosition = ref(0);

// Check if user is near the bottom of the chat
const isNearBottom = () => {
    const container = chatContainer.value || document.querySelector('.chat-scrollbar');
    if (!container) return true;
    
    const scrollTop = container.scrollTop;
    const scrollHeight = container.scrollHeight;
    const clientHeight = container.clientHeight;
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
    
    // Consider "near bottom" if within 150px
    return distanceFromBottom < 150;
};

// Track scroll position to detect user scrolling up
// Use throttle to avoid too many calls
let scrollCheckTimeout: ReturnType<typeof setTimeout> | null = null;
const handleScroll = () => {
    // Throttle scroll checks
    if (scrollCheckTimeout) {
        return;
    }
    
    scrollCheckTimeout = setTimeout(() => {
        const container = chatContainer.value || document.querySelector('.chat-scrollbar');
        if (!container) return;
        
        const scrollTop = container.scrollTop;
        const scrollHeight = container.scrollHeight;
        const clientHeight = container.clientHeight;
        const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
        
        // Use consistent threshold - respect user scroll intent at all times
        const threshold = 150;
        
        // User scrolled up if they're more than threshold from bottom and scrolling up
        // This works the same way whether streaming or not - always respect user intent
        userScrolledUp.value = distanceFromBottom > threshold && scrollTop < lastScrollPosition.value;
        lastScrollPosition.value = scrollTop;
        
        scrollCheckTimeout = null;
    }, 50);
};

// Debounced scroll function
let scrollTimeout: ReturnType<typeof setTimeout> | null = null;
let lastScrollTime = 0;
const SCROLL_THROTTLE_MS = 100; // Throttle scroll calls during streaming

const scrollToBottom = async (force = false, instant = false) => {
    // Don't auto-scroll if user has manually scrolled up (unless forced)
    if (!force && userScrolledUp.value) {
        return;
    }
    
    // During streaming, use instant scroll and throttle more aggressively
    const now = Date.now();
    const timeSinceLastScroll = now - lastScrollTime;
    
    // If not instant and we recently scrolled, skip this call
    if (!instant && timeSinceLastScroll < SCROLL_THROTTLE_MS && messages.value.some(m => m.isStreaming)) {
        return;
    }
    
    // Clear any pending scroll
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
    }
    
    await nextTick();
    
    scrollTimeout = setTimeout(() => {
        const container = chatContainer.value || document.querySelector('.chat-scrollbar');
        if (container) {
            // During streaming, use instant scroll to prevent jitter
            // Otherwise use smooth scroll
            const scrollBehavior = (instant || messages.value.some(m => m.isStreaming)) ? 'auto' : 'smooth';
            
            container.scrollTo({
                top: container.scrollHeight,
                behavior: scrollBehavior
            });
            
            lastScrollTime = Date.now();
            
            // Reset the user scrolled up flag if we're forcing scroll
            if (force) {
                userScrolledUp.value = false;
            }
        }
    }, instant ? 0 : 50);
};

// Handle chat selection from sidebar
const handleChatSelected = async (chatId: string | null) => {
    // Close mobile sidebar when a chat is selected
    showMobileSidebar.value = false;

    if (!chatId) {
        currentChat.value = null;
        messages.value = [];
        clearCurrentChat();
        return;
    }

    try {
        isLoading.value = true;
        const chatData = await chatApi.getChat(chatId);
        currentChat.value = {
            id: chatData.chat.id.toString(),
            title: chatData.chat.title,
            course_id: chatData.chat.course_id
        };
        messages.value = chatData.messages;
        // Try to hydrate course selection from storage
        loadSelectedCourse(currentChat.value.id.toString());
        showCourseRequired.value = !currentChat.value.course_id;
        // Save current chat ID to localStorage
        saveCurrentChat(chatId);
        
        // Wait for messages to render and then scroll to bottom
        await nextTick();
        setTimeout(async () => {
            await scrollToBottom(true); // Force scroll when loading a chat
        }, 200);
    } catch (error) {
        console.error('Failed to load chat:', error);
    } finally {
        isLoading.value = false;
    }
};

// Watch for new messages being added (only when not streaming to avoid jitter)
watch(() => messages.value.length, async (newLength, oldLength) => {
    // Only scroll on new messages, not content updates
    if (newLength > oldLength && newLength > 0) {
        // Check if we're near bottom before auto-scrolling
        if (isNearBottom()) {
            await scrollToBottom();
        }
    }
}, { flush: 'post' });

// Track if Mermaid is rendering to prevent scroll conflicts
const isMermaidRendering = ref(false);

// Watch for Mermaid rendering state changes (via custom event from ChatMessage)
const handleMermaidStart = () => {
    isMermaidRendering.value = true;
};

const handleMermaidComplete = () => {
    isMermaidRendering.value = false;
    // After Mermaid rendering, scroll if near bottom
    if (isNearBottom()) {
        setTimeout(() => scrollToBottom(), 100);
    }
};

onMounted(() => {
    window.addEventListener('mermaid-rendering-start', handleMermaidStart);
    window.addEventListener('mermaid-rendering-complete', handleMermaidComplete);
});

onUnmounted(() => {
    window.removeEventListener('mermaid-rendering-start', handleMermaidStart);
    window.removeEventListener('mermaid-rendering-complete', handleMermaidComplete);
});

// Watch for streaming state changes and scroll when streaming starts
watch(() => messages.value.some(m => m.isStreaming), async (isStreaming, wasStreaming) => {
    // Don't scroll if Mermaid is rendering
    if (isMermaidRendering.value) return;
    
    // When streaming starts, immediately scroll to bottom and lock it there
    if (isStreaming && !wasStreaming) {
        // Reset scroll up flag to allow auto-scrolling during streaming
        userScrolledUp.value = false;
        // Immediately scroll to bottom with instant behavior
        await nextTick();
        scrollToBottom(true, true);
    }
    // When streaming completes, scroll to show final content (but wait for Mermaid to finish)
    if (!isStreaming && wasStreaming && isNearBottom()) {
        await nextTick();
        // Wait a bit for Mermaid to start rendering if needed
        setTimeout(() => {
            if (!isMermaidRendering.value) {
                scrollToBottom(false, false); // Use smooth scroll when complete
            }
        }, 400);
    }
});

// Auto-load chat if provided via prop (from /chat/{id}) or restore from localStorage
onMounted(async () => {
    if (props.initialChatId) {
        // If we have an initial chat ID from the URL/route, use it
        await handleChatSelected(props.initialChatId.toString());
    } else {
        // Otherwise, try to restore the last active chat from localStorage
        const savedChatId = loadCurrentChat();
        if (savedChatId && savedChatId !== 'null' && savedChatId !== 'undefined') {
            await handleChatSelected(savedChatId);
        }
    }
});

// Handle new chat creation from sidebar
const handleNewChatCreated = (newChat: any) => {
    // Close mobile sidebar when a new chat is created
    showMobileSidebar.value = false;

    // Set the new chat as current
    currentChat.value = {
        id: newChat.id.toString(),
        title: newChat.title || 'Nuevo Chat',
        course_id: newChat.course_id
    };
    // Hydrate from storage if present
    loadSelectedCourse(currentChat.value.id.toString());
    showCourseRequired.value = !currentChat.value.course_id;

    // Clear messages for new chat
    messages.value = [];

    // Stop any active streaming
    if (streamingChatService) {
        streamingChatService.stopAllStreaming();
    }

    // Reset typing state
    isTyping.value = false;

    // Save the new chat ID to localStorage
    saveCurrentChat(newChat.id.toString());
};

// Send message to current chat using streaming
const sendMessage = async () => {
    if (!currentMessage.value.trim()) return;
    
    // If no current chat, try to create one first
    if (!currentChat.value) {
        const chatCreated = await createNewChatIfNeeded();
        if (!chatCreated) return; // Chat creation failed or was blocked
    }

    // Require an exam selection before chatting
    if (!currentChat.value.course_id) {
        showCourseRequired.value = true;
        // Nudge user to the selector
        focusCourseSelector();
        return;
    }

    // Check if user has exceeded usage limits
    if (!hasFeatureAccess('chat_messages')) {
        const chatUsage = usage.value.chat_messages;
        const planName = currentPlanName.value;
        
        let title = 'Usage Limit Reached';
        let message = 'You\'ve reached your usage limit. Please upgrade your plan for more access.';
        
        if (planName === 'Free') {
            title = 'Daily Limit Reached';
            message = 'You\'ve reached your daily limit of 3 messages. Upgrade to Premium for 200 messages per month or Plus for unlimited messages.';
        } else if (planName === 'Premium') {
            title = 'Monthly Limit Reached';
            message = 'You\'ve reached your monthly limit of 200 messages. Upgrade to Plus for unlimited messages.';
        }
        
        subscriptionPromptData.value = {
            title,
            message,
            showWaitOption: false, // Remove wait option
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

    let messageContent = currentMessage.value;
    let fullMessageForAI = messageContent;
    
    // Add file content to message if file is selected
    if (selectedFile.value) {
        isProcessingFile.value = true;
        try {
            const fileContent = await extractFileContent(selectedFile.value);
            // Clean message for user display
            messageContent = `[Document: ${selectedFile.value.name}] ${messageContent}`;
            // Full message with document content for AI processing
            fullMessageForAI = `Document: ${selectedFile.value.name}\n\n${currentMessage.value}\n\nDocument Content:\n${fileContent}`;
        } catch (error) {
            console.error('Error reading file:', error);
            messageContent = `[Document: ${selectedFile.value.name}] ${messageContent}`;
            fullMessageForAI = `Document: ${selectedFile.value.name}\n\n${currentMessage.value}\n\n(Note: Could not read file content - ${error.message})`;
        } finally {
            isProcessingFile.value = false;
        }
    }
    
    currentMessage.value = '';
    selectedFile.value = null;
    imagePreviewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
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
        currentChat.value!.title = 'Nuevo Chat';
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
                fullMessageForAI,
                // onChunk callback
                (chunk, accumulatedContent, formattedContent) => {
                    // Update the streaming message content
                    const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
                    if (messageIndex !== -1) {
                        messages.value[messageIndex].streamingContent = formattedContent;
                        messages.value[messageIndex].content = accumulatedContent;
                        
                        // Only auto-scroll if user is near bottom and hasn't manually scrolled up
                        // This allows users to scroll up and read previous messages during streaming
                        if (isNearBottom() && !userScrolledUp.value) {
                            // Use instant scroll during streaming to prevent jitter
                            scrollToBottom(false, true);
                        }
                    }
                },
            // onComplete callback
            async (messageId, finalContent) => {
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
                
                // Refresh usage data after message completion
                try {
                    await fetchUsageData();
                } catch (error) {
                    console.error('Failed to refresh usage data:', error);
                }
            },
            // onError callback
            (error) => {
                console.error('Streaming error:', error);
                
                // Check if this is a usage limit error
                if (error && typeof error === 'object' && error.type === 'USAGE_LIMIT_EXCEEDED') {
                    // Show subscription prompt for usage limit
                    const chatUsage = error.usage?.chat_messages;
                    const planName = currentPlanName.value;
                    
                    let title = 'Usage Limit Reached';
                    let message = error.message || 'You\'ve reached your usage limit. Please upgrade your plan for more access.';
                    
                    subscriptionPromptData.value = {
                        title,
                        message,
                        showWaitOption: false, // Remove wait option
                        usageInfo: {
                            feature_name: 'Chat Messages',
                            usage: chatUsage?.usage || 0,
                            limit: chatUsage?.limit || 0,
                            percentage: chatUsage?.percentage || 0
                        }
                    };
                    showSubscriptionPrompt.value = true;
                    
                    // Remove the streaming message
                    const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
                    if (messageIndex !== -1) {
                        messages.value.splice(messageIndex, 1);
                    }
                    isTyping.value = false;
                    return;
                }
                
                // Try fallback to regular chat API for other errors
                fallbackToRegularChat(fullMessageForAI, assistantMessage.id);
            }
        );

        // Update session ID for the streaming message
        // Try to get sessionId immediately, then check again after a short delay
        const trySetSessionId = () => {
            const sessionId = streamingChatService.getSessionId(currentChat.value!.id);
            if (sessionId) {
                const messageIndex = messages.value.findIndex(m => m.id === assistantMessage.id);
                if (messageIndex !== -1) {
                    messages.value[messageIndex].sessionId = sessionId;
                    console.log('Session ID set on message:', sessionId);
                }
            }
        };
        
        // Try immediately
        trySetSessionId();
        
        // Also try after a delay in case session hasn't started yet
        setTimeout(trySetSessionId, 100);
        setTimeout(trySetSessionId, 500);

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
const stopStreaming = async (sessionId?: string) => {
    console.log('stopStreaming called', { sessionId, chatId: currentChat.value?.id, allMessages: messages.value.map(m => ({ id: m.id, sessionId: m.sessionId, isStreaming: m.isStreaming })) });
    
    if (!currentChat.value?.id) {
        console.error('Cannot stop streaming: no current chat');
        return;
    }
    
    try {
        // Find the message by sessionId, or if sessionId is not provided/found, find the currently streaming message
        let messageIndex = -1;
        let actualSessionId = sessionId || '';
        
        if (sessionId) {
            messageIndex = messages.value.findIndex(m => m.sessionId === sessionId);
        }
        
        // If not found by sessionId, try to find the currently streaming message
        if (messageIndex === -1) {
            messageIndex = messages.value.findIndex(m => m.isStreaming);
            if (messageIndex !== -1) {
                // Get sessionId from the service if not provided
                actualSessionId = sessionId || streamingChatService.getSessionId(currentChat.value.id) || '';
                if (actualSessionId) {
                    messages.value[messageIndex].sessionId = actualSessionId;
                }
            }
        } else {
            // Found by sessionId, use it
            actualSessionId = sessionId || '';
        }
        
        console.log('Message index found:', messageIndex, 'SessionId:', actualSessionId);
        
        // Immediately mark the message as not streaming to prevent further updates
        if (messageIndex !== -1) {
            messages.value[messageIndex].isStreaming = false;
            // Keep the current content, just stop the streaming indicator
            // streamingContent will be kept to show the partial message
        }
        
        // Stop the streaming service (closes EventSource and sends stop request to server)
        // Pass both chatId and sessionId to ensure we can stop even if connection doesn't have sessionId yet
        await streamingChatService.stopStreaming(currentChat.value.id, actualSessionId || undefined);
        
        // Clear session ID after a short delay to allow the stopped event to be processed
        setTimeout(() => {
            if (messageIndex !== -1 && messageIndex < messages.value.length) {
                messages.value[messageIndex].sessionId = '';
            }
        }, 100);
        
        isTyping.value = false;
    } catch (error) {
        console.error('Failed to stop streaming:', error);
        // Still update the UI even if the request fails
        let messageIndex = -1;
        if (sessionId) {
            messageIndex = messages.value.findIndex(m => m.sessionId === sessionId);
        }
        if (messageIndex === -1) {
            messageIndex = messages.value.findIndex(m => m.isStreaming);
        }
        if (messageIndex !== -1) {
            messages.value[messageIndex].isStreaming = false;
            messages.value[messageIndex].sessionId = '';
        }
        isTyping.value = false;
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
        
        // Refresh usage data after fallback completion
        try {
            await fetchUsageData();
        } catch (error) {
            console.error('Failed to refresh usage data after fallback:', error);
        }
    }
};

// Create new chat if none exists
const createNewChatIfNeeded = async () => {
    if (!currentChat.value && currentMessage.value.trim()) {
        try {
            // Check if user can create new conversations
            if (!hasFeatureAccess('chat_messages')) {
                const chatUsage = usage.value.chat_messages;
                subscriptionPromptData.value = {
                    type: 'usage_limit_exceeded',
                    title: 'Daily Limit Reached',
                    message: `You've reached your daily limit of ${chatUsage?.limit || 0} conversations. Upgrade to Premium for 200 messages per month or Plus for unlimited messages.`,
                    resetTime: chatUsage?.reset_time,
                    showWaitOption: false
                };
                showSubscriptionPrompt.value = true;
                return false;
            }

            const newChat = await chatApi.createChat();
            currentChat.value = { 
                id: newChat.id.toString(), 
                title: newChat.title,
                course_id: newChat.course_id 
            };
            messages.value = [];
            
            // Save the new chat ID to localStorage
            saveCurrentChat(newChat.id.toString());
            
            // Refresh usage data
            await fetchUsageData();
            
            return true;
        } catch (error) {
            console.error('Error creating new chat:', error);
            throw error; // Re-throw to handle in the calling function
        }
    }
    return true;
};

const handleKeyDown = async (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        try {
            const chatCreated = await createNewChatIfNeeded();
            if (chatCreated) {
                await sendMessage();
            }
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
    router.visit(route('pricing'));
};

const handleWaitClick = () => {
    showSubscriptionPrompt.value = false;
    // Show a toast or notification that the limit will reset tomorrow
    // For now, just close the modal
};

// Rename chat
const renameCurrentChat = async () => {
    if (!currentChat.value) return;
    const newTitle = prompt('Rename chat', currentChat.value.title || 'Nuevo Chat');
    if (!newTitle || newTitle.trim() === '' || newTitle === currentChat.value.title) return;
    try {
        const updated = await chatApi.updateChat(currentChat.value.id, { title: newTitle.trim() });
        currentChat.value.title = updated.title;
    } catch (error) {
        console.error('Failed to rename chat:', error);
        alert('Could not rename chat. Please try again.');
    }
};

// Handle course selection
const handleCourseSelected = async (course: any) => {
    if (currentChat.value) {
        // Update existing chat with course
        currentChat.value.course_id = course?.id || null;
    } else if (course) {
        // Create new chat with selected course
        try {
            const response = await fetch('/api/chats', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    exam_type: course.slug || course.namespace || course.name,
                    title: `${course.name} Chat`,
                    course_id: course.id
                })
            });

            if (response.ok) {
                const data = await response.json();
                currentChat.value = {
                    id: data.chat.id.toString(),
                    title: data.chat.title,
                    course_id: data.chat.course_id
                };
                messages.value = [];
            } else {
                console.error('Failed to create exam chat');
            }
        } catch (error) {
            console.error('Error creating exam chat:', error);
        }
    }
    
    currentCourse.value = course ? { id: course.id, name: course.name } : null;
    showCourseRequired.value = !currentChat.value?.course_id;
    // Persist selection
    saveSelectedCourse();
};

// Theme toggle
const { appearance, updateAppearance } = useAppearance();
const prefersDark = () => typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const isDark = computed(() => appearance.value === 'dark');
const cycleTheme = () => {
    // Get the current theme from the DOM to ensure accuracy
    const isCurrentlyDark = document.documentElement.classList.contains('dark');
    const current = appearance.value || 'light';
    
    // Determine next theme based on current state
    let next: 'light' | 'dark';
    if (current === 'light') {
        next = 'dark';
    } else {
        next = 'light';
    }
    
    updateAppearance(next);
};

// File upload methods
const triggerFileUpload = () => {
    // Check if user has access to file uploads before opening file dialog
    if (!hasFeatureAccess('file_uploads')) {
        const planName = currentPlanName.value;
        let title = 'File Upload Limit Reached';
        let message = 'You\'ve reached your file upload limit. Please upgrade to upload more files.';
        
        if (planName === 'Free') {
            title = 'File Uploads Not Available';
            message = 'File uploads are not available on the free plan. Upgrade to Premium, Plus, or Academy to upload files.';
        }
        
        subscriptionPromptData.value = {
            title,
            message,
            showWaitOption: false, // Remove wait option
            usageInfo: {
                feature_name: 'File Uploads',
                usage: 0,
                limit: 0,
                percentage: 100
            }
        };
        showSubscriptionPrompt.value = true;
        return;
    }
    
    fileInput.value?.click();
};

const handleFileSelect = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        
        // File size validation (5MB limit)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            alert('File size too large. Please select a file smaller than 5MB.');
            target.value = '';
            return;
        }
        
        selectedFile.value = file;
        
        // Create image preview if it's an image
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreviewUrl.value = e.target?.result as string;
            };
            reader.readAsDataURL(file);
        } else {
            imagePreviewUrl.value = null;
        }
        
        console.log('File selected:', file.name, file.size, file.type);
        
        // Refresh usage data after file selection (since file upload counts as usage)
        try {
            await fetchUsageData();
        } catch (error) {
            console.error('Failed to refresh usage data after file selection:', error);
        }
    }
};

const removeSelectedFile = () => {
    selectedFile.value = null;
    imagePreviewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

// Extract content from uploaded file
const extractFileContent = async (file: File): Promise<string> => {
    return new Promise(async (resolve, reject) => {
        try {
            // Handle PDF files
            if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                const pdfContent = await extractPDFText(file);
                resolve(pdfContent);
                return;
            }
            
            // Handle text files
            if (file.type.startsWith('text/') || 
                file.name.endsWith('.txt') || 
                file.name.endsWith('.md') ||
                file.name.endsWith('.json') ||
                file.name.endsWith('.csv')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const content = e.target?.result as string;
                    resolve(content);
                };
                reader.onerror = () => reject(new Error('Failed to read file'));
                reader.readAsText(file);
                return;
            }
            
            // Handle images with OCR
            if (file.type.startsWith('image/')) {
                const imageText = await extractImageText(file);
                resolve(imageText || `[Image file: ${file.name} - ${file.type} - No text detected]`);
                return;
            }
            
            // For other file types, try to read as text
            const reader = new FileReader();
            reader.onload = (e) => {
                const content = e.target?.result as string;
                resolve(content);
            };
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsText(file);
            
        } catch (error) {
            reject(error);
        }
    });
};

// Extract text from PDF using pdfjs-dist
const extractPDFText = async (file: File): Promise<string> => {
    try {
        // Dynamically import pdfjs-dist
        const pdfjsLib = await import('pdfjs-dist');
        
        // Set up the worker - use local worker file
        pdfjsLib.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.js';
        
        // Convert file to ArrayBuffer
        const arrayBuffer = await file.arrayBuffer();
        
        // Load the PDF document
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        
        let fullText = '';
        
        // Extract text from each page
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const textContent = await page.getTextContent();
            
            // Combine all text items from the page
            const pageText = textContent.items
                .map((item: any) => item.str)
                .join(' ');
            
            fullText += pageText + '\n\n';
        }
        
        return fullText.trim();
    } catch (error) {
        console.error('Error extracting PDF text:', error);
        throw new Error(`Failed to extract text from PDF: ${error.message}`);
    }
};

// Extract text from images using Tesseract.js OCR
const extractImageText = async (file: File): Promise<string> => {
    try {
        // Dynamically import tesseract.js
        const { createWorker } = await import('tesseract.js');
        
        // Create a worker with progress callback
        const worker = await createWorker('eng', 1, {
            logger: m => {
                if (m.status === 'recognizing text') {
                    console.log(`OCR Progress: ${Math.round(m.progress * 100)}%`);
                }
            }
        });
        
        // Perform OCR on the image
        const { data: { text } } = await worker.recognize(file);
        
        // Terminate the worker
        await worker.terminate();
        
        const extractedText = text.trim();
        
        // If no text was extracted, return a helpful message
        if (!extractedText || extractedText.length < 3) {
            return `[Image file: ${file.name} - No readable text detected in image]`;
        }
        
        return extractedText;
    } catch (error) {
        console.error('Error extracting image text:', error);
        throw new Error(`Failed to extract text from image: ${error.message}`);
    }
};

// Audio recording functions
const handleStartRecording = async () => {
    try {
        await startRecording();
    } catch (error) {
        console.error('Failed to start recording:', error);
    }
};

const handleStopRecording = async () => {
    try {
        isTranscribing.value = true;
        const audioBlob = await stopRecording();
        
        // Send audio to backend for transcription
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.webm');
        
        const response = await fetch('/api/transcribe-audio', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (!response.ok) {
            throw new Error('Transcription failed');
        }
        
        const result = await response.json();
        
        if (result.success && result.text) {
            // Add transcribed text to the input
            currentMessage.value = result.text;
        } else {
            throw new Error(result.error || 'No text transcribed');
        }
        
    } catch (error) {
        console.error('Transcription error:', error);
        alert('Failed to transcribe audio. Please try again.');
    } finally {
        isTranscribing.value = false;
    }
};

// Toggle exam section collapse state
const toggleExamSection = () => {
    isExamSectionCollapsed.value = !isExamSectionCollapsed.value;
    // Save preference to localStorage
    localStorage.setItem('examSectionCollapsed', isExamSectionCollapsed.value.toString());
};

// Toggle sidebar collapse state
const toggleSidebar = () => {
    // Check if we're on mobile (screen width < 1024px)
    if (window.innerWidth < 1024) {
        // On mobile, toggle the mobile sidebar
        showMobileSidebar.value = !showMobileSidebar.value;
    } else {
        // On desktop, toggle the sidebar collapse state
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
        // Save preference to localStorage
        localStorage.setItem('sidebarCollapsed', isSidebarCollapsed.value.toString());
    }
};

// Initialize exam section collapse state from localStorage
// Default to collapsed (true) unless user has previously expanded it
const savedCollapseState = localStorage.getItem('examSectionCollapsed');
if (savedCollapseState === 'false') {
    isExamSectionCollapsed.value = false;
} else {
    // Default to collapsed state
    isExamSectionCollapsed.value = true;
}

// Initialize sidebar collapse state from localStorage
// Default to collapsed (true) unless user has previously expanded it
const savedSidebarState = localStorage.getItem('sidebarCollapsed');
if (savedSidebarState === 'false') {
    isSidebarCollapsed.value = false;
} else {
    // Default to collapsed state
    isSidebarCollapsed.value = true;
}
</script>

<template>
    <Head title="OposChat - Dashboard" />
    
    <div class="flex h-screen h-[100dvh] bg-gray-50 dark:bg-gray-900 overflow-hidden">
        <!-- Left Sidebar - Collapsible -->
        <div class="hidden lg:block">
            <ChatSidebar 
                :current-chat-id="currentChat?.id"
                :is-collapsed="isSidebarCollapsed"
                @chat-selected="handleChatSelected" 
                @new-chat-created="handleNewChatCreated"
                @toggle-collapse="toggleSidebar"
            />
        </div>
        
        <!-- Mobile Sidebar Overlay -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            leave-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div v-if="showMobileSidebar" class="fixed inset-0 z-[70] lg:hidden">
                <div class="absolute inset-0 bg-transparent" @click="showMobileSidebar = false"></div>
                <Transition
                    enter-active-class="transition-transform duration-300"
                    leave-active-class="transition-transform duration-300"
                    enter-from-class="-translate-x-full"
                    leave-to-class="-translate-x-full"
                >
                    <div v-if="showMobileSidebar" class="relative w-80 h-full shadow-2xl">
                        <ChatSidebar 
                            :is-mobile="true"
                            :current-chat-id="currentChat?.id"
                            @chat-selected="handleChatSelected" 
                            @new-chat-created="handleNewChatCreated"
                            @toggle-collapse="toggleSidebar"
                            @close-mobile="showMobileSidebar = false"
                        />
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Chat Header -->
            <div class="bg-gradient-to-r from-white via-gray-50 to-white dark:from-gray-800 dark:via-gray-850 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="px-6 py-3">
                    <div class="flex items-center justify-between">
                        <!-- Left Section -->
                        <div class="flex items-center space-x-6">
                            <!-- Mobile Sidebar Open Button -->
                            <Button 
                                @click="showMobileSidebar = true"
                                variant="ghost" 
                                size="sm" 
                                class="p-2.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-all duration-200 lg:hidden"
                                title="Open Sidebar"
                            >
                                <ChevronsRight class="w-5 h-5" />
                            </Button>
                            <!-- Select Exam Dropdown -->
                            <div class="relative z-50">
                                <CourseSelector 
                                    :chat-id="currentChat ? parseInt(currentChat.id) : undefined"
                                    :initial-course-id="currentChat?.course_id"
                                    @course-selected="handleCourseSelected"
                                    class="transform transition-all duration-200 hover:scale-[1.01]"
                                />
                            </div>
                        </div>
                        
                        <!-- Right Section -->
                        <div class="flex items-center space-x-4">
                            <!-- Enhanced Theme Toggle -->
                            <Button 
                                @click="cycleTheme" 
                                variant="ghost" 
                                size="sm" 
                                class="p-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200"
                                :title="`Theme: ${appearance}`"
                            >
                                <Sun v-if="isDark" class="w-4 h-4" />
                                <Moon v-else class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Chat Messages Area -->
            <div ref="chatContainer" @scroll="handleScroll" class="flex-1 overflow-y-auto overflow-x-hidden bg-gray-50 dark:bg-gray-900 chat-scrollbar">
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
                                Bienvenido a oposchat
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto leading-relaxed">
                                Comienza tu conversación para preparar tu oposición.
                                Nuestro asistente de IA te ayudará a lo largo de tu estudio
                            </p>
                            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                    Aprendizaje impulsado por IA
                                </div>
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                    Preparación de oposiciones
                                </div>
                                <div class="flex items-center">
                                    <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                                    Siempre disponible
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
            <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4 safe-area-inset-bottom chat-input-bar">
                <div class="max-w-4xl mx-auto">
                    <!-- Selected File Display -->
                    <div v-if="selectedFile" class="mb-3 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <!-- Image Preview -->
                        <div v-if="imagePreviewUrl" class="mb-3">
                            <img 
                                :src="imagePreviewUrl" 
                                :alt="selectedFile.name"
                                class="max-w-xs max-h-32 object-contain rounded-lg border border-gray-300 dark:border-gray-600"
                            />
                        </div>
                        
                        <!-- File Info -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <Paperclip class="w-4 h-4 text-gray-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ selectedFile.name }}</span>
                                <span class="text-xs text-gray-500">({{ Math.round(selectedFile.size / 1024) }} KB)</span>
                                <span v-if="isProcessingFile" class="text-xs text-blue-500">Processing...</span>
                            </div>
                            <Button 
                                @click="removeSelectedFile"
                                variant="ghost" 
                                size="sm" 
                                class="text-red-500 hover:text-red-700 p-1"
                                :disabled="isProcessingFile"
                            >
                                ×
                            </Button>
                        </div>
                    </div>
                    
                    

                    <div class="flex items-center space-x-3">
                        <!-- Recording Status Indicator -->
                        <div v-if="isRecording" class="flex items-center space-x-2 text-red-600 dark:text-red-400 text-sm">
                            <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                            <span class="font-medium">Recording...</span>
                        </div>
                        
                        <!-- Attachment Button -->
                        <Button 
                            @click="triggerFileUpload"
                            variant="ghost" 
                            size="sm" 
                            class="p-2 flex-shrink-0 text-gray-500 hover:text-gray-700"
                            :title="hasFeatureAccess('file_uploads') ? 'Attach file' : 'Click to see upgrade options'"
                        >
                            <Paperclip class="w-5 h-5" />
                        </Button>
                        
                        <!-- Hidden File Input -->
                        <input
                            ref="fileInput"
                            type="file"
                            @change="handleFileSelect"
                            class="hidden"
                            accept=".pdf,.doc,.docx,.txt,.md,.json,.csv,.jpg,.jpeg,.png,.gif,.bmp,.tiff,.webp"
                        />
                        
                        <!-- Message Input -->
                        <div class="flex-1 relative">
                            <textarea
                                v-model="currentMessage"
                                @keydown="handleKeyDown"
                                placeholder="Escribe un mensaje..."
                                rows="1"
                                class="w-full resize-none pr-24 py-3 px-4 text-base border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                                style="min-height: 48px; max-height: 120px;"
                                :disabled="isTyping"
                            ></textarea>
                            
                            <!-- Record Button -->
                            <Button
                                v-if="isSupported"
                                @click="isRecording ? handleStopRecording() : handleStartRecording()"
                                :disabled="isTranscribing || isTyping"
                                class="absolute right-12 top-1/2 -translate-y-1/2 p-2 rounded-lg transition-all duration-200"
                                :class="isRecording 
                                    ? 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 animate-pulse' 
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                size="sm"
                                variant="ghost"
                                :title="isRecording ? 'Stop Recording' : 'Start Recording'"
                            >
                                <div class="relative">
                                    <Mic v-if="!isRecording" class="w-4 h-4" />
                                    <Square v-else class="w-4 h-4" />
                                    <!-- Recording indicator dot -->
                                    <div 
                                        v-if="isRecording" 
                                        class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-ping"
                                    ></div>
                                </div>
                            </Button>
                            
                            <!-- Send Button -->
                            <Button
                                @click="async () => { 
                                    try {
                                        await createNewChatIfNeeded(); 
                                        await sendMessage(); 
                                    } catch (error) {
                                        console.error('Failed to send message:', error);
                                    }
                                }"
                                :disabled="!currentMessage.trim() || isTyping || isProcessingFile"
                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-orange-500 hover:bg-orange-600 text-white p-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                size="sm"
                            >
                                <Send class="w-4 h-4" />
                            </Button>
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
            :show-wait-option="subscriptionPromptData.showWaitOption"
            :usage-info="subscriptionPromptData.usageInfo"
            @close="showSubscriptionPrompt = false"
            @upgrade="handleUpgradeClick"
            @wait="handleWaitClick"
        />

        <!-- Subscription Success Modal -->
        <SubscriptionSuccessModal 
            :show="showSuccessModal"
            :subscription="{}"
            @close="showSuccessModal = false"
        />

        <!-- Toast Container for notifications -->
        <ToastContainer />
    </div>
</template>
