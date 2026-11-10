<script setup>
import PosterTile from './PosterTile.vue';
// "Ma liste": same list on every screen of the profile.
</script>

<template>
    <div class="absolute inset-0 flex flex-col px-[6cqw] pt-[14cqw]">
        <div class="font-display text-[7.5cqw] font-bold">Ma liste</div>
        <div class="mt-[2cqw] flex gap-[2cqw] text-[3.2cqw]">
            <span class="rounded-full bg-accent px-[3cqw] py-[1cqw] font-semibold text-on-accent">Tout</span>
            <span class="rounded-full bg-white/10 px-[3cqw] py-[1cqw]">Films</span>
            <span class="rounded-full bg-white/10 px-[3cqw] py-[1cqw]">Séries</span>
        </div>
        <div class="mt-[5cqw] grid grid-cols-3 gap-[3cqw]">
            <div v-for="i in 9" :key="i" class="relative">
                <PosterTile :seed="i * 5 + 1" />
                <span
                    v-if="[2, 5].includes(i)"
                    class="absolute top-[4%] right-[6%] grid size-[6cqw] place-items-center rounded-full bg-accent text-[3.4cqw] font-bold text-on-accent"
                >✓</span>
            </div>
        </div>
    </div>
</template>
