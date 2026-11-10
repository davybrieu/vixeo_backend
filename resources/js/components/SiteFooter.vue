<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import VxLogo from './VxLogo.vue';

const site = usePage().props.site;
const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-line/70 bg-surface/40">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div>
                <VxLogo size="h-8" />
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-text-muted">
                    Vos films et séries, sur votre téléphone, votre TV Android et votre navigateur.
                </p>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-text">Vixeo</h2>
                <ul class="mt-4 space-y-3 text-sm text-text-secondary">
                    <li><a href="/#fonctionnalites" class="hover:text-text">Fonctionnalités</a></li>
                    <li><Link href="/installer" class="hover:text-text">Installer</Link></li>
                    <li><a :href="site.appUrl" class="hover:text-text">Ouvrir dans le navigateur</a></li>
                </ul>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-text">Aide</h2>
                <ul class="mt-4 space-y-3 text-sm text-text-secondary">
                    <li><Link href="/faq" class="hover:text-text">Questions fréquentes</Link></li>
                    <li><Link href="/installer#tv" class="hover:text-text">Connecter une TV</Link></li>
                    <li v-if="site.contactEmail"><a :href="`mailto:${site.contactEmail}`" class="hover:text-text">Contact</a></li>
                </ul>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-text">Informations</h2>
                <ul class="mt-4 space-y-3 text-sm text-text-secondary">
                    <li><Link href="/mentions-legales" class="hover:text-text">Mentions légales</Link></li>
                    <li><Link href="/confidentialite" class="hover:text-text">Confidentialité du site</Link></li>
                    <li><a :href="`${site.appUrl}/confidentialite`" class="hover:text-text">Confidentialité de l’appli</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-line/50">
            <p class="mx-auto max-w-6xl px-5 py-6 text-xs leading-relaxed text-text-muted sm:px-8">
                © {{ year }} {{ site.publisher || 'Vixeo' }}. Informations et images des titres fournies par TMDb.
                This product uses the TMDB API but is not endorsed or certified by TMDB.
            </p>
        </div>
    </footer>
</template>
