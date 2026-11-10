<script setup>
import Icon from './Icon.vue';

// Questions as native <details>: accessible, and readable without JavaScript.
defineProps({ items: { type: Array, required: true } });
</script>

<template>
    <div class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-surface/60">
        <details v-for="item in items" :id="item.id" :key="item.id" class="group">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 px-5 py-5 text-left font-semibold sm:px-7 [&::-webkit-details-marker]:hidden">
                <span class="text-base sm:text-lg">{{ item.question }}</span>
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-white/6 text-text-secondary transition group-open:rotate-45 group-open:text-accent">
                    <Icon name="close" :size="16" class="rotate-45" />
                </span>
            </summary>
            <div class="space-y-3 px-5 pb-6 leading-relaxed text-text-secondary sm:px-7">
                <p v-for="(paragraph, i) in item.answer" :key="i" v-html="paragraph" />
            </div>
        </details>
    </div>
</template>
