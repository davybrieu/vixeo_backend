<script setup>
import { usePage } from '@inertiajs/vue3';
import FaqList from '../components/FaqList.vue';
import Seo from '../components/Seo.vue';
import VxButton from '../components/VxButton.vue';
import faq from '../data/faq.js';

const site = usePage().props.site;
const items = faq(site);
</script>

<template>
    <Seo
        title="Questions fréquentes"
        description="Appareils compatibles, compte, connexion de la TV par QR code, contrôle parental, données personnelles : les réponses aux questions sur Vixeo."
    />
    <section class="mx-auto max-w-4xl px-5 pt-32 pb-24 sm:px-8 sm:pt-40">
        <h1 class="text-4xl leading-tight font-extrabold sm:text-6xl">Questions fréquentes</h1>
        <p class="mt-5 max-w-2xl text-lg text-text-secondary">Tout ce qu’il faut savoir avant d’installer Vixeo.</p>
        <FaqList :items="items" class="mt-12" />
        <div class="mt-12 flex flex-wrap gap-3">
            <VxButton href="/installer" icon="download">Installer Vixeo</VxButton>
            <VxButton :href="site.appUrl" icon="globe" variant="outline">Ouvrir dans le navigateur</VxButton>
        </div>
    </section>
</template>
