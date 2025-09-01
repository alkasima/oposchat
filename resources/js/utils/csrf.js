/**
 * Utility functions for handling CSRF tokens
 */

/**
 * Get the current CSRF token from meta tag or global variable
 */
export function getCSRFToken() {
    // Try meta tag first
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) {
        return metaToken;
    }
    
    // Fallback to global variable
    return window.csrfToken || null;
}

/**
 * Refresh CSRF token by making a request to get a fresh one
 */
export async function refreshCSRFToken() {
    try {
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
                
                // Update Inertia's CSRF token if available
                if (window.axios && window.axios.defaults) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = data.csrf_token;
                }
                
                console.log('CSRF token refreshed successfully');
                return data.csrf_token;
            }
        }
        
        throw new Error(`Failed to refresh CSRF token: ${response.status} ${response.statusText}`);
    } catch (error) {
        console.error('CSRF token refresh failed:', error);
        throw error;
    }
}

/**
 * Check if an error is a CSRF token error
 */
export function isCSRFError(error) {
    if (!error) return false;
    
    // Check status code
    if (error.status === 419) return true;
    
    // Check error message
    const message = (error.message || '').toLowerCase();
    const responseText = (error.responseText || '').toLowerCase();
    
    const csrfIndicators = [
        'csrf',
        'token mismatch',
        'token expired',
        'session expired',
        '419',
        'page expired'
    ];
    
    return csrfIndicators.some(indicator => 
        message.includes(indicator) || responseText.includes(indicator)
    );
}