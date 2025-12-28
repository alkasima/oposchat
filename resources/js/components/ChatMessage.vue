<script setup lang="ts">
import { computed, ref, watch, nextTick, onMounted } from 'vue';
import { User, Bot, Copy, Square, Maximize2, X } from 'lucide-vue-next';
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
    console.log('Stop button clicked', {
        sessionId: props.message.sessionId,
        hasCallback: !!props.onStopStreaming,
        message: props.message
    });
    
    // Always call the callback if it exists, even if sessionId is missing
    // The callback will handle finding the streaming message
    if (props.onStopStreaming) {
        // Pass sessionId if available, otherwise pass empty string or undefined
        // The stopStreaming function in ChatLayout will find the streaming message
        props.onStopStreaming(props.message.sessionId || '');
    } else {
        console.error('Cannot stop streaming: callback not provided', {
            sessionId: props.message.sessionId,
            hasCallback: !!props.onStopStreaming
        });
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

// Normalize and sanitize Mermaid text: decode entities, strip tags, normalize newlines,
// and drop trailing narrative/explanation lines that aren't valid Mermaid syntax.
const sanitizeMermaidText = (code: string): string => {
    if (!code) return '';
    // Decode common HTML entities first
    let text = code
        .replace(/&gt;/g, '>')
        .replace(/&lt;/g, '<')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'");
    // Strip any residual HTML tags
    text = text.replace(/<[^>]*>/g, '');
    // Normalize CRLF to LF
    text = text.replace(/\r\n?/g, '\n');
    // Keep only contiguous Mermaid-relevant lines starting from header
    const lines = text.split('\n');
    const kept: string[] = [];
    const isHeader = (l: string) => /^(\s*)?(graph|flowchart)\b/i.test(l.trim());
    const isMermaidLine = (l: string) => {
        const t = l.trim();
        if (t === '') return true; // allow blank lines inside block
        // Allow common mermaid directives and edge definitions
        if (/^(subgraph|end|classDef|linkStyle|click|style|direction|%%)/i.test(t)) return true;
        if (t.includes('--') || t.includes('==')) return true; // edges like A --> B or A --- B or A ==> B
        return false;
    };
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        if (i === 0) {
            if (!isHeader(line)) return '';
            kept.push(line);
            continue;
        }
        if (isMermaidLine(line)) {
            kept.push(line);
        } else {
            break; // stop at first narrative/non-mermaid line
        }
    }
    return kept.join('\n').trim();
};

// Sanitize Mermaid code: convert invalid characters to valid Mermaid syntax
const sanitizeMermaidCode = (code: string): string => {
    if (!code) return '';
    
    // Convert invalid arrow characters to valid Mermaid arrows
    let sanitized = code
        .replace(/→/g, '-->')  // Right arrow → to -->
        .replace(/←/g, '<--')  // Left arrow ← to <--
        .replace(/↔/g, '<-->') // Bidirectional arrow ↔ to <-->
        .replace(/⇒/g, '==>')  // Double right arrow ⇒ to ==>
        .replace(/⇐/g, '<==')  // Double left arrow ⇐ to <==
        .replace(/⇔/g, '<==>') // Double bidirectional arrow ⇔ to <==>
        // Fix common arrow pattern issues: " -- No -→ " should be " -- No --> "
        .replace(/--\s*([^-]+?)\s*-→/g, '-- $1 -->')
        .replace(/--\s*([^-]+?)\s*-/g, '-- $1 -->'); // Fix incomplete arrows
    
    // Fix malformed arrow sequences - this is the key fix for -->->->-> patterns
    // Match: --> followed by one or more occurrences of -> or -->
    // Replace with just a single -->
    sanitized = sanitized.replace(/-->(?:->|-->)+/g, '-->');
    
    // Fix patterns where we have multiple consecutive dashes followed by arrows
    // e.g., ---> or -----> should become -->
    sanitized = sanitized.replace(/-{3,}>/g, '-->');
    
    // Fix edge cases where arrows are concatenated incorrectly
    // Pattern: -->-> or -->--> or any combination
    sanitized = sanitized.replace(/(-->)(->)+/g, '$1');
    sanitized = sanitized.replace(/(-->)(-->)+/g, '$1');
    
    // Fix patterns like A -->->->-> B to A --> B (with node labels)
    // This handles cases where malformed arrows appear between nodes
    sanitized = sanitized.replace(/([A-Za-z0-9_]+)\s*-->(?:->)+(?:\s*->)*\s*([A-Za-z0-9_\[\(\)\{\|])/g, '$1 --> $2');
    
    // Normalize spacing around arrows (but preserve labels on edges)
    // Only normalize if there's no label (no | characters nearby)
    sanitized = sanitized.replace(/([A-Za-z0-9_\]\)\}])\s*-->\s*([A-Za-z0-9_\[\(\)\{])/g, '$1 --> $2');
    sanitized = sanitized.replace(/([A-Za-z0-9_\]\)\}])\s*---\s*([A-Za-z0-9_\[\(\)\{])/g, '$1 --- $2');
    sanitized = sanitized.replace(/([A-Za-z0-9_\]\)\}])\s*==>\s*([A-Za-z0-9_\[\(\)\{])/g, '$1 ==> $2');
    
    return sanitized;
};

