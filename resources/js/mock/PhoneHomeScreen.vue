<script setup>
import PosterTile from './PosterTile.vue';
import VxLogo from '../components/VxLogo.vue';
// Home of the phone app: hero, "Continuer à regarder", a row, the tab bar.
</script>

<template>
    <div class="absolute inset-0 flex flex-col pt-[12cqw]">
        <div class="px-[6cqw]"><VxLogo size="h-[6.3cqw]" /></div>
        <div
            class="relative mx-[5cqw] mt-[4cqw] aspect-[4/5] overflow-hidden rounded-[5cqw]"
            style="background: radial-gradient(circle at 60% 30%, rgba(46,229,138,0.4), transparent 55%), linear-gradient(170deg, #1b2a22, #070a08 110%)"
        >
            <div class="absolute inset-x-[6cqw] bottom-[6cqw]">
                <div class="h-[6cqw] w-[60%] rounded-[1.4cqw] bg-white/90" />
                <div class="mt-[2.4cqw] h-[2.4cqw] w-[42%] rounded bg-white/40" />
                <div class="mt-[4cqw] flex gap-[2.4cqw]">
                    <span class="flex h-[9cqw] flex-1 items-center justify-center rounded-[2.4cqw] bg-accent text-[3.6cqw] font-semibold text-on-accent">▶ Lecture</span>
                    <span class="size-[9cqw] rounded-full bg-white/15" />
                </div>
            </div>
        </div>
        <div class="mt-[6cqw] px-[6cqw] text-[3.8cqw] font-semibold">Continuer à regarder</div>
        <div class="mt-[2.4cqw] flex gap-[2.6cqw] pl-[6cqw]">
            <PosterTile v-for="i in 3" :key="i" :seed="i + 4" wide :progress="[60, 25, 85][i - 1]" class="w-[40cqw]" />
        </div>
        <div class="mt-[5cqw] px-[6cqw] text-[3.8cqw] font-semibold">Films tendance</div>
        <div class="mt-[2.4cqw] flex gap-[2.6cqw] pl-[6cqw]">
            <PosterTile v-for="i in 4" :key="i" :seed="i * 2 + 1" class="w-[25cqw]" />
        </div>
        <div class="absolute inset-x-0 bottom-0 flex justify-around border-t border-white/8 bg-bg/95 px-[4cqw] pt-[3cqw] pb-[7cqw]">
            <span v-for="i in 4" :key="i" :class="['h-[5cqw] w-[11cqw] rounded-full', i === 1 ? 'bg-accent/25' : 'bg-white/10']" />
        </div>
    </div>
</template>
