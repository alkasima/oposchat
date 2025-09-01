import axios from 'axios';

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;

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

// Set initial CSRF token
const initialToken = getCsrfToken();
if (initialToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = initialToken;
}

// Request interceptor to ensure CSRF token is always included
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

// Response interceptor to handle CSRF token errors gracefully
axios.interceptors.response.use(
    (response) => {
        return response;
    },
    async (error) => {
        const originalRequest = error.config;
        
        // Check if it's a CSRF token error and we haven't already retried
        if (error.response && error.response.status === 419 && !originalRequest._retry) {
            originalRequest._retry = true;
            
            try {
                console.warn('CSRF token expired, refreshing token and retrying request...');
                
                // Refresh CSRF token
                const response = await fetch('/csrf-token', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.csrf_token) {
                        // Update meta tag
                        const metaTag = document.querySelector('meta[name="csrf-token"]');
                        if (metaTag) {
                            metaTag.setAttribute('content', data.csrf_token);
                        }
                        
                        // Update global variable
                        window.csrfToken = data.csrf_token;
                        
                        // Update axios defaults
                        axios.defaults.headers.common['X-CSRF-TOKEN'] = data.csrf_token;
                        
                        // Update the original request with new token
                        originalRequest.headers['X-CSRF-TOKEN'] = data.csrf_token;
                        
                        console.log('CSRF token refreshed successfully, retrying request...');
                        
                        // Retry the original request
                        return axios(originalRequest);
                    }
                }
                
                throw new Error('Failed to refresh CSRF token');
            } catch (refreshError) {
                console.error('Failed to refresh CSRF token, reloading page as fallback...', refreshError);
                // Only reload as a last resort
                window.location.reload();
                return Promise.reject(refreshError);
            }
        }
        
        return Promise.reject(error);
    }
);

export default axios;