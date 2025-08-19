import axios from 'axios';

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

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

// Add CSRF token to requests
const token = getCsrfToken();
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// Add request interceptor to ensure CSRF token is always included
axios.interceptors.request.use(
    (config) => {
        // Ensure CSRF token is included for all requests
        if (!config.headers['X-CSRF-TOKEN']) {
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                config.headers['X-CSRF-TOKEN'] = csrfToken;
            }
        }
        

        
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Add response interceptor to handle CSRF token errors
axios.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        if (error.response && error.response.status === 419) {
            console.error('CSRF token mismatch. Refreshing page...');
            // Refresh the page to get a new CSRF token
            window.location.reload();
        }
        return Promise.reject(error);
    }
);

class ChatApiService {
    /**
     * Get all chats for the current user
     */
    async getChats() {
        try {
            const response = await axios.get('/api/chats');
            return response.data;
        } catch (error) {
            console.error('Error fetching chats:', error);
            throw error;
        }
    }

    /**
     * Create a new chat
     */
    async createChat() {
        try {
            const response = await axios.post('/api/chats');
            return response.data;
        } catch (error) {
            console.error('Error creating chat:', error);
            throw error;
        }
    }

    /**
     * Get messages for a specific chat
     */
    async getChat(chatId) {
        try {
            const response = await axios.get(`/api/chats/${chatId}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching chat:', error);
            throw error;
        }
    }

    /**
     * Send a message in a chat
     */
    async sendMessage(chatId, message) {
        try {
            const response = await axios.post(`/api/chats/${chatId}/messages`, {
                message: message
            });
            return response.data;
        } catch (error) {
            console.error('Error sending message:', error);
            throw error;
        }
    }

    /**
     * Export a chat (Premium feature)
     */
    async exportChat(chatId) {
        try {
            const response = await axios.get(`/api/chats/${chatId}/export`);
            return response.data;
        } catch (error) {
            console.error('Error exporting chat:', error);
            throw error;
        }
    }

    /**
     * Get chat analytics (Premium feature)
     */
    async getAnalytics() {
        try {
            const response = await axios.get('/api/chats/analytics');
            return response.data;
        } catch (error) {
            console.error('Error fetching analytics:', error);
            throw error;
        }
    }

    /**
     * Delete a chat
     */
    async deleteChat(chatId) {
        try {
            const response = await axios.delete(`/api/chats/${chatId}`);
            return response.data;
        } catch (error) {
            console.error('Error deleting chat:', error);
            throw error;
        }
    }
}

export default new ChatApiService();