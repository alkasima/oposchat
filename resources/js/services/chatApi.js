// Use centralized axios configuration
import axios from '../utils/axios';

class ChatApiService {
    /**
     * Get all chats for the current user
     */
    async getChats(page = 1) {
        try {
            const response = await axios.get(`/api/chats?page=${page}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching chats:', error);
            throw error;
        }
    }

    /**
     * Get available courses
     */
    async getCourses() {
        try {
            const response = await axios.get('/api/courses');
            return response.data;
        } catch (error) {
            console.error('Error fetching courses:', error);
            throw error;
        }
    }

    /**
     * Create a new chat
     */
    /**
     * Create a new chat
     */
    async createChat(payload = {}) {
        try {
            const response = await axios.post('/api/chats', payload);
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

    /**
     * Update chat (e.g., rename title)
     */
    async updateChat(chatId, payload) {
        try {
            const response = await axios.patch(`/api/chats/${chatId}`, payload);
            return response.data;
        } catch (error) {
            console.error('Error updating chat:', error);
            throw error;
        }
    }

    /**
     * Submit feedback for a message
     */
    async submitFeedback(messageId, feedback) {
        try {
            const response = await axios.post(`/api/messages/${messageId}/feedback`, {
                feedback: feedback // 'positive' or 'negative'
            });
            return response.data;
        } catch (error) {
            console.error('Error submitting feedback:', error);
            throw error;
        }
    }
}

export default new ChatApiService();