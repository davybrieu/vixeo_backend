<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import VxButton from './VxButton.vue';

// The buttons of one screen. No Play Store: Android (phones, tablets, TVs) gets the APK of the
// latest release, when there is one; otherwise an honest "not yet" note.
const props = defineProps({
    screen: { type: String, required: true }, // android | tv | web
    size: { type: String, default: 'lg' },
});

const site = usePage().props.site;
const android = computed(() => site.android);
const hasAndroid = computed(() => !!android.value.apkUrl);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <template v-if="screen === 'web'">
            <VxButton :href="site.appUrl" icon="globe" :size="size">Ouvrir dans le navigateur</VxButton>
            <VxButton :href="`${site.appUrl}/inscription`" variant="outline" :size="size">Créer un compte</VxButton>
        </template>

        <template v-else-if="hasAndroid">
            <VxButton :href="android.apkUrl" icon="download" :size="size" download>Télécharger l’APK</VxButton>
        </template>

        <template v-else>
            <span class="inline-flex h-11 items-center rounded-xl border border-dashed border-line-strong px-4 text-sm text-text-secondary">
                {{ screen === 'tv' ? 'Version TV bientôt disponible' : 'Version Android bientôt disponible' }}
            </span>
            <VxButton :href="site.appUrl" icon="globe" variant="outline" :size="size">En attendant, ouvrir dans le navigateur</VxButton>
        </template>
    </div>
</template>
