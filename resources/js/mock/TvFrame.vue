<script setup>
// A TV (16:9) with a thin bezel. Contents use cqw units: 1cqw = 1 % of the screen width.
defineProps({ label: { type: String, required: true } });
</script>

<template>
    <figure class="relative" role="img" :aria-label="label">
        <div class="rounded-[1.6cqw] border border-white/10 bg-[#0c0f0d] p-[0.9%] shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)] @container">
            <div class="relative aspect-video overflow-hidden rounded-[1cqw] bg-bg" aria-hidden="true">
                <slot />
            </div>
        </div>
        <div class="mx-auto h-[1.2cqw] w-[18%] rounded-b-[1cqw] bg-gradient-to-b from-[#151a17] to-[#0b0e0c]" aria-hidden="true" />
    </figure>
</template>
