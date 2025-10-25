<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { User, Bot, Copy, ThumbsUp, ThumbsDown, Square } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import DOMPurify from 'dompurify';
import { marked } from 'marked';

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

// Use marked for GFM (tables) and DOMPurify to sanitize output
marked.setOptions({ gfm: true, breaks: true });

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


// Process LaTeX/math notation
const processMathNotation = (content) => {
    // Process display math (block math) - \[ ... \]
    content = content.replace(/\\\[([\s\S]*?)\\\]/g, (match, mathContent) => {
        return `<div class="my-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border-l-4 border-blue-500">
            <div class="text-center font-mono text-lg">
                ${mathContent.trim()}
            </div>
        </div>`;
    });

    // Process inline math - \( ... \)
    content = content.replace(/\\\(([\s\S]*?)\\\)/g, (match, mathContent) => {
        return `<span class="inline-math font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-sm">
            ${mathContent.trim()}
        </span>`;
    });

    // Process simple math expressions without LaTeX delimiters
    // Look for patterns like "π × r²" or "3.14 × 49"
    content = content.replace(/([π])\s*×\s*([a-zA-Z0-9²³⁴⁵⁶⁷⁸⁹]+)/g, (match, symbol, expression) => {
        return `<span class="inline-math font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-sm">
            ${symbol} × ${expression}
        </span>`;
    });

    // Process superscripts (like r², x³)
    content = content.replace(/([a-zA-Z0-9]+)([²³⁴⁵⁶⁷⁸⁹]+)/g, (match, base, superscript) => {
        return `<span class="inline-math">${base}<sup class="text-xs">${superscript}</sup></span>`;
    });

    // Process LaTeX fractions (\frac{numerator}{denominator})
    content = content.replace(/\\frac\{([^}]+)\}\{([^}]+)\}/g, (match, numerator, denominator) => {
        return `<span class="inline-math font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-sm">
            <span class="inline-block align-middle">
                <span class="block text-center border-b border-gray-400">${numerator}</span>
                <span class="block text-center">${denominator}</span>
            </span>
        </span>`;
    });

    // Process simple fractions (like 14/2)
    content = content.replace(/(\d+)\/(\d+)/g, (match, numerator, denominator) => {
        return `<span class="inline-math font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-sm">
            <span class="inline-block align-middle">
                <span class="block text-center border-b border-gray-400">${numerator}</span>
                <span class="block text-center">${denominator}</span>
            </span>
        </span>`;
    });

    // Process LaTeX text commands (\text{...})
    content = content.replace(/\\text\{([^}]+)\}/g, (match, text) => {
        return `<span class="font-normal">${text}</span>`;
    });

    // Process LaTeX times symbol (\times)
    content = content.replace(/\\times/g, '×');

    // Process LaTeX sqrt symbol
    content = content.replace(/\\sqrt\{([^}]+)\}/g, (match, expression) => {
        return `<span class="inline-math">√${expression}</span>`;
    });

    // Process LaTeX pm symbol (±)
    content = content.replace(/\\pm/g, '±');

    return content;
};





// Decode HTML entities
const decodeHtml = (html: string): string => {
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
};

// Format content with markdown-like formatting and sanitize output.
const formatContent = (content: string | undefined): string => {
    if (!content) return '';

    // First, decode any HTML entities so we can detect raw HTML tables
    const decodedContent = decodeHtml(content);

    // If the decoded content contains an HTML table tag, treat it as HTML.
    if (decodedContent.includes('<table')) {
        // Sanitize the decoded HTML, then apply our table styling wrapper.
    let sanitized = String(DOMPurify.sanitize(decodedContent, { USE_PROFILES: { html: true } }) as unknown as string);

        sanitized = sanitized.replace(/<table/g, '<div class="overflow-x-auto my-6">$&');
        sanitized = sanitized.replace(/<\/table>/g, '</table></div>');

        // Ensure table elements take full width: add `w-full` class when missing
        sanitized = sanitized.replace(/<table([^>]*)class=(["'])(.*?)\2([^>]*)>/g, (m, before, quote, cls, after) => {
            return `<table${before}class=${quote}${cls} w-full${quote}${after}>`;
        });
        sanitized = sanitized.replace(/<table(?![^>]*class=)/g, '<table class="w-full"');

        // Add classes to th/td
        sanitized = sanitized.replace(/<th>/g, '<th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800">');
        sanitized = sanitized.replace(/<td>/g, '<td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-900 dark:text-gray-100">');

        return sanitized;
    }

    // Otherwise treat as markdown (supports tables via GFM)
    let text = content;

    // Process LaTeX/math notation first
    text = processMathNotation(text);

    // Render markdown to HTML (marked supports GFM tables)
    const html = marked.parse(text || '', { gfm: true });

    // Sanitize rendered HTML and style tables
    let sanitized = String(DOMPurify.sanitize(html, { USE_PROFILES: { html: true } }) as unknown as string);

    sanitized = sanitized.replace(/<table/g, '<div class="overflow-x-auto my-6">$&');
    sanitized = sanitized.replace(/<\/table>/g, '</table></div>');
    // Ensure table elements take full width: add `w-full` class when missing
    sanitized = sanitized.replace(/<table([^>]*)class=(["'])(.*?)\2([^>]*)>/g, (m, before, quote, cls, after) => {
        return `<table${before}class=${quote}${cls} w-full${quote}${after}>`;
    });
    sanitized = sanitized.replace(/<table(?![^>]*class=)/g, '<table class="w-full"');
    sanitized = sanitized.replace(/<th>/g, '<th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800">');
    sanitized = sanitized.replace(/<td>/g, '<td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-900 dark:text-gray-100">');

    return sanitized;
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

/* Ensure tables in messages take full width */
.streaming-content table,
.message-content table {
    width: 100%;
    table-layout: auto;
}
</style>