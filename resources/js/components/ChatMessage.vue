<script setup lang="ts">
import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { User, Bot, Copy, ThumbsUp, ThumbsDown, Square } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { useToast } from '@/composables/useToast';
import mermaid from 'mermaid';

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

// Toast composable
const { success } = useToast();

// Template ref for the prose content area
const proseRef = ref<HTMLElement | null>(null);

// Use marked for GFM (tables) and DOMPurify to sanitize output
marked.setOptions({ gfm: true, breaks: true });

const isUser = computed(() => props.message.role === 'user');
const isStreaming = computed(() => props.message.isStreaming || false);
const hasStreamingContent = computed(() => props.message.streamingContent && props.message.streamingContent !== props.message.content);

const copyMessage = async () => {
    try {
        if (!proseRef.value) {
            console.error('No content to copy');
            return;
        }

        // Get both HTML and text content from the rendered message
        const htmlContent = proseRef.value.innerHTML || '';
        const textContent = proseRef.value.innerText || proseRef.value.textContent || '';
        
        if (!htmlContent && !textContent) {
            console.error('No content to copy');
            return;
        }

        // Copy with formatting (HTML) and plain text fallback
        // This allows pasting into Word with formatting preserved
        await navigator.clipboard.write([
            new ClipboardItem({
                'text/html': new Blob([htmlContent], { type: 'text/html' }),
                'text/plain': new Blob([textContent], { type: 'text/plain' })
            })
        ]);

        // Show success toast
        success('Copied to clipboard');
    } catch (err) {
        console.error('Failed to copy:', err);
        // Fallback to plain text if ClipboardItem fails
        try {
            const textContent = proseRef.value?.innerText || proseRef.value?.textContent || '';
            await navigator.clipboard.writeText(textContent);
            success('Copied to clipboard');
        } catch (fallbackErr) {
            console.error('Fallback copy also failed:', fallbackErr);
        }
    }
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
// `applyHeuristics` toggles heading/list heuristics (disable while streaming)
// Process list-like content that doesn't have proper markdown syntax
const processListLikeContent = (content: string): string => {
    // Look for patterns like "Topic 1", "Description:", "Examples:" that should be formatted as lists
    const lines = content.split('\n');
    const processedLines = [];
    let inList = false;
    let listItems = [];
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        
        // Check if this looks like a list item (Topic X, Description:, Examples:, etc.)
        const isListItem = /^(Topic \d+|Description:|Examples?:?)$/i.test(line);
        
        if (isListItem) {
            if (!inList) {
                // Start a new list
                inList = true;
                listItems = [];
            }
            listItems.push(line);
        } else {
            // If we were in a list and now we're not, process the accumulated list
            if (inList && listItems.length > 0) {
                processedLines.push('- ' + listItems.join('\n- '));
                inList = false;
                listItems = [];
            }
            processedLines.push(line);
        }
    }
    
    // Handle any remaining list items
    if (inList && listItems.length > 0) {
        processedLines.push('- ' + listItems.join('\n- '));
    }
    
    return processedLines.join('\n');
};

