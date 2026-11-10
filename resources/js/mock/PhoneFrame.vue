<script setup>
// A phone (about 9:19.5). Contents use cqw units of the screen width.
defineProps({ label: { type: String, required: true } });
</script>

<template>
    <figure class="relative" role="img" :aria-label="label">
        <div class="rounded-[14cqw] border border-white/12 bg-[#0c0f0d] p-[3.2%] shadow-[0_30px_80px_-20px_rgba(0,0,0,0.95)] @container">
            <div class="relative aspect-[9/19.5] overflow-hidden rounded-[11cqw] bg-bg" aria-hidden="true">
                <div class="absolute top-[1.6%] left-1/2 z-20 h-[2.6%] w-[28%] -translate-x-1/2 rounded-full bg-black" />
                <slot />
            </div>
        </div>
    </figure>
</template>
