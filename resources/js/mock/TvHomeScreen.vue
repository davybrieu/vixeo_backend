<script setup>
import PosterTile from './PosterTile.vue';
import VxLogo from '../components/VxLogo.vue';
// Home of the TV app: rail on the left, big image of the focused title, rows below.
</script>

<template>
    <div class="absolute inset-0 flex">
        <div class="flex w-[6.5cqw] flex-col items-center gap-[2.6cqw] border-r border-white/5 pt-[3cqw]">
            <VxLogo mark size="h-[1.6cqw]" />
            <span v-for="i in 5" :key="i" :class="['size-[1.8cqw] rounded-[0.5cqw]', i === 1 ? 'bg-accent' : 'bg-white/15']" />
        </div>
        <div class="relative flex-1">
            <div
                class="absolute inset-x-0 top-0 h-[60%]"
                style="background: radial-gradient(circle at 72% 30%, rgba(96,140,255,0.45), transparent 55%), linear-gradient(120deg, #1a2230 10%, #070a08 75%)"
            />
            <div class="absolute inset-x-0 top-[30%] h-[30%] bg-gradient-to-b from-transparent to-bg" />
            <div class="relative px-[3.5cqw] pt-[6cqw]">
                <div class="h-[3.2cqw] w-[26cqw] rounded-[0.5cqw] bg-white/90" />
                <div class="mt-[1.2cqw] h-[1.1cqw] w-[18cqw] rounded bg-white/35" />
                <div class="mt-[0.7cqw] h-[1.1cqw] w-[30cqw] rounded bg-white/20" />
                <div class="mt-[2cqw] flex items-center gap-[1cqw]">
                    <span class="flex h-[3.2cqw] items-center gap-[0.6cqw] rounded-[0.8cqw] bg-accent px-[1.6cqw] text-[1.3cqw] font-semibold text-on-accent">
                        ▶ Lecture
                    </span>
                    <span class="size-[3.2cqw] rounded-full bg-white/12" />
                    <span class="size-[3.2cqw] rounded-full bg-white/12" />
                </div>
            </div>
            <div class="absolute inset-x-0 bottom-[3cqw] space-y-[1.2cqw] px-[3.5cqw]">
                <div class="text-[1.35cqw] font-semibold text-text-soft">Continuer à regarder</div>
                <div class="flex gap-[1.2cqw]">
                    <PosterTile v-for="i in 5" :key="i" :seed="i + 2" wide :focused="i === 2" :progress="[35, 70, 15, 55, 80][i - 1]" class="w-[15.5cqw]" />
                </div>
            </div>
        </div>
    </div>
</template>
