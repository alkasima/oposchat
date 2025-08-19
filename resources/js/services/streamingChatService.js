import axios from 'axios';

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
     * Process markdown elements for streaming content
     */
    processMarkdownElements(content) {
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
                processedLines.push(`<li class="ml-4">${bulletMatch[1]}</li>`);
            } else if (numberedMatch) {
                if (!inList || listType !== 'ol') {
                    if (inList) processedLines.push(`</${listType}>`);
                    processedLines.push('<ol class="list-decimal list-inside my-2 space-y-1">');
                    inList = true;
                    listType = 'ol';
                }
                processedLines.push(`<li class="ml-4">${numberedMatch[2]}</li>`);
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
        
        // Line breaks
        content = content.replace(/\n/g, '<br>');
        
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
}

export default new StreamingChatService(); 