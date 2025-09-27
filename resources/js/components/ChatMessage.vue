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

// Process markdown tables
const processMarkdownTables = (content) => {
    console.log('Processing table content:', content);

    // Check if content contains table-like structure
    if (!content.includes('|')) {
        console.log('No pipe characters found, skipping table processing');
        return content;
    }

    // Try to detect and process table format
    const lines = content.split('\n').filter(line => line.trim());
    console.log('Table lines:', lines);

    // Check if this looks like a markdown table
    const hasHeaders = lines.some(line => line.startsWith('|') && line.endsWith('|'));
    const hasSeparator = lines.some(line => line.includes('|') && (line.includes('-') || line.includes('=')));

    console.log('Has headers:', hasHeaders, 'Has separator:', hasSeparator);

    if (hasHeaders && hasSeparator) {
        console.log('Detected markdown table format');
        return processStandardTable(content);
    }

    // Check for single-line table format
    if (content.includes('| Shape |') || content.includes('|Rectangle |')) {
        console.log('Detected single-line table format');
        return processSingleLineTableFormat(content);
    }

    console.log('No table format detected');
    return content;
};

// Process standard markdown table format
const processStandardTable = (content) => {
    console.log('Processing standard table format');

    const lines = content.split('\n').filter(line => line.trim());
    if (lines.length < 2) return content;

    // Find separator line
    let separatorIndex = -1;
    for (let i = 0; i < lines.length; i++) {
        if (lines[i].includes('|') && (lines[i].includes('-') || lines[i].includes('='))) {
            separatorIndex = i;
            break;
        }
    }

    if (separatorIndex === -1) return content;

    // Parse headers (line before separator)
    const headerLine = lines[separatorIndex - 1];
    const headers = headerLine.split('|').slice(1, -1).map(h => h.trim());

    // Parse data rows (lines after separator)
    const dataRows = lines.slice(separatorIndex + 1).map(line => {
        return line.split('|').slice(1, -1).map(cell => cell.trim());
    }).filter(row => row.length > 0 && row.some(cell => cell.length > 0));

    if (headers.length === 0 || dataRows.length === 0) return content;

    // Build HTML table
    let tableHtml = '<div class="overflow-x-auto my-6 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">';
    tableHtml += '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';

    // Headers
    tableHtml += '<thead class="bg-gray-50 dark:bg-gray-800/50"><tr>';
    headers.forEach(header => {
        tableHtml += `<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">${header}</th>`;
    });
    tableHtml += '</tr></thead>';

    // Data rows
    tableHtml += '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';
    dataRows.forEach((row, index) => {
        tableHtml += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-150">';
        row.forEach(cell => {
            tableHtml += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">${cell}</td>`;
        });
        tableHtml += '</tr>';
    });
    tableHtml += '</tbody></table></div>';

    return tableHtml;
};

// Process single-line table format
const processSingleLineTableFormat = (content) => {
    console.log('Processing single-line table format');

    // Split by | and filter out empty parts
    const parts = content.split('|').filter(part => part.trim().length > 0);
    console.log('Parts:', parts);

    // Find separator index
    let separatorIndex = -1;
    for (let i = 0; i < parts.length; i++) {
        if (parts[i].includes('-') || parts[i].includes('=')) {
            separatorIndex = i;
            break;
        }
    }

    console.log('Separator index:', separatorIndex);

    // If we found a separator, skip it
    let startIndex = 0;
    if (separatorIndex !== -1) {
        startIndex = separatorIndex + 4; // Skip separator row
    }

    // Group into rows (4 columns)
    const rows = [];
    for (let i = startIndex; i < parts.length; i += 4) {
        if (i + 3 < parts.length) {
            const row = [
                parts[i].trim(),
                parts[i + 1].trim(),
                parts[i + 2].trim(),
                parts[i + 3].trim()
            ];
            if (row.some(cell => cell.length > 0)) {
                rows.push(row);
            }
        }
    }

    console.log('Rows:', rows);

    if (rows.length < 2) return content;

    // Build HTML table
    let tableHtml = '<div class="overflow-x-auto my-6 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">';
    tableHtml += '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';

    // Headers
    tableHtml += '<thead class="bg-gray-50 dark:bg-gray-800/50"><tr>';
    rows[0].forEach(header => {
        tableHtml += `<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">${header}</th>`;
    });
    tableHtml += '</tr></thead>';

    // Data rows
    tableHtml += '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';
    rows.slice(1).forEach((row, index) => {
        tableHtml += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-150">';
        row.forEach(cell => {
            tableHtml += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">${cell}</td>`;
        });
        tableHtml += '</tr>';
    });
    tableHtml += '</tbody></table></div>';

    return tableHtml;
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
    
    // Process LaTeX/math notation first (before other formatting)
    formattedContent = processMathNotation(formattedContent);

    // Process bold and italic using function replacements
    formattedContent = formattedContent.replace(/\*\*(.*?)\*\*/g, (match, p1) => `<strong>${p1}</strong>`);
    formattedContent = formattedContent.replace(/\*([^*\n]+)\*/g, (match, p1) => `<em>${p1}</em>`);

    // Process tables first (before lists to avoid conflicts)
    formattedContent = processMarkdownTables(formattedContent);

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