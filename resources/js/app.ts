import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { refreshCSRFToken } from './utils/csrf';
// Import centralized axios configuration
import './utils/axios';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Global error handler for CSRF token issues
router.on('error', async (event) => {
    const { errors } = event.detail;
    
    // Check if it's a CSRF token error (419 status)
    if (errors && (errors.status === 419 || errors.message?.includes('CSRF') || errors.message?.includes('expired'))) {
        console.warn('CSRF token expired on Inertia request, attempting to refresh...');
        
        try {
            // Try to refresh the CSRF token
            await refreshCSRFToken();
            console.log('CSRF token refreshed successfully');
            
            // For Inertia requests, we might need to reload to retry the request
            // since Inertia doesn't have built-in retry mechanism like axios
            setTimeout(() => {
                window.location.reload();
            }, 100);
        } catch (error) {
            console.error('Failed to refresh CSRF token, reloading page...', error);
            window.location.reload();
        }
    }
});

// Initialize theme before app mounts to avoid flash of wrong theme
initializeTheme();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
