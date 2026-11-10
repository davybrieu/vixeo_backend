import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { defineConfig } from 'vite';

/*
| The build (public/build, bootstrap/ssr) is committed: the server runs no npm at deploy time.
| Fingerprint of the site's sources, written next to the SSR bundle: a Pest test
| (tests/Unit/FrontendBuildTest.php) fails when the committed build is older than the sources.
| Keep SOURCES and the algorithm in line with that test.
*/
const SOURCES = ['resources/js', 'resources/css', 'resources/views', 'vite.config.js', 'package-lock.json'];

function listFiles(path) {
    try {
        return readdirSync(path, { withFileTypes: true }).flatMap((entry) =>
            entry.isDirectory() ? listFiles(join(path, entry.name)) : [join(path, entry.name)],
        );
    } catch {
        return [path];
    }
}

export function sourceHash(root = process.cwd()) {
    const files = SOURCES.flatMap((source) => listFiles(join(root, source)))
        .map((file) => relative(root, file).split('\\').join('/'))
        .sort();
    const hash = createHash('sha256');
    for (const file of files) {
        hash.update(`${file}\n${createHash('sha256').update(readFileSync(join(root, file))).digest('hex')}\n`);
    }
    return hash.digest('hex');
}

const writeSourceHash = {
    name: 'vixeo-source-hash',
    apply: 'build',
    closeBundle() {
        if (this.environment?.config?.build?.ssr) {
            writeFileSync('bootstrap/ssr/source-hash.txt', `${sourceHash()}\n`);
        }
    },
};

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        // Pages in resources/js/pages; SSR entry: resources/js/ssr.js (built into bootstrap/ssr).
        inertia(),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
        writeSourceHash,
    ],
    // Self-contained SSR bundle (vue, @inertiajs/vue3 included): it runs without node_modules.
    ssr: {
        noExternal: true,
    },
    build: {
        sourcemap: false,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
