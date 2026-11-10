<script setup>
defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    centered: { type: Boolean, default: false },
    id: { type: String, default: null },
});
</script>

<template>
    <div :class="['max-w-2xl', centered ? 'mx-auto text-center' : '']">
        <p v-if="eyebrow" class="mb-3 text-sm font-semibold tracking-wide text-accent uppercase">{{ eyebrow }}</p>
        <h2 :id="id" class="text-3xl leading-tight font-bold sm:text-4xl lg:text-5xl">{{ title }}</h2>
        <p v-if="$slots.default" class="mt-4 text-base leading-relaxed text-text-secondary sm:text-lg">
            <slot />
        </p>
    </div>
</template>
