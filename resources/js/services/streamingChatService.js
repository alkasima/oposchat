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
            console.log('Streaming session started:', sessionId);
            
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
            console.log('Streaming completed:', data.message_id);
            onComplete(data.message_id, accumulatedContent);
            this.cleanup(chatId);
        });

        // Handle usage limit exceeded
        this.eventSource.addEventListener('usage_limit_exceeded', (event) => {
            const data = JSON.parse(event.data);
            console.log('Usage limit exceeded:', data);
            onError({
                type: 'USAGE_LIMIT_EXCEEDED',
                message: data.message,
                usage: data.usage
            });
            this.cleanup(chatId);
        });

        // Handle errors
        this.eventSource.addEventListener('error', (event) => {
            console.error('Streaming error:', event);
            
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
            console.log('Streaming connection closed');
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
                    console.error('Error stopping streaming:', error);
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
     * Format streaming content with progressive markdown rendering
     */
    formatStreamingContent(content) {
        if (!content) return '';

        // Handle code blocks progressively
        let formattedContent = content;
        
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
        
        return formattedContent;
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
            return this.processStandardTable(content);
        }

        // Check for single-line table format
        if (content.includes('| Shape |') || content.includes('|Rectangle |')) {
            console.log('Detected single-line table format');
            return this.processSingleLineTableFormat(content);
        }

        console.log('No table format detected');
        return content;
    }

    // Process standard markdown table format
    processStandardTable(content) {
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
    }

    // Process single-line table format
    processSingleLineTableFormat(content) {
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
    }



    /**
     * Process markdown elements for streaming content
     */
    processMarkdownElements(content) {
        // Process math notation first (before other elements)
        content = this.processMathNotation(content);

        // Process tables (before other elements to avoid conflicts)
        content = this.processMarkdownTables(content);

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
                    processedLines.push('<ul class="list-disc list-inside my-2 space-y-1">');
                    inList = true;
                    listType = 'ul';
                }
                processedLines.push(`<li class="ml-2">${bulletMatch[1]}</li>`);
            } else if (numberedMatch) {
                if (!inList || listType !== 'ol') {
                    if (inList) processedLines.push(`</${listType}>`);
                    processedLines.push('<ol class="list-decimal list-inside my-2 space-y-1">');
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
            console.error('Failed to refresh usage data:', error);
        }
    }

}

export default new StreamingChatService(); 