// Use centralized axios configuration
import axios from '../utils/axios';

// Function to get CSRF token
const getCsrfToken = () => {
    // Try to get from meta tag first
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (token && token.content) {
        return token.content;
    }
    
    // Fallback to global window variable
    if (window.csrfToken) {
        return window.csrfToken;
    }
    
    return null;
};

class StreamingChatService {
    constructor() {
        this.activeConnections = new Map();
        this.eventSource = null;
    }

    /**
     * Start streaming a message and return an EventSource
     */
    startStreaming(chatId, message, onChunk, onComplete, onError) {
        // Close any existing connection for this chat
        this.stopStreaming(chatId);

        const url = `/api/chats/${chatId}/stream`;
        const params = new URLSearchParams({ message });
        
        // Create EventSource for Server-Sent Events
        this.eventSource = new EventSource(`${url}?${params}`);
        
        // Store the connection immediately
        this.activeConnections.set(chatId, {
            eventSource: this.eventSource,
            sessionId: null,
            onComplete: onComplete,
            onError: onError
        });
        
        let sessionId = null;
        let accumulatedContent = '';
        let isComplete = false;

        // Handle session started event
        this.eventSource.addEventListener('session_started', (event) => {
            const data = JSON.parse(event.data);
            sessionId = data.session_id;
            console.log('Streaming session started: - streamingChatService.js:55', sessionId);
            
            // Update the stored connection with session ID
            const connection = this.activeConnections.get(chatId);
            if (connection) {
                connection.sessionId = sessionId;
            }

            // Update usage data if provided in the event
            if (data.usage) {
                window.dispatchEvent(new CustomEvent('usage-updated', { 
                    detail: { usage: data.usage } 
                }));
            } else {
                // Fallback: refresh usage data if not provided
                this.refreshUsageData();
            }
        });

        // Handle incoming chunks
        this.eventSource.addEventListener('chunk', (event) => {
            const data = JSON.parse(event.data);
            const chunk = data.content;
            
            if (chunk) {
                accumulatedContent += chunk;
                const formattedContent = this.formatStreamingContent(accumulatedContent);
                // Call the chunk handler with formatted content
                onChunk(chunk, accumulatedContent, formattedContent);
            }
        });

        // Handle completion
        this.eventSource.addEventListener('completed', (event) => {
            const data = JSON.parse(event.data);
            isComplete = true;
            console.log('Streaming completed: - streamingChatService.js:91', data.message_id);
            onComplete(data.message_id, accumulatedContent);
            this.cleanup(chatId);
        });

        // Handle usage limit exceeded
        this.eventSource.addEventListener('usage_limit_exceeded', (event) => {
            const data = JSON.parse(event.data);
            console.log('Usage limit exceeded: - streamingChatService.js:99', data);
            onError({
                type: 'USAGE_LIMIT_EXCEEDED',
                message: data.message,
                usage: data.usage
            });
            this.cleanup(chatId);
        });

        // Handle errors
        this.eventSource.addEventListener('error', (event) => {
            console.error('Streaming error: - streamingChatService.js:110', event);
            
            // Check if it's a connection error or server error
            if (this.eventSource.readyState === EventSource.CLOSED) {
                onError('Connection closed unexpectedly');
            } else if (this.eventSource.readyState === EventSource.CONNECTING) {
                onError('Connection failed, retrying...');
            } else {
                onError('Streaming connection failed');
            }
            
            this.cleanup(chatId);
        });

        // Handle connection close
        this.eventSource.addEventListener('close', (event) => {
            console.log('Streaming connection closed - streamingChatService.js:126');
            this.cleanup(chatId);
        });

        return this.eventSource;
    }