// Parse a raw Mermaid block into { mermaid, explanation }
const parseMermaidBlock = (code: string): { mermaid: string; explanation: string } => {
    if (!code) return { mermaid: '', explanation: '' };
    // Decode entities and strip tags, normalize newlines
    let text = code
        .replace(/&gt;/g, '>')
        .replace(/&lt;/g, '<')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/<[^>]*>/g, '')
        .replace(/\r\n?/g, '\n');
    
    // Sanitize the code first to fix invalid characters
    text = sanitizeMermaidCode(text);
    
    const lines = text.split('\n');
    
    // Check for all Mermaid diagram types
    const isHeader = (l: string) => {
        const trimmed = l.trim();
        return /^(\s*)?(graph|flowchart|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)\b/i.test(trimmed);
    };
    
    // More permissive check for Mermaid syntax lines
    const isMermaidLine = (l: string) => {
        const t = l.trim();
        if (t === '') return true; // Allow blank lines
        
        // Mermaid directives and keywords
        if (/^(subgraph|end|classDef|linkStyle|click|style|direction|%%)/i.test(t)) return true;
        
        // Edge definitions (arrows) - check for various arrow patterns
        if (t.includes('-->') || t.includes('---') || t.includes('==>') || t.includes('===') || 
            t.includes('-.->') || t.includes('-.') || t.includes('==') || t.includes('--') ||
            t.includes('<--') || t.includes('<==') || t.includes('<-->') || t.includes('<==>')) return true;
        
        // Node definitions: A[Label], A((Label)), A{Label}, A[Label]|Label|, etc.
        // More permissive: allow any characters inside brackets/parentheses/braces
        if (/^[A-Za-z0-9_]+[\[\(\)\{\|][\s\S]*[\]\)\}\|]/.test(t)) return true;
        
        // Node definitions with quotes: A["Label"], A(("Label"))
        if (/^[A-Za-z0-9_]+[\[\(].*["'].*["'].*[\]\)]/.test(t)) return true;
        
        // Simple node references: A, B, etc. (might be part of a diagram)
        if (/^[A-Za-z0-9_]+$/.test(t) && t.length <= 50) return true;
        
        // Labels on edges: A -->|label| B
        if (/\|.*\|/.test(t) && (t.includes('-->') || t.includes('---') || t.includes('--'))) return true;
        
        // Edge with label: A -- label --> B or A -- label --- B
        if (/--\s+[^-]+?\s*(?:-->|---|==>|===)/.test(t)) return true;
        
        return false;
    };
    
    // Find header
    let start = -1;
    for (let i = 0; i < lines.length; i++) {
        if (isHeader(lines[i])) { 
            start = i; 
            break; 
        }
    }
    
    if (start === -1) {
        // If no header found, check if the whole text looks like a Mermaid diagram
        // (might be a diagram without explicit header, or header was stripped)
        const firstLine = lines[0]?.trim() || '';
        if (firstLine.includes('-->') || firstLine.includes('---') || 
            /^[A-Za-z0-9_]+[\[\(\)\{\|]/.test(firstLine)) {
            // Looks like Mermaid syntax, treat entire block as diagram
            return { mermaid: text.trim(), explanation: '' };
        }
        return { mermaid: '', explanation: text.trim() };
    }
    
    let end = start + 1;
    // Keep reading lines until we hit non-Mermaid content
    // Use a more intelligent approach: track bracket/brace/paren balance across all lines
    let bracketBalance = 0;
    let parenBalance = 0;
    let braceBalance = 0;
    let inNodeDefinition = false;
    
    while (end < lines.length) {
        const line = lines[end];
        const trimmed = line.trim();
        
        // Always allow blank lines
        if (trimmed === '') {
            end++;
            continue;
        }
        
        // Update balance counters for the entire diagram so far
        const allLinesSoFar = lines.slice(start, end + 1).join('\n');
        bracketBalance = (allLinesSoFar.match(/\[/g) || []).length - (allLinesSoFar.match(/\]/g) || []).length;
        parenBalance = (allLinesSoFar.match(/\(/g) || []).length - (allLinesSoFar.match(/\)/g) || []).length;
        braceBalance = (allLinesSoFar.match(/\{/g) || []).length - (allLinesSoFar.match(/\}/g) || []).length;
        inNodeDefinition = bracketBalance > 0 || parenBalance > 0 || braceBalance > 0;
        
        // If we're in the middle of a node definition (unclosed brackets/braces/parens), continue
        if (inNodeDefinition) {
            end++;
            continue;
        }
        
        // Check if it's a valid Mermaid line
        if (isMermaidLine(line)) {
            end++;
            continue;
        }
        
        // Check if this line could be continuation of previous line
        if (end > start) {
            const prevLine = lines[end - 1].trim();
            
            // If previous line ends with -- or -->, this might be a continuation
            if (/--(?:>)?\s*$/.test(prevLine)) {
                end++;
                continue;
            }
            
            // If previous line looks like it's starting a node or edge, continue
            if (/^[A-Za-z0-9_]+[\[\(\)\{\|]/.test(prevLine) && !prevLine.match(/[\]\)\}\|]\s*$/) && !prevLine.includes('-->')) {
                // Previous line started a node but might not have closed it yet
                end++;
                continue;
            }
            
            // If line contains characters that could be part of a label (numbers, Spanish chars, etc.)
            // and previous line was part of diagram, continue
            if (/^[\d\s\/¿¡áéíóúñÁÉÍÓÚÑ.,;:!?\-]+$/.test(trimmed) && end > start + 1) {
                // This looks like it could be part of a label (e.g., "2/3 en ambas Cámaras?")
                end++;
                continue;
            }
        }
        
        // If we get here, this line doesn't look like part of the diagram
        // But check if it's just a short continuation (like a number or short text)
        // that might be part of a label
        if (trimmed.length < 20 && /^[\d\s\/¿¡áéíóúñÁÉÍÓÚÑ.,;:!?\-]+$/.test(trimmed)) {
            // Short line with only label-like characters, might be continuation
            end++;
            continue;
        }
        
        // Check if next few lines might complete the diagram
        // (sometimes diagrams have explanation text after, but we want to capture the full diagram)
        let lookAhead = end + 1;
        let foundMoreDiagram = false;
        while (lookAhead < Math.min(end + 5, lines.length)) {
            if (isMermaidLine(lines[lookAhead])) {
                foundMoreDiagram = true;
                break;
            }
            lookAhead++;
        }
        
        if (foundMoreDiagram) {
            // There's more diagram content ahead, continue reading
            end++;
            continue;
        }
        
        // Otherwise, stop here
        break;
    }
    
    const mermaid = lines.slice(start, end).join('\n').trim();
    const explanation = lines.slice(end).join('\n').trim();
    return { mermaid, explanation };
};

// Process and wrap Mermaid diagrams
const processMermaidDiagrams = (html: string): string => {
    // Detect Mermaid syntax patterns (with or without code blocks)
    // Look for graph/flowchart syntax followed by node definitions
    
    let uniqueIdCounter = 0;
    
    // Helper function to create mermaid container HTML
    const createMermaidContainer = (uniqueId: string, mermaidCode: string, explanation: string = '') => {
        // Final sanitization pass to ensure code is clean
        let finalCode = sanitizeMermaidCode(mermaidCode);
        
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
        
        // Prepare explanation paragraphs (plain text, no extra styling)
        const escapedExplanation = (explanation || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        const explanationHtml = escapedExplanation
            .trim()
            .split(/\n{2,}/)
            .map(p => `<p>${p.replace(/\n+/g, ' ')}</p>`) 
            .join('');

        return `<div class="mermaid-container relative"> 
            <button class="mermaid-fullscreen-btn absolute top-2 right-2 z-10 p-2 bg-white dark:bg-gray-800 rounded-md shadow-md hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 opacity-70 hover:opacity-100 transition-opacity" data-mermaid-id="${uniqueId}" title="Enlarge diagram">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                </svg>
            </button>
            <div class="mermaid-loading flex items-center justify-center my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600">
                <div class="flex items-center space-x-3">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Rendering diagram...</span>
                </div>
            </div>
            <div class="mermaid my-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600 overflow-x-auto" id="${uniqueId}" style="display: none;">${finalCode}</div>
            ${explanationHtml ? `${explanationHtml}` : ''}
        </div>`;
    };
    
    // First, check if already in code blocks (with language-mermaid class)
    const codeBlockRegex = /<pre><code(?:\s+class=["']language-mermaid["'])?>([\s\S]*?)<\/code><\/pre>/g;
    let processedHtml = html.replace(codeBlockRegex, (match, diagramCode) => {
        // Check if this code block contains Mermaid syntax
        const trimmedCode = diagramCode.trim();
        if (!/^(flowchart|graph|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)/i.test(trimmedCode)) {
            return match; // Not a Mermaid diagram, return original
        }
        
        const uniqueId = `mermaid-${props.message.id}-${Date.now()}-${uniqueIdCounter++}`;
        const { mermaid: trimmedMermaidCode, explanation } = parseMermaidBlock(trimmedCode);
        
        return createMermaidContainer(uniqueId, trimmedMermaidCode, explanation);
    });
    
    // Also check for code blocks without class but containing Mermaid syntax
    const plainCodeBlockRegex = /<pre><code>([\s\S]*?)<\/code><\/pre>/g;
    processedHtml = processedHtml.replace(plainCodeBlockRegex, (match, diagramCode) => {
        // Skip if already processed (inside mermaid-container)
        if (match.includes('mermaid-container')) {
            return match;
        }
        
        const trimmedCode = diagramCode.trim();
        // Check if this looks like a Mermaid diagram
        if (!/^(flowchart|graph|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)/i.test(trimmedCode)) {
            return match; // Not a Mermaid diagram, return original
        }
        
        const uniqueId = `mermaid-${props.message.id}-${Date.now()}-${uniqueIdCounter++}`;
        const { mermaid: trimmedMermaidCode, explanation } = parseMermaidBlock(trimmedCode);
        
        return createMermaidContainer(uniqueId, trimmedMermaidCode, explanation);
    });
    
    // Also check for diagrams inside paragraph tags (common when markdown wraps content)
    const paragraphMermaidRegex = /<p>([\s\S]*?(?:flowchart|graph|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)[\s\S]*?)<\/p>/g;
    processedHtml = processedHtml.replace(paragraphMermaidRegex, (match, content) => {
        // Skip if already processed
        if (match.includes('mermaid-container')) {
            return match;
        }
        
        // Decode HTML entities in the content
        const decodedContent = content
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&amp;/g, '&')
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'");
        
        // Check if this looks like a Mermaid diagram
        const trimmed = decodedContent.trim();
        if (!/^(flowchart|graph|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)/i.test(trimmed) &&
            !/(?:flowchart|graph|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)/i.test(trimmed)) {
            return match; // Not a Mermaid diagram, return original
        }
        
        const uniqueId = `mermaid-${props.message.id}-${Date.now()}-${uniqueIdCounter++}`;
        const { mermaid: trimmedMermaidCode, explanation } = parseMermaidBlock(trimmed);
        
        // Only replace if we found valid Mermaid code
        if (!trimmedMermaidCode || trimmedMermaidCode.trim().length === 0) {
            return match;
        }
        
        return createMermaidContainer(uniqueId, trimmedMermaidCode, explanation);
    });
    
    // Look for standalone Mermaid syntax (flowchart/graph followed by node definitions)
    // Updated regex to be more flexible - doesn't require direction indicator
    // Matches: flowchart, graph, flowchart TD, graph LR, etc.
    const standaloneMermaidRegex = /(<p>|<br>|<br\s*\/>|\n)?\s*(flowchart(?:\s+(?:TD|LR|TB|BT|RL))?\s*;?|graph(?:\s+(?:TD|LR|TB|BT|RL))?\s*;?|sequenceDiagram|classDiagram|stateDiagram|erDiagram|gantt|pie|gitgraph|journey|requirement)([\s\S]*?)(?=(?:\n\n)|(?:<p[^>]*>)|(?:<\/p>)|<\/div>|<\/code>|<\/pre>|$)/g;
    
    return processedHtml.replace(standaloneMermaidRegex, (fullMatch, before, graphDecl, diagramContent, offset) => {
        // Check if this match is already inside a mermaid-container (to avoid double-processing)
        const beforeMatch = processedHtml.substring(0, offset);
        const lastContainer = beforeMatch.lastIndexOf('mermaid-container');
        const lastClosingDiv = beforeMatch.lastIndexOf('</div>', lastContainer);
        const isAlreadyProcessed = lastContainer !== -1 && (lastClosingDiv === -1 || lastClosingDiv < lastContainer);
        
        if (isAlreadyProcessed) {
            return fullMatch; // Return original, don't process again
        }
        
        // Also check if we're inside a code block that was already processed
        if (beforeMatch.includes('mermaid-container') && !beforeMatch.substring(beforeMatch.lastIndexOf('mermaid-container')).includes('</div>')) {
            return fullMatch;
        }
        
        const uniqueId = `mermaid-${props.message.id}-${Date.now()}-${uniqueIdCounter++}`;
        // Combine the graph declaration with content and parse
        const parsed = parseMermaidBlock((graphDecl + diagramContent).trim());
        const cleanCode = parsed.mermaid;
        const explanation = parsed.explanation;
        
        // Only process if we actually found valid Mermaid code
        if (!cleanCode || cleanCode.trim().length === 0) {
            return fullMatch;
        }
        
        return createMermaidContainer(uniqueId, cleanCode, explanation);
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
    
    // DEBUG: Print all mermaid diagram codes for debugging
    console.log('🔍 DEBUG: Found', mermaidDivs.length, 'Mermaid diagram(s) to render');
    mermaidDivs.forEach((div, index) => {
        const code = div.textContent || '';
        console.log(`📊 Diagram ${index + 1} code:`);
        console.log('─'.repeat(60));
        console.log(code);
        console.log('─'.repeat(60));
        
        // Check for common syntax issues
        const issues = [];
        if (code.includes('á') || code.includes('é') || code.includes('í') || code.includes('ó') || code.includes('ú') || code.includes('ñ')) {
            issues.push('⚠️ Contains Spanish accents (á,é,í,ó,ú,ñ)');
        }
        if (code.includes('(') || code.includes(')')) {
            issues.push('⚠️ Contains parentheses ()');
        }
        if (code.includes(':') && !code.includes('://')) {
            issues.push('⚠️ Contains colons :');
        }
        if (code.includes(',')) {
            issues.push('⚠️ Contains commas ,');
        }
        if (code.split('\n').length > 15) {
            issues.push('⚠️ More than 15 lines (likely too complex)');
        }
        
        if (issues.length > 0) {
            console.log('🚨 POTENTIAL ISSUES DETECTED:');
            issues.forEach(issue => console.log('  ' + issue));
        } else {
            console.log('✅ No obvious syntax issues detected');
        }
    });
    
    // Emit event to notify ChatLayout that Mermaid is rendering
    window.dispatchEvent(new CustomEvent('mermaid-rendering-start'));
    
    // Get the chat container to preserve scroll position
    const chatContainer = document.querySelector('.chat-scrollbar') as HTMLElement;
    let scrollTop = 0;
    let scrollHeight = 0;
    let isNearBottom = false;
    
    if (chatContainer) {
        scrollTop = chatContainer.scrollTop;
        scrollHeight = chatContainer.scrollHeight;
        const clientHeight = chatContainer.clientHeight;
        // Check if user is near bottom (within 200px)
        isNearBottom = (scrollHeight - scrollTop - clientHeight) < 200;
    }
    
    // Hide all loading indicators and show mermaid divs
    // First, sanitize and update the code in each mermaid div
    mermaidDivs.forEach(mermaidDiv => {
        const originalCode = mermaidDiv.textContent || '';
        // Sanitize the code to fix any invalid characters
        const sanitizedCode = sanitizeMermaidCode(originalCode);
        // Update the div content with sanitized code
        if (sanitizedCode !== originalCode) {
            mermaidDiv.textContent = sanitizedCode;
            console.log('🔧 Sanitized Mermaid code (removed invalid characters)');
        }
        
        const container = mermaidDiv.closest('.mermaid-container') as HTMLElement;
        if (container) {
            const loadingDiv = container.querySelector('.mermaid-loading') as HTMLElement;
            if (loadingDiv) {
                // Set minimum height based on loading div before hiding it
                const minHeight = loadingDiv.offsetHeight || 200;
                container.style.minHeight = `${minHeight}px`;
                loadingDiv.style.display = 'none';
            }
            mermaidDiv.style.display = 'block';
            // Set initial min-height for mermaid div
            mermaidDiv.style.minHeight = '200px';
        }
    });
    
    // Preserve scroll position during rendering
    const restoreScroll = () => {
        if (chatContainer) {
            if (isNearBottom) {
                // If near bottom, scroll to bottom after render
                requestAnimationFrame(() => {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                });
            } else {
                // Otherwise, maintain relative scroll position
                const newScrollHeight = chatContainer.scrollHeight;
                const heightDiff = newScrollHeight - scrollHeight;
                if (heightDiff > 0) {
                    chatContainer.scrollTop = scrollTop + heightDiff;
                }
            }
        }
    };
    
    try {
        // Use mermaid.run() which automatically detects .mermaid divs and renders them
        await mermaid.run();
        
        // Wait for next frame to ensure rendering is complete
        await new Promise(resolve => requestAnimationFrame(resolve));
        
        // Remove min-height constraints now that rendering is complete
        mermaidDivs.forEach(mermaidDiv => {
            const container = mermaidDiv.closest('.mermaid-container') as HTMLElement;
            if (container) {
                container.style.minHeight = '';
                mermaidDiv.style.minHeight = '';
            }
        });
        
        // Restore scroll position
        restoreScroll();
        
        // Mark as rendered to avoid re-rendering
        mermaidDivs.forEach(div => {
            div.classList.add('mermaid-rendered');
        });
        
        // Setup fullscreen buttons after rendering
        await nextTick();
        setupFullscreenButtons();
        
        // Emit event to notify ChatLayout that Mermaid rendering is complete
        window.dispatchEvent(new CustomEvent('mermaid-rendering-complete'));
        
        console.log(`✅ Rendered ${mermaidDivs.length} Mermaid diagram(s)`);
    } catch (error) {
        console.error('❌ Error rendering Mermaid diagrams:', error);
        
        // Show detailed error message to user with the problematic code
        mermaidDivs.forEach(mermaidDiv => {
            const container = mermaidDiv.closest('.mermaid-container') as HTMLElement;
            if (container) {
                container.style.minHeight = '';
                mermaidDiv.style.minHeight = '';
                const loadingDiv = container.querySelector('.mermaid-loading') as HTMLElement;
                
                // Get the diagram code that failed
                const diagramCode = mermaidDiv.textContent || '';
                const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                
                if (loadingDiv) {
                    loadingDiv.innerHTML = `
                        <div class="text-sm space-y-2">
                            <div class="text-red-500 dark:text-red-400 font-semibold">
                                ⚠️ Diagram Syntax Error
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                The AI generated an invalid diagram. Error: ${errorMessage}
                            </div>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-gray-500 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                    View diagram code
                                </summary>
                                <pre class="mt-2 p-2 bg-gray-100 dark:bg-gray-900 rounded text-xs overflow-x-auto">${diagramCode}</pre>
                            </details>
                        </div>
                    `;
                    loadingDiv.style.display = 'block';
                }
            }
        });
        restoreScroll();
        
        // Emit event even on error
        window.dispatchEvent(new CustomEvent('mermaid-rendering-complete'));
    }
};

// Handle fullscreen for Mermaid diagrams
const openMermaidFullscreen = (mermaidId: string) => {
    const mermaidDiv = document.getElementById(mermaidId);
    if (!mermaidDiv) return;
    
    // Clean up any existing overlay first
    cleanupExistingOverlay();
    
    // Create fullscreen overlay
    const overlay = document.createElement('div');
    overlay.className = 'mermaid-fullscreen-overlay fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center p-8';
    overlay.dataset.mermaidOverlay = 'true'; // Mark as our overlay
    
    // Create the close button first so we can attach a direct handler
    const closeButton = document.createElement('button');
    closeButton.className = 'mermaid-close-btn absolute top-4 right-4 z-[100] p-3 bg-white dark:bg-gray-800 rounded-md shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 cursor-pointer';
    closeButton.title = 'Close';
    closeButton.type = 'button';
    closeButton.style.pointerEvents = 'auto !important';
    closeButton.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="pointer-events: none;">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    `;
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'mermaid-fullscreen-content overflow-auto p-8 bg-white dark:bg-gray-900 rounded-lg';
    contentDiv.style.maxWidth = '95vw';
    contentDiv.style.maxHeight = '95vh';
    
    const innerDiv = document.createElement('div');
    innerDiv.className = 'mermaid-fullscreen-inner';
    
    const containerDiv = document.createElement('div');
    containerDiv.className = 'relative w-full h-full flex items-center justify-center';
    
    // Assemble the overlay
    containerDiv.appendChild(closeButton);
    contentDiv.appendChild(innerDiv);
    containerDiv.appendChild(contentDiv);
    overlay.appendChild(containerDiv);
    
    // Clone the mermaid SVG content
    const mermaidSvg = mermaidDiv.querySelector('svg');
    if (mermaidSvg) {
        const clone = mermaidSvg.cloneNode(true) as SVGElement;
        
        // Preserve original dimensions and viewBox
        const originalWidth = mermaidSvg.getAttribute('width');
        const originalHeight = mermaidSvg.getAttribute('height');
        const viewBox = mermaidSvg.getAttribute('viewBox');
        const computedStyle = window.getComputedStyle(mermaidSvg);
        
        // Always preserve viewBox if it exists
        if (viewBox) {
            clone.setAttribute('viewBox', viewBox);
        }
        
        // Set dimensions - make responsive but preserve aspect ratio
        clone.style.width = '100%';
        clone.style.height = 'auto';
        clone.style.maxWidth = '95vw';
        clone.style.maxHeight = '95vh';
        
        // If we have explicit width/height, use them as fallback
        if (originalWidth && !viewBox) {
            clone.setAttribute('width', originalWidth);
        }
        if (originalHeight && !viewBox) {
            clone.setAttribute('height', originalHeight);
        }
        
        // Ensure SVG is visible and properly displayed
        clone.style.display = 'block';
        clone.style.visibility = 'visible';
        clone.style.opacity = '1';
        
        // Function to make all arrows/edges adapt to theme - mobile light mode fix
        const makeArrowsAdaptive = (svgElement: SVGElement) => {
            // Determine if we're in dark mode
            const isDarkMode = document.documentElement.classList.contains('dark') || 
                              window.matchMedia('(prefers-color-scheme: dark)').matches;
            const arrowColor = isDarkMode ? '#ffffff' : '#000000';
            
            // Get all paths, lines, polylines, and polygons that have a stroke
            const allShapes = svgElement.querySelectorAll('path, line, polyline, polygon, g');
            
            allShapes.forEach((el) => {
                const svgEl = el as SVGElement;
                // Skip text elements
                if (svgEl.tagName === 'text' || svgEl.tagName === 'tspan') {
                    return;
                }
                
                // Skip if inside a text element
                if (svgEl.closest('text')) {
                    return;
                }
                
                const stroke = svgEl.getAttribute('stroke');
                const tagName = svgEl.tagName.toLowerCase();
                
                // For paths, lines, polylines, polygons - make stroke adaptive if it exists
                if ((tagName === 'path' || tagName === 'line' || tagName === 'polyline' || tagName === 'polygon') && stroke && stroke !== 'none') {
                    svgEl.setAttribute('stroke', arrowColor);
                    (svgEl as any).style.stroke = arrowColor;
                }
                
                // For groups, check children
                if (tagName === 'g') {
                    const children = svgEl.querySelectorAll('path, line, polyline, polygon');
                    children.forEach((child) => {
                        const childSvgEl = child as SVGElement;
                        const childStroke = childSvgEl.getAttribute('stroke');
                        if (childStroke && childStroke !== 'none') {
                            childSvgEl.setAttribute('stroke', arrowColor);
                            (svgEl as any).style.stroke = arrowColor;
                        }
                    });
                }
            });
            
            // Also force all paths and lines with stroke to be adaptive (catch-all)
            const allPathsAndLines = svgElement.querySelectorAll('path[stroke], line[stroke], polyline[stroke], polygon[stroke]');
            allPathsAndLines.forEach((el) => {
                const svgEl = el as SVGElement;
                if (svgEl.closest('text')) return; // Skip if inside text
                const stroke = svgEl.getAttribute('stroke');
                if (stroke && stroke !== 'none') {
                    svgEl.setAttribute('stroke', arrowColor);
                    (svgEl as any).style.stroke = arrowColor;
                }
            });
        };
        
        // Apply adaptive arrows before appending
        makeArrowsAdaptive(clone);
        
        const innerDiv = overlay.querySelector('.mermaid-fullscreen-inner');
        if (innerDiv) {
            // Clear any existing content
            innerDiv.innerHTML = '';
            
            // Append the cloned SVG
            innerDiv.appendChild(clone);
            
            // Ensure the SVG has dimensions
            requestAnimationFrame(() => {
                // Check if SVG has content
                if (!clone.querySelector('path, line, polyline, polygon, circle, rect, ellipse')) {
                    console.warn('Mermaid SVG clone appears to be empty');
                }
                
                // Make arrows adaptive
                makeArrowsAdaptive(clone);
                
                // Also apply after a short delay to catch any elements that might be styled later
                setTimeout(() => {
                    makeArrowsAdaptive(clone);
                    // Force all stroke attributes one more time
                    const allStrokedElements = clone.querySelectorAll('path, line, polyline, polygon');
                    allStrokedElements.forEach((el: Element) => {
                        const svgEl = el as SVGElement;
                        if (svgEl.closest('text')) return;
                        const computedStyle = window.getComputedStyle(svgEl);
                        const strokeValue = svgEl.getAttribute('stroke') || computedStyle.stroke;
                        if (strokeValue && strokeValue !== 'none' && strokeValue !== 'transparent') {
                            const isDarkMode = document.documentElement.classList.contains('dark') || 
                                              window.matchMedia('(prefers-color-scheme: dark)').matches;
                            const arrowColor = isDarkMode ? '#ffffff' : '#000000';
                            svgEl.setAttribute('stroke', arrowColor);
                            svgEl.style.setProperty('stroke', arrowColor, 'important');
                        }
                    });
                }, 100);
            });
        } else {
            console.error('Could not find .mermaid-fullscreen-inner element');
        }
    } else {
        console.error('Could not find SVG element in mermaid div');
    }
    
    // Add comprehensive CSS to force adaptive arrows (add to document head if not exists)
    if (!document.getElementById('mermaid-fullscreen-styles')) {
        const style = document.createElement('style');
        style.id = 'mermaid-fullscreen-styles';
        style.textContent = `
            /* Force ALL paths, lines, polylines with stroke to be dark in fullscreen (light mode) */
            .mermaid-fullscreen-inner svg path[stroke] {
                stroke: #000000 !important;
            }
            .mermaid-fullscreen-inner svg line[stroke] {
                stroke: #000000 !important;
            }
            .mermaid-fullscreen-inner svg polyline[stroke] {
                stroke: #000000 !important;
            }
            .mermaid-fullscreen-inner svg polygon[stroke] {
                stroke: #000000 !important;
            }
            /* Handle nested elements in groups */
            .mermaid-fullscreen-inner svg g path[stroke],
            .mermaid-fullscreen-inner svg g line[stroke],
            .mermaid-fullscreen-inner svg g polyline[stroke] {
                stroke: #000000 !important;
            }
            /* Mermaid-specific classes */
            .mermaid-fullscreen-inner svg .edge-thickness-normal,
            .mermaid-fullscreen-inner svg .edge-pattern-solid,
            .mermaid-fullscreen-inner svg path.flowchart-link,
            .mermaid-fullscreen-inner svg path.flowchart-arrowheadPath {
                stroke: #000000 !important;
            }
            /* Override with white arrows in dark mode */
            @media (prefers-color-scheme: dark) {
                .mermaid-fullscreen-inner svg path[stroke],
                .mermaid-fullscreen-inner svg line[stroke],
                .mermaid-fullscreen-inner svg polyline[stroke],
                .mermaid-fullscreen-inner svg polygon[stroke],
                .mermaid-fullscreen-inner svg g path[stroke],
                .mermaid-fullscreen-inner svg g line[stroke],
                .mermaid-fullscreen-inner svg g polyline[stroke],
                .mermaid-fullscreen-inner svg .edge-thickness-normal,
                .mermaid-fullscreen-inner svg .edge-pattern-solid,
                .mermaid-fullscreen-inner svg path.flowchart-link,
                .mermaid-fullscreen-inner svg path.flowchart-arrowheadPath {
                    stroke: #ffffff !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Track if overlay is already being closed to prevent multiple close attempts
    let isClosing = false;
    
    // Function to close the overlay - simple and direct
    const closeOverlay = () => {
        if (isClosing) return;
        isClosing = true;
        
        try {
            // Remove all event listeners
            closeButton.removeEventListener('click', closeButtonClickHandler);
            overlay.removeEventListener('click', overlayClickHandler);
            document.removeEventListener('keydown', escapeKeyHandler);
            
            // Check if overlay still exists and is attached to DOM
            if (overlay && document.body.contains(overlay)) {
                // Remove overlay from DOM
                document.body.removeChild(overlay);
                document.body.style.overflow = '';
                
                // Clear active overlay reference
                if (activeOverlay === overlay) {
                    activeOverlay = null;
                }
            }
        } catch (e) {
            // Already removed or error occurred
            console.log('Overlay already removed or error:', e);
        }
        
        isClosing = false;
    };
    
    // Define handlers with proper references for removal
    const closeButtonClickHandler = (e: Event) => {
        e.preventDefault();
        e.stopPropagation();
        closeOverlay();
    };
    
    const overlayClickHandler = (e: Event) => {
        if (isClosing) return;
        
        const target = e.target as HTMLElement;
        
        // If clicking on content area, don't close
        if (target.closest('.mermaid-fullscreen-content')) {
            return;
        }
        
        // If clicking directly on overlay background, close
        if (target === overlay) {
            closeOverlay();
        }
    };
    
    const escapeKeyHandler = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
            closeOverlay();
        }
    };
    
    // Add all event listeners
    closeButton.addEventListener('click', closeButtonClickHandler);
    overlay.addEventListener('click', overlayClickHandler);
    document.addEventListener('keydown', escapeKeyHandler);
    
    // Append to body and prevent scrolling
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    
    // Track this overlay as active
    activeOverlay = overlay;
};

// Store references to buttons and their handlers to prevent duplicate event listeners
const buttonHandlers = new Map<Element, (event: Event) => void>();

// Track active overlay to prevent multiple instances
let activeOverlay: HTMLElement | null = null;

// Clean up any existing overlay before creating a new one
const cleanupExistingOverlay = () => {
    if (activeOverlay) {
        try {
            if (document.body.contains(activeOverlay)) {
                document.body.removeChild(activeOverlay);
            }
        } catch (e) {
            // Error handling for overlay removal
        }
        activeOverlay = null;
        
        // Also remove any mermaid overlays that might be lingering
        const lingeringOverlays = document.querySelectorAll('[data-mermaid-overlay="true"]');
        lingeringOverlays.forEach(overlay => {
            try {
                document.body.removeChild(overlay);
            } catch (e) {
                // Ignore errors for already removed elements
            }
        });
    }
};

// Setup fullscreen button handlers after diagrams are rendered
const setupFullscreenButtons = () => {
    if (!proseRef.value) return;
    
    // First, remove any existing event listeners
    buttonHandlers.forEach((handler, btn) => {
        btn.removeEventListener('click', handler);
    });
    buttonHandlers.clear();
    
    // Get all fullscreen buttons
    const buttons = proseRef.value.querySelectorAll('.mermaid-fullscreen-btn');
    buttons.forEach(btn => {
        const mermaidId = btn.getAttribute('data-mermaid-id');
        if (mermaidId) {
            // Create a unique handler for this button
            const clickHandler = (event: Event) => {
                event.preventDefault();
                event.stopPropagation();
                openMermaidFullscreen(mermaidId);
            };
            
            // Store the handler reference so we can remove it later
            buttonHandlers.set(btn, clickHandler);
            
            // Add the new event listener
            btn.addEventListener('click', clickHandler, { passive: false });
            
            // Ensure button is visible
            const container = btn.closest('.mermaid-container');
            if (container) {
                container.classList.add('group');
            }
        }
    });
};

// Initialize Mermaid on mount
onMounted(() => {
    // Detect if we're on mobile
    const isMobile = window.innerWidth <= 768;
    
    mermaid.initialize({ 
        startOnLoad: false,
        theme: 'default',
        securityLevel: 'loose',
        flowchart: {
            useMaxWidth: isMobile, // Enable on mobile to prevent overflow
            htmlLabels: true,
            curve: 'basis'
        },
        fontFamily: 'Arial, sans-serif'
    });
    
    // Render diagrams on mount
    renderMermaidDiagrams().then(async () => {
        // Setup fullscreen buttons after rendering
        await nextTick();
        setupFullscreenButtons();
    });
});

// Debounce Mermaid rendering to avoid multiple renders
let mermaidRenderTimeout: ReturnType<typeof setTimeout> | null = null;

// Watch for content changes and re-render diagrams when streaming completes
watch([() => props.message.content, () => props.message.streamingContent], async () => {
    // Wait a bit after streaming to ensure content is complete
    if (isStreaming.value) {
        return;
    }
    
    // Clear any pending render
    if (mermaidRenderTimeout) {
        clearTimeout(mermaidRenderTimeout);
    }
    
    // Debounce rendering to avoid rapid re-renders
    mermaidRenderTimeout = setTimeout(async () => {
        await nextTick();
        await renderMermaidDiagrams();
        await nextTick();
        setupFullscreenButtons();
    }, 300); // Wait 300ms after content stops changing
}, { immediate: false });

// Also watch for when streaming state changes from true to false
watch(() => isStreaming.value, async (isCurrentlyStreaming, wasStreaming) => {
    // When streaming completes (was streaming, now not streaming)
    if (!isCurrentlyStreaming && wasStreaming) {
        // Clear any pending render
        if (mermaidRenderTimeout) {
            clearTimeout(mermaidRenderTimeout);
        }
        
        await nextTick();
        // Small delay to ensure DOM is updated, then render
        mermaidRenderTimeout = setTimeout(async () => {
            await renderMermaidDiagrams();
            await nextTick();
            setupFullscreenButtons();
        }, 300);
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

// Note: Auto-scrolling during streaming is handled by ChatLayout.vue
// We don't need to handle scroll here to avoid conflicts and jitter
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
            
            <div ref="proseRef" class="prose prose-sm max-w-none text-gray-900 dark:text-white w-full overflow-hidden">
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
                        @click.stop="stopStreaming" 
                        size="sm" 
                        variant="ghost" 
                        class="h-8 px-2 text-red-500 hover:text-red-700"
                        type="button">
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

/* Ensure explanation paragraphs after a diagram wrap like normal chat text */
.message-content :deep(.mermaid ~ p),
.streaming-content :deep(.mermaid ~ p) {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Noto Sans, Ubuntu, Cantarell, Helvetica Neue, Arial, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
    font-weight: 400;
}

/* Mermaid: improve arrow visibility in dark mode */
.dark .streaming-content :deep(.mermaid .edgePath path),
.dark .message-content :deep(.mermaid .edgePath path) {
    stroke: #ffffff !important;
}
.dark .streaming-content :deep(.mermaid .flowchart-link),
.dark .message-content :deep(.mermaid .flowchart-link) {
    stroke: #ffffff !important;
}
.dark .streaming-content :deep(.mermaid marker path),
.dark .message-content :deep(.mermaid marker path) {
    fill: #ffffff !important;
    stroke: #ffffff !important;
}



/* Ensure tables in messages take full width */
.streaming-content table,
.message-content table {
    width: 100%;
    table-layout: auto;
}

/* Mermaid diagram styling for mobile */
.message-content :deep(.mermaid-container),
.streaming-content :deep(.mermaid-container) {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.message-content :deep(.mermaid),
.streaming-content :deep(.mermaid) {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    /* Prevent horizontal scroll on mobile */
    -webkit-overflow-scrolling: touch;
}

/* Mobile-specific fixes for Mermaid diagrams */
@media (max-width: 768px) {
    .message-content :deep(.mermaid-container),
    .streaming-content :deep(.mermaid-container) {
        margin-left: -0.5rem !important;
        margin-right: -0.5rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    
    .message-content :deep(.mermaid),
    .streaming-content :deep(.mermaid) {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding: 0 !important;
        /* Ensure diagram doesn't cause horizontal overflow */
        max-width: calc(100vw - 2rem);
        width: 100%;
    }
    
    .message-content :deep(.mermaid svg),
    .streaming-content :deep(.mermaid svg) {
        max-width: 100% !important;
        height: auto !important;
    }
    
    /* Prevent prose from causing overflow */
    .prose {
        max-width: 100%;
        overflow: hidden;
    }
}

/* Fullscreen button styles */
.message-content :deep(.mermaid-container.group:hover .mermaid-fullscreen-btn),
.streaming-content :deep(.mermaid-container.group:hover .mermaid-fullscreen-btn) {
    opacity: 1 !important;
}

.message-content :deep(.mermaid-fullscreen-btn),
.streaming-content :deep(.mermaid-fullscreen-btn) {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Fullscreen overlay styles */
:deep(.mermaid-fullscreen-overlay) {
    backdrop-filter: blur(4px);
}

:deep(.mermaid-fullscreen-content) {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

:deep(.mermaid-fullscreen-inner) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    min-width: 200px;
    min-height: 200px;
}

:deep(.mermaid-fullscreen-inner svg) {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    display: block;
    visibility: visible;
}

/* Force arrows/paths in fullscreen to adapt to theme - mobile light mode fix */
:deep(.mermaid-fullscreen-inner svg path[stroke]),
:deep(.mermaid-fullscreen-inner svg line[stroke]),
:deep(.mermaid-fullscreen-inner svg polyline[stroke]),
:deep(.mermaid-fullscreen-inner svg polygon[stroke]),
:deep(.mermaid-fullscreen-inner svg g path[stroke]),
:deep(.mermaid-fullscreen-inner svg g line[stroke]),
:deep(.mermaid-fullscreen-inner svg g polyline[stroke]),
:deep(.mermaid-fullscreen-inner svg g polygon[stroke]) {
    stroke: #000000 !important; /* Dark arrows for light mode */
}

/* White arrows only in dark mode */
@media (prefers-color-scheme: dark) {
    :deep(.mermaid-fullscreen-inner svg path[stroke]),
    :deep(.mermaid-fullscreen-inner svg line[stroke]),
    :deep(.mermaid-fullscreen-inner svg polyline[stroke]),
    :deep(.mermaid-fullscreen-inner svg polygon[stroke]),
    :deep(.mermaid-fullscreen-inner svg g path[stroke]),
    :deep(.mermaid-fullscreen-inner svg g line[stroke]),
    :deep(.mermaid-fullscreen-inner svg g polyline[stroke]),
    :deep(.mermaid-fullscreen-inner svg g polygon[stroke]) {
        stroke: #ffffff !important;
    }
}
</style>