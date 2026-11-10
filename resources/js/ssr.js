import { createInertiaApp } from '@inertiajs/vue3';
import SiteLayout from './layouts/SiteLayout.vue';

// Server-side rendering (php artisan inertia:start-ssr): search engines and slow phones get the
// full HTML at once. Keep these options in line with app.js.
createInertiaApp({
    pages: './pages',
    layout: () => SiteLayout,
    title: (title) => (title ? `${title} · Vixeo` : 'Vixeo'),
});