    /**
     * Stop streaming for a specific chat
     */
    async stopStreaming(chatId) {
        const connection = this.activeConnections.get(chatId);
        if (connection) {
            // Close the EventSource
            if (connection.eventSource) {
                connection.eventSource.close();
            }

            // Send stop request to server if we have a session ID
            if (connection.sessionId) {
                try {
                    await axios.post('/api/chats/stream/stop', {
                        session_id: connection.sessionId
                    }, {
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken()
                        }
                    });
                } catch (error) {
                    console.error('Error stopping streaming: - streamingChatService.js:155', error);
                }
            }

            this.activeConnections.delete(chatId);
        }
    }

    /**
     * Stop all active streaming connections
     */
    stopAllStreaming() {
        for (const [chatId] of this.activeConnections) {
            this.stopStreaming(chatId);
        }
    }

    /**
     * Clean up connection for a specific chat
     */
    cleanup(chatId) {
        const connection = this.activeConnections.get(chatId);
        if (connection && connection.eventSource) {
            connection.eventSource.close();
        }
        this.activeConnections.delete(chatId);
    }

    /**
     * Decode HTML entities
     */
    decodeHtml(html) {
        const txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }

    /**
     * Format streaming content with progressive markdown rendering
     */
    formatStreamingContent(content) {
        if (!content) return '';

        // First, decode HTML entities
        const decodedContent = this.decodeHtml(content);

        // If content contains HTML table tags, treat as HTML and add styling
        if (decodedContent.includes('<table>')) {
            let formattedContent = decodedContent;

            // Add wrapper and styling to existing HTML tables
            formattedContent = formattedContent.replace(
                /<table>/g,
                '<div class="overflow-x-auto my-6"><table class="w-full border-collapse border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">'
            );
            formattedContent = formattedContent.replace(/<\/table>/g, '</table></div>');

            // Add styling to table headers and cells
            formattedContent = formattedContent.replace(
                /<th>/g,
                '<th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800">'
            );
            formattedContent = formattedContent.replace(
                /<td>/g,
                '<td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-900 dark:text-gray-100">'
            );

            return formattedContent;
        }

        // Handle code blocks progressively
        let formattedContent = decodedContent;
        
        // Process incomplete code blocks
        const codeBlockRegex = /```(\w+)?\n([\s\S]*?)(?:```|$)/g;
        let match;
        let processedContent = '';
        let lastIndex = 0;

        while ((match = codeBlockRegex.exec(content)) !== null) {
            const [fullMatch, language, codeContent] = match;
            const startIndex = match.index;
            
            // Add content before the code block
            processedContent += content.slice(lastIndex, startIndex);
            
            // Check if code block is complete
            if (fullMatch.endsWith('```')) {
                // Complete code block - apply syntax highlighting
                const languageClass = language ? `language-${language}` : '';
                processedContent += `<pre class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 overflow-x-auto"><code class="${languageClass}">${this.escapeHtml(codeContent)}</code></pre>`;
            } else {
                // Incomplete code block - show as plain text with cursor
                const languageClass = language ? `language-${language}` : '';
                processedContent += `<pre class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 overflow-x-auto"><code class="${languageClass}">${this.escapeHtml(codeContent)}<span class="animate-pulse text-orange-500">▋</span></code></pre>`;
            }
            
            lastIndex = startIndex + fullMatch.length;
        }
        
        // Add remaining content
        processedContent += content.slice(lastIndex);
        
        // Process other markdown elements
        formattedContent = this.processMarkdownElements(processedContent);
        
        // Additional processing for list-like content without proper markdown syntax
        formattedContent = this.processListLikeContent(formattedContent);
        
        return formattedContent;
    }

    /**
     * Process list-like content that doesn't have proper markdown syntax
     */
    processListLikeContent(content) {
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
                    processedLines.push('<ul class="list-disc pl-6 ml-4 my-2 space-y-1">');
                    listItems.forEach(item => {
                        processedLines.push(`<li class="ml-2">${item}</li>`);
                    });
                    processedLines.push('</ul>');
                    inList = false;
                    listItems = [];
                }
                processedLines.push(line);
            }
        }
        
        // Handle any remaining list items
        if (inList && listItems.length > 0) {
            processedLines.push('<ul class="list-disc pl-6 ml-4 my-2 space-y-1">');
            listItems.forEach(item => {
                processedLines.push(`<li class="ml-2">${item}</li>`);
            });
            processedLines.push('</ul>');
        }
        
        return processedLines.join('\n');
    }

    /**
     * Process LaTeX/math notation
     */
    processMathNotation(content) {
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
    }

    /**
     * Process markdown tables
     */
    processMarkdownTables(content) {
        console.log('Processing table content: - streamingChatService.js:340', content);

        // Check if content contains table-like structure
        if (!content.includes('|')) {
            console.log('No pipe characters found, skipping table processing - streamingChatService.js:344');
            return content;
        }

        // Detect and convert contiguous table blocks within mixed content
        const originalLines = content.split('\n');
        const lines = [...originalLines];
        const isSeparatorLine = (line) => {
            const trimmed = line.trim();
            // Check if line contains | and multiple dashes or equals
            if (!trimmed.includes('|') || (!trimmed.includes('-') && !trimmed.includes('='))) {
                return false;
            }
            // Check for multiple dashes or equals (at least 2)
            const dashCount = (trimmed.match(/-/g) || []).length;
            const equalsCount = (trimmed.match(/=/g) || []).length;
            return dashCount >= 2 || equalsCount >= 2;
        };
        const isTableRowLine = (line) => {
            const trimmed = line.trim();
            if (/^[-*+]\s+/.test(trimmed) || /^\d+\.\s+/.test(trimmed)) return false;
            const pipeCount = (trimmed.match(/\|/g) || []).length;
            const startsOrEndsWithPipe = trimmed.startsWith('|') || trimmed.endsWith('|');
            return startsOrEndsWithPipe && pipeCount >= 2;
        };
        console.log('Table lines: - streamingChatService.js:369', lines);

        // Check if this looks like a markdown table
        const hasHeaders = lines.some(line => line.startsWith('|') && line.endsWith('|'));
        const hasSeparator = lines.some(line => line.includes('|') && (line.includes('-') || line.includes('=')));

        console.log('Has headers: - streamingChatService.js:375', hasHeaders, 'Has separator:', hasSeparator);
        const result = [];
        let i = 0;
        while (i < lines.length) {
            const line = lines[i];
            if (isTableRowLine(line)) {
                let j = i + 1;
                while (j < lines.length && lines[j].trim() === '') j++;
                if (j < lines.length && isSeparatorLine(lines[j])) {
                    const block = [line, lines[j]];
                    j++;
                    while (j < lines.length && (isTableRowLine(lines[j]) || isSeparatorLine(lines[j]) || lines[j].trim() === '')) {
                        block.push(lines[j]);
                        j++;
                    }
                    const blockText = block.join('\n');
                    const tableHtml = this.processStandardTable(blockText);
                    result.push(tableHtml);
                    i = j;
                    continue;
                }
            }
            result.push(line);
            i++;
        }

        return result.join('\n');
    }

    // Process standard markdown table format
    processStandardTable(content) {
        console.log('Processing standard table format - streamingChatService.js:406');

        const lines = content.split('\n').filter(line => line.trim());
        if (lines.length < 2) return content;

        // Find separator line - improved detection
        let separatorIndex = -1;
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            // Check if line contains | and - or = characters (markdown table separator)
            if (line.includes('|') && (line.includes('-') || line.includes('='))) {
                // Additional check: make sure it's actually a separator line
                const hasMultipleDashes = (line.match(/-/g) || []).length >= 2;
                const hasMultipleEquals = (line.match(/=/g) || []).length >= 2;
                if (hasMultipleDashes || hasMultipleEquals) {
                    separatorIndex = i;
                    break;
                }
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

        // Build HTML table with ChatGPT-like styling
        let tableHtml = '<div class="overflow-x-auto my-6">';
        tableHtml += '<table class="w-full border-collapse border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">';

        // Headers
        tableHtml += '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
        headers.forEach(header => {
            tableHtml += `<th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800">${header}</th>`;
        });
        tableHtml += '</tr></thead>';

        // Data rows
        tableHtml += '<tbody class="bg-white dark:bg-gray-900">';
        dataRows.forEach((row, index) => {
            const isEven = index % 2 === 0;
            const rowClass = isEven ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800';
            tableHtml += `<tr class="${rowClass}">`;
            row.forEach(cell => {
                tableHtml += `<td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-900 dark:text-gray-100">${cell}</td>`;
            });
            tableHtml += '</tr>';
        });
        tableHtml += '</tbody></table></div>';

        return tableHtml;
    }

    // Process single-line table format
    processSingleLineTableFormat(content) {
        console.log('Processing singleline table format - streamingChatService.js:469');

        // Split by | and filter out empty parts
        const parts = content.split('|').filter(part => part.trim().length > 0);
        console.log('Parts: - streamingChatService.js:473', parts);

        // Find separator index
        let separatorIndex = -1;
        for (let i = 0; i < parts.length; i++) {
            if (parts[i].includes('-') || parts[i].includes('=')) {
                separatorIndex = i;
                break;
            }
        }

        console.log('Separator index: - streamingChatService.js:484', separatorIndex);

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

        console.log('Rows: - streamingChatService.js:508', rows);

        if (rows.length < 2) return content;

        // Build HTML table with ChatGPT-like styling
        let tableHtml = '<div class="overflow-x-auto my-6">';
        tableHtml += '<table class="w-full border-collapse border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">';

        // Headers
        tableHtml += '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
        rows[0].forEach(header => {
            tableHtml += `<th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-800">${header}</th>`;
        });
        tableHtml += '</tr></thead>';

        // Data rows
        tableHtml += '<tbody class="bg-white dark:bg-gray-900">';
        rows.slice(1).forEach((row, index) => {
            const isEven = index % 2 === 0;
            const rowClass = isEven ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800';
            tableHtml += `<tr class="${rowClass}">`;
            row.forEach(cell => {
                tableHtml += `<td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm text-gray-900 dark:text-gray-100">${cell}</td>`;
            });
            tableHtml += '</tr>';
        });
        tableHtml += '</tbody></table></div>';

        return tableHtml;
    }



    /**
     * Process markdown elements for streaming content
     */
    processMarkdownElements(content) {
        // Process tables FIRST (before any other formatting)
        content = this.processMarkdownTables(content);
        
        // Protect generated tables from further processing
        const tablePlaceholders = [];
        let tablePlaceholderIndex = 0;
        content = content.replace(/<div class="overflow-x-auto[\s\S]*?<\/div>/g, (match) => {
            const placeholder = `__TABLE_PLACEHOLDER_${tablePlaceholderIndex}__`;
            tablePlaceholders.push(match);
            tablePlaceholderIndex++;
            return placeholder;
        });

        // Process math notation (before other elements)
        content = this.processMathNotation(content);

        // Headers - using function replacements to avoid $ issues
        content = content.replace(/^### (.*$)/gim, (match, p1) => `<h3 class="text-lg font-semibold mt-4 mb-2">${p1}</h3>`);
        content = content.replace(/^## (.*$)/gim, (match, p1) => `<h2 class="text-xl font-semibold mt-4 mb-2">${p1}</h2>`);
        content = content.replace(/^# (.*$)/gim, (match, p1) => `<h1 class="text-2xl font-bold mt-4 mb-2">${p1}</h1>`);

        // Bold and italic - using function replacements
        content = content.replace(/\*\*(.*?)\*\*/g, (match, p1) => `<strong>${p1}</strong>`);
        content = content.replace(/\*(.*?)\*/g, (match, p1) => `<em>${p1}</em>`);
        
        // Process lists more carefully line by line
        const lines = content.split('\n');
        const processedLines = [];
        let inList = false;
        let listType = null;
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            
            // Check for bullet list items
            const bulletMatch = line.match(/^[-*+]\s+(.+)$/);
            // Check for numbered list items
            const numberedMatch = line.match(/^(\d+)\.\s+(.+)$/);
            
            if (bulletMatch) {
                if (!inList || listType !== 'ul') {
                    if (inList) processedLines.push(`</${listType}>`);
                    processedLines.push('<ul class="list-disc pl-6 ml-4 my-2 space-y-1">');
                    inList = true;
                    listType = 'ul';
                }
                processedLines.push(`<li class="ml-2">${bulletMatch[1]}</li>`);
            } else if (numberedMatch) {
                if (!inList || listType !== 'ol') {
                    if (inList) processedLines.push(`</${listType}>`);
                    processedLines.push('<ol class="list-decimal pl-6 ml-4 my-2 space-y-1">');
                    inList = true;
                    listType = 'ol';
                }
                processedLines.push(`<li class="ml-2" value="${numberedMatch[1]}">${numberedMatch[2]}</li>`);
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
        
        content = processedLines.join('\n');
        
        // Process line breaks more intelligently - only add breaks between paragraphs, not every line
        content = content
            .replace(/\n\n+/g, '</p><p class="mb-3">')  // Multiple newlines = paragraph breaks
            .replace(/\n/g, ' ')                        // Single newlines = spaces
            .replace(/^/, '<p class="mb-3">')           // Start with paragraph
            .replace(/$/, '</p>');                      // End with paragraph
        
        // Clean up empty paragraphs
        content = content.replace(/<p class="mb-3"><\/p>/g, '');
        
        // Restore protected tables
        for (let i = 0; i < tablePlaceholders.length; i++) {
            content = content.replace(`__TABLE_PLACEHOLDER_${i}__`, tablePlaceholders[i]);
        }
        
        return content;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Check if streaming is active for a chat
     */
    isStreaming(chatId) {
        return this.activeConnections.has(chatId);
    }

    /**
     * Get active session ID for a chat
     */
    getSessionId(chatId) {
        const connection = this.activeConnections.get(chatId);
        return connection ? connection.sessionId : null;
    }

    /**
     * Refresh usage data from the API
     */
    async refreshUsageData() {
        try {
            const response = await fetch('/usage', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.usage) {
                    // Dispatch a custom event to notify components of usage update
                    window.dispatchEvent(new CustomEvent('usage-updated', { 
                        detail: { usage: data.usage } 
                    }));
                }
            }
        } catch (error) {
            console.error('Failed to refresh usage data: - streamingChatService.js:684', error);
        }
    }

}

export default new StreamingChatService(); 