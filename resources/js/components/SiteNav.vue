<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import VxButton from './VxButton.vue';
import VxLogo from './VxLogo.vue';

// Floating bar: the logo (home) and "Ouvrir Vixeo". The site is one page: no menu.
const page = usePage();
</script>

<template>
    <header class="fixed inset-x-0 top-3 z-50 px-3 sm:top-4">
        <nav
            aria-label="Navigation principale"
            class="mx-auto flex h-14 max-w-5xl items-center gap-2 rounded-2xl border border-white/8 bg-bg/70 pr-2 pl-4 shadow-[0_8px_40px_-12px_rgba(0,0,0,0.8)] backdrop-blur-xl"
        >
            <Link href="/" class="mr-auto rounded-lg px-1 py-2" aria-label="Vixeo, accueil">
                <VxLogo size="h-6" />
            </Link>

            <VxButton :href="page.props.site.appUrl" class="!h-10 !px-4 !text-sm">Ouvrir Vixeo</VxButton>
        </nav>
    </header>
</template>
