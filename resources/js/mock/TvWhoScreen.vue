<script setup>
import avatars from '../data/avatars.js';
import PixelAvatar from './PixelAvatar.vue';

// "Qui regarde ?" on the TV, the first profile has the focus.
const profiles = [
    { name: 'Davy', avatar: avatars[0] },
    { name: 'Léa', avatar: avatars[3], kid: true },
    { name: 'Sam', avatar: avatars[1] },
    { name: 'Inès', avatar: avatars[2] },
];
</script>

<template>
    <div class="absolute inset-0 flex flex-col items-center justify-center bg-[radial-gradient(circle_at_50%_0%,rgba(46,229,138,0.12),transparent_60%)]">
        <div class="font-display text-[4cqw] font-bold">Qui regarde ?</div>
        <div class="mt-[4cqw] flex gap-[3.5cqw]">
            <div v-for="(p, i) in profiles" :key="p.name" class="flex flex-col items-center gap-[1.2cqw]">
                <div :class="['size-[12cqw] overflow-hidden rounded-[2cqw]', i === 0 ? 'scale-105 ring-[0.45cqw] ring-text' : '']">
                    <PixelAvatar :avatar="p.avatar" />
                </div>
                <div class="text-[1.6cqw] font-medium">{{ p.name }}</div>
                <div v-if="p.kid" class="-mt-[0.6cqw] rounded bg-surface-high px-[0.6cqw] text-[1cqw] text-text-secondary">Enfant</div>
            </div>
            <div class="flex flex-col items-center gap-[1.2cqw]">
                <div class="grid size-[12cqw] place-items-center rounded-[2cqw] border-[0.25cqw] border-white/20 text-[5cqw] text-text-muted">+</div>
                <div class="text-[1.6cqw] text-text-muted">Ajouter</div>
            </div>
        </div>
    </div>
</template>