const formatContent = (content: string | undefined, applyHeuristics = true): string => {
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

        // Ensure lists show bullets even if global reset removed them
        sanitized = sanitized.replace(/<ul([^>]*)class=(["'])(.*?)\2([^>]*)>/g, (m, before, quote, cls, after) => {
            // append list-disc and pl-6 ml-4 for proper indentation
            const newCls = (cls + ' list-disc pl-6 ml-4').replace(/\s+/g, ' ').trim();
            return `<ul${before}class=${quote}${newCls}${quote}${after}>`;
        });
        sanitized = sanitized.replace(/<ul(?![^>]*class=)/g, '<ul class="list-disc pl-6 ml-4"');
        sanitized = sanitized.replace(/<ol([^>]*)class=(["'])(.*?)\2([^>]*)>/g, (m, before, quote, cls, after) => {
            const newCls = (cls + ' list-decimal pl-6 ml-4').replace(/\s+/g, ' ').trim();
            return `<ol${before}class=${quote}${newCls}${quote}${after}>`;
        });
        sanitized = sanitized.replace(/<ol(?![^>]*class=)/g, '<ol class="list-decimal pl-6 ml-4"');

        // Add classes to th/td
        sanitized = sanitized.replace(/<th>/g, '<th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800">');
        sanitized = sanitized.replace(/<td>/g, '<td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-900 dark:text-gray-100">');

        // Heuristic: convert paragraph headings ending with ':' into proper headings
        // when followed by a list or standalone paragraph heading.
        if (applyHeuristics) {
            sanitized = sanitized.replace(/<p>\s*([^<]{2,200}?)\s*:\s*<\/p>\s*(?=<(ul|ol)>)/g, '<h2>$1</h2>');
            sanitized = sanitized.replace(/<p>\s*([^<]{2,200}?)\s*:\s*<\/p>/g, '<h2>$1</h2>');

            // Convert an opening list item that is just a heading ("Heading:") into a heading
            sanitized = sanitized.replace(/<(ul|ol)>\s*<li>\s*([^<]{2,200}?)\s*:\s*<\/li>\s*/g, '<h3>$2</h3><$1>');
        }

        return sanitized;
    }

    // Otherwise treat as markdown (supports tables via GFM)
    let text = content;

    // Process LaTeX/math notation first
    text = processMathNotation(text);

    // Process list-like content that doesn't have proper markdown syntax
    text = processListLikeContent(text);

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

    // Ensure lists show bullets (Tailwind reset may remove default markers)
    sanitized = sanitized.replace(/<ul([^>]*)class=(["'])(.*?)\2([^>]*)>/g, (m, before, quote, cls, after) => {
        const newCls = (cls + ' list-disc pl-6 ml-4').replace(/\s+/g, ' ').trim();
        return `<ul${before}class=${quote}${newCls}${quote}${after}>`;
    });
    sanitized = sanitized.replace(/<ul(?![^>]*class=)/g, '<ul class="list-disc pl-6 ml-4"');
    sanitized = sanitized.replace(/<ol([^>]*)class=(["'])(.*?)\2([^>]*)>/g, (m, before, quote, cls, after) => {
        const newCls = (cls + ' list-decimal pl-6 ml-4').replace(/\s+/g, ' ').trim();
        return `<ol${before}class=${quote}${newCls}${quote}${after}>`;
    });
    sanitized = sanitized.replace(/<ol(?![^>]*class=)/g, '<ol class="list-decimal pl-6 ml-4"');

    // Heuristic: convert paragraph headings ending with ':' into proper headings
    if (applyHeuristics) {
        sanitized = sanitized.replace(/<p>\s*([^<]{2,200}?)\s*:\s*<\/p>\s*(?=<(ul|ol)>)/g, '<h2>$1</h2>');
        sanitized = sanitized.replace(/<p>\s*([^<]{2,200}?)\s*:\s*<\/p>/g, '<h2>$1</h2>');
        sanitized = sanitized.replace(/<(ul|ol)>\s*<li>\s*([^<]{2,200}?)\s*:\s*<\/li>\s*/g, '<h3>$2</h3><$1>');

        // Promote lines like "Topic 1" to H2 when followed by a Description label
        sanitized = sanitized.replace(/<p>\s*(Topic\s+\d[\w\s\-:\.]*)\s*<\/p>\s*(?=<p>\s*Description:)/gi, '<h2>$1</h2>');

        // Bold the Description label and keep its content inline
        sanitized = sanitized.replace(/<p>\s*Description:\s*(.*?)\s*<\/p>/gi, '<p><strong>Description:</strong> $1</p>');

        // Convert Examples: + consecutive <p> lines into a list
        const examplesLabel = /<p>\s*Examples:\s*<\/p>/i;
        let exIdx = sanitized.search(examplesLabel);
        while (exIdx !== -1) {
            const labelMatch = sanitized.match(examplesLabel);
            if (!labelMatch) break;
            const startPos = sanitized.indexOf(labelMatch[0], exIdx);
            let cursor = startPos + labelMatch[0].length;
            const itemRe = /^\s*<p>\s*([\s\S]*?)\s*<\/p>/i;
            const items: string[] = [];
            while (true) {
                const rest = sanitized.slice(cursor);
                const m = rest.match(itemRe);
                if (!m) break;
                const text = m[1].trim();
                // stop if next paragraph looks like a labeled section or another heading
                if (/^([A-Z][A-Za-z0-9\s]{0,80}:)$/.test(text) || /^Topic\s+\d+/i.test(text)) break;
                items.push(text);
                cursor += m[0].length;
            }
            if (items.length > 0) {
                const seqRe = new RegExp(labelMatch[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?:\s*<p>\s*[\s\S]*?\s*<\/p>){' + items.length + '}', 'i');
                const ul = '<p><strong>Examples:</strong></p><ul>' + items.map(i => '<li>' + i + '</li>').join('') + '</ul>';
                sanitized = sanitized.replace(seqRe, ul);
        } else {
                break;
            }
            exIdx = sanitized.search(examplesLabel);
        }
    }

    // Process Mermaid diagrams - detect and wrap in proper format
    sanitized = processMermaidDiagrams(sanitized);
    
    return sanitized;
};

// Process and wrap Mermaid diagrams
const processMermaidDiagrams = (html: string): string => {
    // Detect Mermaid syntax patterns (with or without code blocks)
    // Look for graph/flowchart syntax followed by node definitions
    
    let uniqueIdCounter = 0;
    
    // First, check if already in code blocks
    const codeBlockRegex = /<pre><code class="language-mermaid">([\s\S]*?)<\/code><\/pre>/g;
    if (html.match(codeBlockRegex)) {
        // Already in code blocks, just format it properly
        return html.replace(codeBlockRegex, (match, diagramCode) => {
            const uniqueId = `mermaid-${props.message.id}-${Date.now()}-${uniqueIdCounter++}`;
            const trimmedCode = diagramCode.trim();
            
            // If streaming, don't try to render incomplete diagrams
            if (isStreaming.value) {
                return `<div class="mermaid-container">
                    <div class="mermaid-loading flex items-center justify-center my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600">
                        <div class="flex items-center space-x-3">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Generating diagram...</span>
                        </div>
                    </div>
                </div>`;
            }
            
            return `<div class="mermaid-container">
                <div class="mermaid-loading flex items-center justify-center my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600">
                    <div class="flex items-center space-x-3">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Rendering diagram...</span>
                    </div>
                </div>
                <div class="mermaid my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600 overflow-x-auto" id="${uniqueId}" style="display: none;">${trimmedCode}</div>
            </div>`;
        });
    }
    
    // Look for standalone Mermaid syntax (flowchart/graph followed by node definitions)
    const standaloneMermaidRegex = /(flowchart\s+TD|graph\s+TD|flowchart\s+LR|graph\s+LR)[\s\S]*?(?=\n\n|$)/g;
    
    return html.replace(standaloneMermaidRegex, (match) => {
        const uniqueId = `mermaid-${props.message.id}-${Date.now()}-${uniqueIdCounter++}`;
        const cleanCode = match.trim();
        
        // If streaming, don't try to render incomplete diagrams
        if (isStreaming.value) {
            return `<div class="mermaid-container">
                <div class="mermaid-loading flex items-center justify-center my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600">
                    <div class="flex items-center space-x-3">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Generating diagram...</span>
                    </div>
                </div>
            </div>`;
        }
        
        return `<div class="mermaid-container">
            <div class="mermaid-loading flex items-center justify-center my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600">
                <div class="flex items-center space-x-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Rendering diagram...</span>
                </div>
            </div>
            <div class="mermaid my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600 overflow-x-auto" id="${uniqueId}" style="display: none;">${cleanCode}</div>
        </div>`;
    });
};

// Render Mermaid diagrams after content is mounted
const renderMermaidDiagrams = async () => {
    await nextTick();
    
    if (!proseRef.value) return;
    
    // Don't render diagrams while streaming - wait for complete content
    if (isStreaming.value) return;
    
    // Find all .mermaid divs that haven't been rendered yet
    const mermaidDivs = proseRef.value.querySelectorAll<HTMLElement>('.mermaid:not(.mermaid-rendered)');
    
    if (mermaidDivs.length === 0) return;
    
    // Hide all loading indicators and show mermaid divs
    mermaidDivs.forEach(mermaidDiv => {
        const container = mermaidDiv.closest('.mermaid-container') as HTMLElement;
        if (container) {
            const loadingDiv = container.querySelector('.mermaid-loading') as HTMLElement;
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
            mermaidDiv.style.display = 'block';
        }
    });
    
    try {
        // Use mermaid.run() which automatically detects .mermaid divs and renders them
        await mermaid.run();
        
        // Mark as rendered to avoid re-rendering
        mermaidDivs.forEach(div => {
            div.classList.add('mermaid-rendered');
        });
        
        console.log(`✅ Rendered ${mermaidDivs.length} Mermaid diagram(s)`);
    } catch (error) {
        console.error('❌ Error rendering Mermaid diagrams:', error);
        // Show error message to user
        mermaidDivs.forEach(mermaidDiv => {
            const container = mermaidDiv.closest('.mermaid-container') as HTMLElement;
            if (container) {
                const loadingDiv = container.querySelector('.mermaid-loading') as HTMLElement;
                if (loadingDiv) {
                    loadingDiv.innerHTML = '<span class="text-sm text-red-500 dark:text-red-400">Error rendering diagram</span>';
                }
            }
        });
    }
};

// Initialize Mermaid on mount
onMounted(() => {
    mermaid.initialize({ 
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'loose',
        flowchart: {
            useMaxWidth: false,
            htmlLabels: true,
            fontSize: 14,
            curve: 'basis'
        },
        fontSize: 14,
        fontFamily: 'Arial, sans-serif'
    });
    
    // Render diagrams on mount
    renderMermaidDiagrams();
});

// Watch for content changes and re-render diagrams when streaming completes
watch([() => props.message.content, () => props.message.streamingContent], async () => {
    // Wait a bit after streaming to ensure content is complete
    if (isStreaming.value) {
        return;
    }
    await nextTick();
    await renderMermaidDiagrams();
}, { immediate: false });

// Also watch for when streaming state changes from true to false
watch(() => isStreaming.value, async (isCurrentlyStreaming, wasStreaming) => {
    // When streaming completes (was streaming, now not streaming)
    if (!isCurrentlyStreaming && wasStreaming) {
        await nextTick();
        // Small delay to ensure DOM is updated
        setTimeout(() => {
            renderMermaidDiagrams();
        }, 100);
    }
}, { immediate: false });

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
            
            <div ref="proseRef" class="prose prose-sm max-w-none text-gray-900 dark:text-white">
                <!-- Streaming content with real-time formatting -->
                <div v-if="isStreaming && hasStreamingContent" 
                     class="streaming-content"
                 v-html="formatContent(message.streamingContent, false)">
                </div>
                
                <!-- Regular content with formatting -->
                <div v-else class="message-content" v-html="formatContent(message.content)">
                </div>
                
                <!-- Streaming cursor -->
                <span v-if="isStreaming" class="inline-block w-0.5 h-4 bg-orange-500 animate-pulse ml-1"></span>
            </div>
            <!-- Message Actions for Assistant -->
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
            
            <!-- Message Actions for User -->
            <div v-if="isUser" class="flex items-center space-x-2 mt-3 opacity-0 group-hover:opacity-100 transition-opacity">
                <!-- Copy button -->
                <Button @click="copyMessage" 
                        size="sm" 
                        variant="ghost" 
                        class="h-8 px-2 text-gray-500 hover:text-gray-700">
                    <Copy class="w-3 h-3 mr-1" />
                    Copy
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

/* Heading styles for clearer structure in long plans */
.streaming-content :deep(h1), .message-content :deep(h1) {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}
.streaming-content :deep(h2), .message-content :deep(h2) {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 0.9rem;
    margin-bottom: 0.4rem;
}
.streaming-content :deep(h3), .message-content :deep(h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 0.7rem;
    margin-bottom: 0.3rem;
}

/* Ensure table layout is full width */
.streaming-content table,
.message-content table {
    width: 100%;
    table-layout: auto;
}

/* Ensure tables in messages take full width */
.streaming-content table,
.message-content table {
    width: 100%;
    table-layout: auto;
}
</style>