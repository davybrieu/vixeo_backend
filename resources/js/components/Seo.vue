<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Title, description and social cards of a page (also rendered on the server).
const props = defineProps({
    title: { type: String, default: null },
    description: { type: String, required: true },
    // Kept out of search engines (a member's page).
    noindex: { type: Boolean, default: false },
});

const page = usePage();
const url = computed(() => page.props.site.url + (page.props.path === '/' ? '/' : page.props.path));
const fullTitle = computed(() => (props.title ? `${props.title} · Vixeo` : 'Vixeo'));
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description" />
        <link head-key="canonical" rel="canonical" :href="url" />
        <meta head-key="og:title" property="og:title" :content="fullTitle" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:url" property="og:url" :content="url" />
        <meta v-if="noindex" head-key="robots" name="robots" content="noindex" />
    </Head>
</template>
