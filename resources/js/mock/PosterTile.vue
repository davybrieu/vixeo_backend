<script setup>
import { computed } from 'vue';

// Poster placeholder: no image, no name (like the app's posters), just a toned gradient.
const props = defineProps({
    seed: { type: Number, default: 0 },
    wide: { type: Boolean, default: false },
    focused: { type: Boolean, default: false },
    progress: { type: Number, default: null },
});

const tones = ['#1b2a22', '#22261f', '#1a2230', '#2a2026', '#14221e', '#2b2a1e', '#1f1b2a', '#18261f'];
const glows = ['46,229,138', '96,140,255', '255,140,90', '200,110,255', '255,205,90', '90,210,230'];
const style = computed(() => {
    const tone = tones[props.seed % tones.length];
    const glow = glows[(props.seed * 7 + 3) % glows.length];
    const x = 20 + ((props.seed * 37) % 60);
    return {
        background: `radial-gradient(circle at ${x}% 28%, rgba(${glow},0.38), transparent 52%), linear-gradient(165deg, ${tone}, #070a08 125%)`,
    };
});
</script>

<template>
    <div
        :class="[
            'relative shrink-0 overflow-hidden rounded-[0.6cqw] transition',
            wide ? 'aspect-video' : 'aspect-[2/3]',
            focused ? 'z-10 scale-[1.08] ring-[0.35cqw] ring-accent' : '',
        ]"
        :style="style"
    >
        <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/45 to-transparent" />
        <div v-if="progress !== null" class="absolute inset-x-[6%] bottom-[8%] h-[4%] min-h-[2px] rounded-full bg-white/25">
            <div class="h-full rounded-full bg-accent" :style="{ width: `${progress}%` }" />
        </div>
    </div>
</template>
