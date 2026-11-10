import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { readFileSync } from 'node:fs';
import { renderToString } from 'vue/server-renderer';
import SiteLayout from './layouts/SiteLayout.vue';

// Server-side rendering (php artisan inertia:start-ssr): search engines and slow phones get the
// full HTML at once. Keep these options in line with app.js.
let app;
const render = (page) =>
    (app ??= createInertiaApp({
        pages: './pages',
        layout: () => SiteLayout,
        title: (title) => (title ? `${title} · Vixeo` : 'Vixeo'),
    })).then((renderApp) => renderApp(page, renderToString));

/*
| Address of the SSR server, read when it starts (the build is committed, so nothing is baked in):
| INERTIA_SSR_URL, the same variable Laravel uses to reach it, from the environment or else from
| the .env of the directory it runs in (Forge daemon: /home/forge/<site>/current).
| Default http://127.0.0.1:13714. Local interface only: the server is never reachable from outside.
*/
function ssrUrl() {
    if (process.env.INERTIA_SSR_URL) {
        return process.env.INERTIA_SSR_URL;
    }
    try {
        const line = readFileSync('.env', 'utf8').match(/^\s*INERTIA_SSR_URL\s*=\s*(.*)$/m);
        return line ? line[1].trim().replace(/^(['"])(.*)\1$/, '$2') : null;
    } catch {
        return null;
    }
}

function ssrOptions() {
    try {
        const url = new URL(ssrUrl() || 'http://127.0.0.1:13714');
        return { port: Number(url.port) || 13714, host: url.hostname || '127.0.0.1' };
    } catch {
        return { port: 13714, host: '127.0.0.1' };
    }
}

createServer(render, ssrOptions());
