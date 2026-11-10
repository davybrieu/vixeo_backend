<script setup>
import SiteFooter from '../components/SiteFooter.vue';
import SiteNav from '../components/SiteNav.vue';
</script>

<template>
    <a
        href="#contenu"
        class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-lg focus:bg-accent focus:px-4 focus:py-2 focus:text-on-accent"
    >
        Aller au contenu
    </a>
    <SiteNav />
    <main id="contenu">
        <slot />
    </main>
    <SiteFooter />
</template>
