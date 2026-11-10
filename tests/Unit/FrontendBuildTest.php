<?php

/*
| The site's build (public/build, bootstrap/ssr) is committed: Forge runs no npm at deploy time.
| Same fingerprint as `sourceHash()` in vite.config.js: run `npm run build` and commit the result
| after any change to the site's sources.
*/

const FRONTEND_SOURCES = ['resources/js', 'resources/css', 'resources/views', 'vite.config.js', 'package-lock.json'];

function frontendSourceHash(string $root): string
{
    $files = [];
    foreach (FRONTEND_SOURCES as $source) {
        $path = $root.'/'.$source;
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        } else {
            $files[] = $source;
        }
    }
    $files = array_map(fn (string $file) => str_replace('\\', '/', $file), $files);
    sort($files, SORT_STRING);

    $hash = hash_init('sha256');
    foreach ($files as $file) {
        hash_update($hash, $file."\n".hash_file('sha256', $root.'/'.$file)."\n");
    }

    return hash_final($hash);
}

it('ships a build of the current site sources (run npm run build and commit it)', function () {
    $root = dirname(__DIR__, 2);

    expect($root.'/public/build/manifest.json')->toBeFile()
        ->and($root.'/bootstrap/ssr/ssr.js')->toBeFile()
        ->and($root.'/bootstrap/ssr/source-hash.txt')->toBeFile()
        ->and(trim((string) file_get_contents($root.'/bootstrap/ssr/source-hash.txt')))
        ->toBe(frontendSourceHash($root));
});
