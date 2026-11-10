<script setup>
import PosterTile from './PosterTile.vue';
// Title page: big image, info, the round icon-only actions, cast.
</script>

<template>
    <div class="absolute inset-0">
        <div
            class="absolute inset-0"
            style="background: radial-gradient(circle at 75% 35%, rgba(255,140,90,0.35), transparent 50%), linear-gradient(110deg, #070a08 30%, #2a2026 100%)"
        />
        <div class="relative flex h-full flex-col justify-end gap-[1.4cqw] p-[4cqw]">
            <div class="h-[4cqw] w-[34cqw] rounded-[0.6cqw] bg-white/90" />
            <div class="flex gap-[1cqw] text-[1.3cqw] text-text-soft">
                <span>2024</span><span>·</span><span>2 h 06</span><span>·</span>
                <span class="rounded-[0.4cqw] border border-white/25 px-[0.6cqw]">12+</span><span>·</span><span>Drame, Aventure</span>
            </div>
            <div class="flex items-center gap-[1cqw]">
                <span class="flex h-[3.6cqw] items-center rounded-[0.8cqw] bg-accent px-[1.8cqw] text-[1.4cqw] font-semibold text-on-accent">▶ Lecture</span>
                <span class="grid size-[3.6cqw] place-items-center rounded-full bg-white/14 text-[1.6cqw]">＋</span>
                <span class="grid size-[3.6cqw] place-items-center rounded-full bg-white/14 text-[1.6cqw]">✓</span>
                <span class="grid size-[3.6cqw] place-items-center rounded-full bg-white/14 text-[1.4cqw]">▶</span>
            </div>
            <div class="w-[46cqw] space-y-[0.6cqw]">
                <div class="h-[1cqw] rounded bg-white/25" />
                <div class="h-[1cqw] w-[90%] rounded bg-white/25" />
                <div class="h-[1cqw] w-[70%] rounded bg-white/25" />
            </div>
            <div class="mt-[1cqw] text-[1.3cqw] font-semibold text-text-soft">Distribution</div>
            <div class="flex gap-[1.6cqw]">
                <div v-for="i in 6" :key="i" class="flex flex-col items-center gap-[0.5cqw]">
                    <div class="size-[5cqw] overflow-hidden rounded-full"><PosterTile :seed="i * 3" class="!rounded-none" /></div>
                    <div class="h-[0.8cqw] w-[4cqw] rounded bg-white/25" />
                </div>
            </div>
        </div>
    </div>
</template>
