<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import VxButton from './VxButton.vue';

// The buttons of one screen. No store: Android (phones, tablets, TVs) gets the APK of the latest
// release, Windows its installer, when there is one; otherwise an honest "not yet" note. macOS,
// iPhone and iPad: « Bientôt disponible » (developer's request, 21/11/2026). No web app any more.
const props = defineProps({
    screen: { type: String, required: true }, // android | tv | windows | macos | ios | ipados
    size: { type: String, default: 'lg' },
});

const site = usePage().props.site;
const android = computed(() => site.android);
const windows = computed(() => site.windows);
const onWindows = computed(() => props.screen === 'windows');
const comingSoon = computed(() => ['macos', 'ios', 'ipados'].includes(props.screen));
const url = computed(() => {
    if (comingSoon.value) return null;
    return onWindows.value ? windows.value.installerUrl : android.value.apkUrl;
});
const pending = computed(() => {
    if (comingSoon.value) return 'Bientôt disponible';
    if (onWindows.value) return 'Version Windows bientôt disponible';
    return props.screen === 'tv' ? 'Version TV bientôt disponible' : 'Version Android bientôt disponible';
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <VxButton v-if="url" :href="url" icon="download" :size="size" download>
            {{ onWindows ? 'Télécharger pour Windows' : 'Télécharger l’APK' }}
        </VxButton>
        <span
            v-else
            class="inline-flex h-11 items-center rounded-xl border border-dashed border-line-strong px-4 text-sm text-text-secondary"
        >
            {{ pending }}
        </span>
    </div>
</template>
