<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { User, Bot, Copy, ThumbsUp, ThumbsDown, Square } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

interface Props {
    message: {
        id: string;
        content: string;
        role: 'user' | 'assistant';
        timestamp: string;
        isStreaming?: boolean;
        streamingContent?: string;
        sessionId?: string;
    };
    onStopStreaming?: (sessionId: string) => void;
}

const props = defineProps<Props>();

const isUser = computed(() => props.message.role === 'user');
const isStreaming = computed(() => props.message.isStreaming || false);
const hasStreamingContent = computed(() => props.message.streamingContent && props.message.streamingContent !== props.message.content);

const copyMessage = () => {
    const contentToCopy = hasStreamingContent.value ? props.message.streamingContent! : props.message.content;
    navigator.clipboard.writeText(contentToCopy);
};

const stopStreaming = () => {
    if (props.message.sessionId && props.onStopStreaming) {
        props.onStopStreaming(props.message.sessionId);
    }
};

// Format content with markdown-like formatting
const formatContent = (content) => {
    if (!content) return '';
    
    
    // First, let's clean up any existing $ artifacts from regex replacements
    let formattedContent = content
        .replace(/\$\d+/g, '')                    // Remove $1, $2, etc.
        .replace(/\$+/g, '')                      // Remove multiple $ signs
        .replace(/\*\*V\.\*\*/g, '**V.**')        // Fix V. specifically
        .replace(/V\.\s*Resources:/g, 'V. Resources:')  // Fix V. Resources specifically
        .replace(/([IVX]+)\.\s*([A-Z])/g, '$1. $2');   // Fix Roman numerals spacing
    
    // Process code blocks first to protect them from other formatting
    const codeBlockRegex = /```(\w+)?\n([\s\S]*?)```/g;
    const codeBlocks = [];
    let codeBlockIndex = 0;
    
    formattedContent = formattedContent.replace(codeBlockRegex, (match, language, codeContent) => {
        const placeholder = `__CODE_BLOCK_${codeBlockIndex}__`;
        const languageClass = language ? `language-${language}` : '';
        codeBlocks[codeBlockIndex] = `<pre class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 overflow-x-auto my-4"><code class="${languageClass}">${escapeHtml(codeContent.trim())}</code></pre>`;
        codeBlockIndex++;
        return placeholder;
    });
    
    // Process headers using function replacements to avoid $ issues
    formattedContent = formattedContent.replace(/^### (.*$)/gim, (match, p1) => `<h3 class="text-lg font-semibold mt-4 mb-2">${p1}</h3>`);
    formattedContent = formattedContent.replace(/^## (.*$)/gim, (match, p1) => `<h2 class="text-xl font-semibold mt-4 mb-2">${p1}</h2>`);
    formattedContent = formattedContent.replace(/^# (.*$)/gim, (match, p1) => `<h1 class="text-2xl font-bold mt-4 mb-2">${p1}</h1>`);
    
    // Process bold and italic using function replacements
    formattedContent = formattedContent.replace(/\*\*(.*?)\*\*/g, (match, p1) => `<strong>${p1}</strong>`);
    formattedContent = formattedContent.replace(/\*([^*\n]+)\*/g, (match, p1) => `<em>${p1}</em>`);
    
    // Process lists more carefully
    const lines = formattedContent.split('\n');
    const processedLines = [];
    let inList = false;
    let listType = null; // 'ul' or 'ol'
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        
        // Check for bullet list items
        const bulletMatch = line.match(/^[-*+]\s+(.+)$/);
        // Check for numbered list items  
        const numberedMatch = line.match(/^(\d+)\.\s+(.+)$/);
        
        if (bulletMatch) {
            if (!inList || listType !== 'ul') {
                if (inList) processedLines.push(`</${listType}>`);
                processedLines.push('<ul class="list-disc list-inside my-2 space-y-1">');
                inList = true;
                listType = 'ul';
            }
            const listContent = bulletMatch[1];
            processedLines.push(`<li class="ml-2">${listContent}</li>`);
        } else if (numberedMatch) {
            if (!inList || listType !== 'ol') {
                if (inList) processedLines.push(`</${listType}>`);
                processedLines.push('<ol class="list-decimal list-inside my-2 space-y-1">');
                inList = true;
                listType = 'ol';
            }
            const listContent = numberedMatch[2];
            processedLines.push(`<li class="ml-2" value="${numberedMatch[1]}">${listContent}</li>`);
        } else {
            if (inList) {
                processedLines.push(`</${listType}>`);
                inList = false;
                listType = null;
            }
            processedLines.push(line);
        }
    }
    
    // Close any remaining list
    if (inList) {
        processedLines.push(`</${listType}>`);
    }
    
    formattedContent = processedLines.join('\n');
    
    // Process line breaks more intelligently - only add breaks between paragraphs, not every line
    formattedContent = formattedContent
        .replace(/\n\n+/g, '</p><p class="mb-3">')  // Multiple newlines = paragraph breaks
        .replace(/\n/g, ' ')                        // Single newlines = spaces
        .replace(/^/, '<p class="mb-3">')           // Start with paragraph
        .replace(/$/, '</p>');                      // End with paragraph
    
    // Clean up empty paragraphs
    formattedContent = formattedContent.replace(/<p class="mb-3"><\/p>/g, '');
    
    // Restore code blocks
    for (let i = 0; i < codeBlocks.length; i++) {
        formattedContent = formattedContent.replace(`__CODE_BLOCK_${i}__`, codeBlocks[i]);
    }
    
    
    return formattedContent;
};

// Escape HTML to prevent XSS
const escapeHtml = (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

// Smart auto-scroll: only scroll if user is near the bottom
const shouldAutoScroll = () => {
    const chatContainer = document.querySelector('.chat-scrollbar');
    if (!chatContainer) return false;
    
    const scrollTop = chatContainer.scrollTop;
    const scrollHeight = chatContainer.scrollHeight;
    const clientHeight = chatContainer.clientHeight;
    
    // Only auto-scroll if user is within 100px of the bottom
    return (scrollHeight - scrollTop - clientHeight) < 100;
};

// Auto-scroll to bottom when streaming content changes (only if user is near bottom)
watch(() => props.message.streamingContent, async () => {
    if (isStreaming.value && shouldAutoScroll()) {
        await nextTick();
        const messageElement = document.querySelector(`[data-message-id="${props.message.id}"]`);
        if (messageElement) {
            messageElement.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
    }
}, { immediate: true });
</script>

<template>
    <div class="group flex items-start space-x-4 py-4" :data-message-id="message.id">
        <!-- Avatar -->
        <div class="flex-shrink-0">
            <div v-if="isUser" 
                 class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                <User class="w-4 h-4 text-white" />
            </div>
            <div v-else 
                 class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center">
                <Bot class="w-4 h-4 text-white" />
            </div>
        </div>

        <!-- Message Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-2 mb-1">
                <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ isUser ? 'You' : 'OposChat' }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ message.timestamp }}
                </span>
                <!-- Streaming indicator -->
                <span v-if="isStreaming" class="text-xs text-orange-500 animate-pulse">
                    {{ hasStreamingContent ? 'typing...' : 'thinking...' }}
                </span>
                <!-- Auto-scroll indicator -->
                <span v-if="isStreaming && !shouldAutoScroll()" class="text-xs text-blue-500 ml-2">
                    (scroll to see live updates)
                </span>
            </div>
            
            <div class="prose prose-sm max-w-none text-gray-900 dark:text-white">
                <!-- Streaming content with real-time formatting -->
                <div v-if="isStreaming && hasStreamingContent" 
                     class="streaming-content"
                     v-html="formatContent(message.streamingContent)">
                </div>
                
                <!-- Regular content with formatting -->
                <div v-else class="message-content" v-html="formatContent(message.content)">
                </div>
                
                <!-- Streaming cursor -->
                <span v-if="isStreaming" class="inline-block w-0.5 h-4 bg-orange-500 animate-pulse ml-1"></span>
            </div>

            <!-- Message Actions -->
            <div v-if="!isUser" class="flex items-center space-x-2 mt-3 opacity-0 group-hover:opacity-100 transition-opacity">
                <!-- Stop streaming button -->
                <Button v-if="isStreaming" 
                        @click="stopStreaming" 
                        size="sm" 
                        variant="ghost" 
                        class="h-8 px-2 text-red-500 hover:text-red-700">
                    <Square class="w-3 h-3 mr-1" />
                    Stop
                </Button>
                
                <!-- Copy button -->
                <Button @click="copyMessage" 
                        size="sm" 
                        variant="ghost" 
                        class="h-8 px-2 text-gray-500 hover:text-gray-700">
                    <Copy class="w-3 h-3 mr-1" />
                    Copy
                </Button>
                
                <!-- Feedback buttons (only show when not streaming) -->
                <Button v-if="!isStreaming" 
                        size="sm" 
                        variant="ghost" 
                        class="h-8 px-2 text-gray-500 hover:text-gray-700">
                    <ThumbsUp class="w-3 h-3" />
                </Button>
                <Button v-if="!isStreaming" 
                        size="sm" 
                        variant="ghost" 
                        class="h-8 px-2 text-gray-500 hover:text-gray-700">
                    <ThumbsDown class="w-3 h-3" />
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.streaming-content {
    line-height: 1.6;
}

.streaming-content :deep(pre) {
    margin: 1rem 0;
    padding: 1rem;
    background-color: rgb(243 244 246);
    border-radius: 0.5rem;
    overflow-x: auto;
}

.dark .streaming-content :deep(pre) {
    background-color: rgb(31 41 55);
}

.streaming-content :deep(code) {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
}

.streaming-content :deep(h1) {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.streaming-content :deep(h2) {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.streaming-content :deep(h3) {
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.streaming-content :deep(ul) {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.streaming-content :deep(li) {
    margin: 0.25rem 0;
}

.streaming-content :deep(strong) {
    font-weight: 600;
}

.streaming-content :deep(em) {
    font-style: italic;
}

.message-content {
    line-height: 1.6;
}
</style>