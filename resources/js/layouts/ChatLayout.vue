<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ChatSidebar from '@/components/ChatSidebar.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import SettingsModal from '@/components/SettingsModal.vue';
import SubscriptionPrompt from '@/components/SubscriptionPrompt.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';
import UsageIndicator from '@/components/UsageIndicator.vue';
import CourseSelector from '@/components/CourseSelector.vue';

import { useSubscription } from '@/composables/useSubscription.js';
import chatApi from '@/services/chatApi.js';
import streamingChatService from '@/services/streamingChatService.js';
import { Send, User, Bot, Paperclip, Settings, Menu, Download, BarChart3, Pencil, Sun, Moon, Home } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

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

// Real chat data
const messages = ref<Message[]>([]);
const currentChat = ref<{ id: string; title: string; course_id?: number } | null>(null);
const currentCourse = ref<{ id: number; name: string } | null>(null);
const showCourseRequired = ref(false);
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
        currentChat.value = {
            id: chatData.chat.id,
            title: chatData.chat.title,
            course_id: chatData.chat.course_id
        };
        messages.value = chatData.messages;
        showCourseRequired.value = !currentChat.value.course_id;
    } catch (error) {
        console.error('Failed to load chat:', error);
    } finally {
        isLoading.value = false;
    }
};

// Auto-load chat if provided via prop (from /chat/{id})
if (props.initialChatId) {
    handleChatSelected(props.initialChatId.toString());
}

