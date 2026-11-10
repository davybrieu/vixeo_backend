<script setup>
import avatars from '../data/avatars.js';
import PixelAvatar from './PixelAvatar.vue';
// Parental control: PIN on, age limit of a kid profile.
const ages = ['Tous publics', '7 ans', '10 ans', '12 ans', '16 ans'];
</script>

<template>
    <div class="absolute inset-0 flex flex-col gap-[4cqw] px-[6cqw] pt-[14cqw]">
        <div class="font-display text-[6.4cqw] font-bold">Contrôle parental</div>
        <div class="rounded-[4cqw] border border-white/8 bg-surface p-[5cqw]">
            <div class="flex items-center justify-between">
                <span class="text-[4cqw] font-semibold">Code PIN parental</span>
                <span class="rounded-[1.4cqw] bg-accent px-[2cqw] text-[3cqw] font-semibold text-on-accent">Activé</span>
            </div>
            <div class="mt-[2.4cqw] h-[2.4cqw] w-[85%] rounded bg-white/20" />
            <div class="mt-[1.4cqw] h-[2.4cqw] w-[60%] rounded bg-white/20" />
        </div>
        <div class="rounded-[4cqw] border border-white/8 bg-surface p-[5cqw]">
            <div class="flex items-center gap-[3cqw]">
                <div class="size-[11cqw] overflow-hidden rounded-[2.4cqw]"><PixelAvatar :avatar="avatars[3]" /></div>
                <span class="text-[4.2cqw] font-semibold">Léa</span>
            </div>
            <div class="mt-[4cqw] flex items-center justify-between text-[3.6cqw]">
                <span>Profil enfant</span>
                <span class="relative h-[6cqw] w-[11cqw] rounded-full bg-accent"><span class="absolute top-[0.8cqw] right-[0.8cqw] size-[4.4cqw] rounded-full bg-on-accent" /></span>
            </div>
            <div class="mt-[4cqw] text-[3.2cqw] text-text-secondary">Âge maximum</div>
            <div class="mt-[2cqw] flex flex-wrap gap-[2cqw]">
                <span
                    v-for="age in ages"
                    :key="age"
                    :class="[
                        'rounded-full px-[3cqw] py-[1.2cqw] text-[3cqw] font-semibold',
                        age === '12 ans' ? 'bg-accent/15 text-accent' : 'border border-white/15',
                    ]"
                >{{ age }}</span>
            </div>
        </div>
    </div>
</template>
