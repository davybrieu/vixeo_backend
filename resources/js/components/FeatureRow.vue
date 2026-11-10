<script setup>
import Icon from './Icon.vue';

// A feature: text on one side, a device on the other (alternating on large screens).
defineProps({
    title: { type: String, required: true },
    icon: { type: String, required: true },
    reverse: { type: Boolean, default: false },
    points: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
        <div :class="reverse ? 'lg:order-2' : ''">
            <span class="grid size-12 place-items-center rounded-2xl bg-accent/12 text-accent"><Icon :name="icon" :size="24" /></span>
            <h3 class="mt-5 text-2xl font-bold sm:text-3xl">{{ title }}</h3>
            <p class="mt-4 text-base leading-relaxed text-text-secondary sm:text-lg"><slot /></p>
            <ul v-if="points.length" class="mt-6 space-y-3">
                <li v-for="point in points" :key="point" class="flex gap-3 text-text-soft">
                    <Icon name="check" :size="20" class="mt-0.5 text-accent" />
                    <span>{{ point }}</span>
                </li>
            </ul>
        </div>
        <div :class="reverse ? 'lg:order-1' : ''">
            <slot name="visual" />
        </div>
    </div>
</template>
