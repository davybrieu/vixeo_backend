import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import SiteLayout from './layouts/SiteLayout.vue';

// Same options as ssr.js: the page is rendered on the server, then hydrated here.
createInertiaApp({
    pages: './pages',
    layout: () => SiteLayout,
    title: (title) => (title ? `${title} · Vixeo` : 'Vixeo'),
    progress: { color: '#2EE58A', showSpinner: false },
});
