<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from './Icon.vue';

// Link styled as the app's VxButton. Internal paths go through Inertia, the rest are plain links.
const props = defineProps({
    href: { type: String, required: true },
    variant: { type: String, default: 'primary' }, // primary | secondary | outline
    size: { type: String, default: 'md' }, // md | lg
    icon: { type: String, default: null },
    external: { type: Boolean, default: false },
    download: { type: Boolean, default: false },
});

const internal = computed(() => props.href.startsWith('/') && !props.href.startsWith('//') && !props.download);

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2.5 rounded-xl font-semibold whitespace-nowrap transition duration-200',
    'focus-visible:outline-offset-4 active:scale-[0.98]',
    props.size === 'lg' ? 'h-13 px-6 text-base' : 'h-11 px-5 text-[15px]',
    {
        primary: 'bg-accent text-on-accent hover:bg-accent-hover shadow-[0_10px_30px_-12px_rgba(46,229,138,0.6)]',
        secondary: 'bg-surface-high text-text hover:bg-line',
        outline: 'border border-line-strong text-text hover:border-text-muted hover:bg-white/5',
    }[props.variant],
]);
</script>

<template>
    <Link v-if="internal" :href="href" :class="classes">
        <Icon v-if="icon" :name="icon" :size="size === 'lg' ? 22 : 20" />
        <slot />
    </Link>
    <a
        v-else
        :href="href"
        :class="classes"
        :target="external ? '_blank' : null"
        :rel="external ? 'noopener' : null"
        :download="download || null"
    >
        <Icon v-if="icon" :name="icon" :size="size === 'lg' ? 22 : 20" />
        <slot />
        <span v-if="external" class="sr-only">(nouvel onglet)</span>
    </a>
</template>