// Handle new chat creation from sidebar
const handleNewChatCreated = (newChat: any) => {
    // Close mobile sidebar when a new chat is created
    showMobileSidebar.value = false;
    
    // Set the new chat as current
    currentChat.value = {
        id: newChat.id.toString(),
        title: newChat.title || 'New Chat',
        course_id: newChat.course_id
    };
    showCourseRequired.value = !currentChat.value.course_id;
    
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

    // Require an exam selection before chatting
    if (!currentChat.value.course_id) {
        showCourseRequired.value = true;
        return;
    }

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
            fullMessageForAI,
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
                fallbackToRegularChat(fullMessageForAI, assistantMessage.id);
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

// Rename chat
const renameCurrentChat = async () => {
    if (!currentChat.value) return;
    const newTitle = prompt('Rename chat', currentChat.value.title || 'New Chat');
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
const handleCourseSelected = (course: any) => {
    if (currentChat.value) {
        currentChat.value.course_id = course?.id || null;
    }
    currentCourse.value = course ? { id: course.id, name: course.name } : null;
    showCourseRequired.value = !currentChat.value?.course_id;
};

// Theme toggle
const { appearance, updateAppearance } = useAppearance();
const prefersDark = () => typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const isDark = computed(() => appearance.value === 'dark' || (appearance.value === 'system' && prefersDark()));
const cycleTheme = () => {
    const current = appearance.value || 'system';
    const next = current === 'light' ? 'dark' : current === 'dark' ? 'system' : 'light';
    updateAppearance(next);
};

// File upload methods
const triggerFileUpload = () => {
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
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
</script>

<template>
    <Head title="OposChat - Dashboard" />
    
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Left Sidebar - Hidden on mobile, shown on desktop -->
        <div class="hidden lg:block">
            <ChatSidebar 
                :filter-by-course-id="currentChat?.course_id"
                @chat-selected="handleChatSelected" 
                @new-chat-created="handleNewChatCreated" 
            />
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
                            :filter-by-course-id="currentChat?.course_id"
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
            <div class="bg-gradient-to-r from-white via-gray-50 to-white dark:from-gray-800 dark:via-gray-850 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="px-6 py-5">
                    <div class="flex items-center justify-between">
                        <!-- Left Section -->
                        <div class="flex items-center space-x-6">
                            <!-- Mobile Menu Button -->
                            <Button 
                                @click="showMobileSidebar = true"
                                variant="ghost" 
                                size="sm" 
                                class="lg:hidden p-2.5 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition-all duration-200"
                            >
                                <Menu class="w-5 h-5" />
                            </Button>
                            
                            <!-- Chat Title and Course Info -->
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center space-x-2">
                                        <h1 class="text-2xl font-bold bg-gradient-to-r from-gray-900 via-blue-800 to-purple-800 dark:from-white dark:via-blue-200 dark:to-purple-200 bg-clip-text text-transparent">
                                            {{ currentChat?.title || 'OposChat' }}
                                        </h1>
                                        <button v-if="currentChat" @click="renameCurrentChat" class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 group" title="Rename chat">
                                            <Pencil class="w-4 h-4 text-gray-500 group-hover:text-blue-600 dark:text-gray-400 dark:group-hover:text-blue-400 transition-colors" />
                                        </button>
                                    </div>
                                    
                                    <!-- Enhanced Course Badge -->
                                    <div v-if="currentCourse" class="relative">
                                        <div class="inline-flex items-center px-4 py-2.5 rounded-2xl text-sm font-semibold bg-gradient-to-r from-blue-500 via-blue-600 to-purple-600 text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-2.5 h-2.5 bg-white rounded-full animate-pulse"></div>
                                                <span class="font-bold">{{ currentCourse.name }}</span>
                                                <div class="w-1 h-1 bg-white/60 rounded-full"></div>
                                                <span class="text-xs opacity-90">ACTIVE</span>
                                            </div>
                                        </div>
                                        <!-- Subtle glow effect -->
                                        <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-blue-500 via-blue-600 to-purple-600 opacity-20 blur-sm -z-10"></div>
                                    </div>
                                </div>
                                
                                <!-- Course Selector with enhanced styling -->
                                <div v-if="currentChat" class="relative">
                                    <CourseSelector 
                                        :chat-id="parseInt(currentChat.id)"
                                        :initial-course-id="currentChat.course_id"
                                        @course-selected="handleCourseSelected"
                                        class="transform transition-all duration-200 hover:scale-105"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Section -->
                        <div class="flex items-center space-x-4">
                            <!-- Home Button -->
                            <Link 
                                :href="route('home')"
                                class="hidden sm:inline-flex items-center px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-xl hover:from-gray-200 hover:to-gray-300 dark:hover:from-gray-600 dark:hover:to-gray-500 transition-all duration-200 shadow-sm hover:shadow-md transform hover:scale-105"
                                title="Go to Home"
                            >
                                <Home class="w-4 h-4 mr-2" />
                                Home
                            </Link>
                            
                            <!-- Premium Features -->
                            <div v-if="currentChat" class="hidden md:flex items-center space-x-2">
                                <!-- Export Chat Button -->
                                <Button 
                                    @click="exportCurrentChat"
                                    variant="ghost" 
                                    size="sm" 
                                    class="p-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200"
                                    :class="{ 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20': hasPremium }"
                                    title="Export Chat"
                                >
                                    <Download class="w-4 h-4" />
                                </Button>
                                
                                <!-- Analytics Button -->
                                <Button 
                                    @click="viewAnalytics"
                                    variant="ghost" 
                                    size="sm" 
                                    class="p-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200"
                                    :class="{ 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20': hasPremium }"
                                    title="View Analytics"
                                >
                                    <BarChart3 class="w-4 h-4" />
                                </Button>
                            </div>
                            
                            <!-- User Info -->
                            <div class="hidden sm:flex items-center space-x-4">
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ user?.name || 'User' }}
                                    </div>
                                    <div class="text-xs font-medium px-2 py-0.5 rounded-full"
                                         :class="hasPremium ? 'text-yellow-700 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900/30' : 'text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700'">
                                        {{ hasPremium ? 'Premium' : 'Free' }}
                                    </div>
                                </div>
                                
                                <!-- Enhanced User Avatar -->
                                <div class="relative">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-purple-600 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110">
                                        <span class="text-white text-sm font-bold">
                                            {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                        </span>
                                    </div>
                                    <!-- Online indicator -->
                                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></div>
                                </div>
                            </div>
                            
                            <!-- Settings Button -->
                            <Button 
                                @click="showSettingsModal = true"
                                variant="ghost" 
                                size="sm" 
                                class="p-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200"
                                title="Settings"
                            >
                                <Settings class="w-4 h-4" />
                            </Button>

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

            <!-- Enhanced Course Required Banner -->
            <div v-if="showCourseRequired" class="mx-6 mt-4 mb-0 p-4 rounded-2xl bg-gradient-to-r from-yellow-50 via-amber-50 to-yellow-50 border border-yellow-200 text-yellow-800 dark:from-yellow-900/20 dark:via-amber-900/20 dark:to-yellow-900/20 dark:text-yellow-200 dark:border-yellow-800 shadow-sm">
                <div class="flex items-center space-x-3">
                    <div class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center">
                        <span class="text-white text-xs font-bold">!</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold">Exam Selection Required</p>
                        <p class="text-sm opacity-90">Please select an exam in the header above to start your personalized chat experience.</p>
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
                        <!-- Attachment Button -->
                        <Button 
                            @click="triggerFileUpload"
                            variant="ghost" 
                            size="sm" 
                            class="text-gray-500 hover:text-gray-700 p-2 flex-shrink-0"
                            title="Attach file"
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
                                :disabled="!currentMessage.trim() || isTyping || isProcessingFile"
                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-orange-500 hover:bg-orange-600 text-white p-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                size="sm"
                            >
                                <Send class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-center mt-4 space-x-6 text-xs text-gray-500">
                        <Link :href="route('about')" class="hover:text-gray-700 transition-colors">About us</Link>
                        <Link :href="route('legal.privacy')" class="hover:text-gray-700 transition-colors">Privacy policy</Link>
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