<script setup>
// Vixeo pixel-art avatar (12×12), drawn like PixelAvatarPainter in the app.
defineProps({ avatar: { type: Object, required: true } });
</script>

<template>
    <svg viewBox="0 0 12 12" class="block size-full" shape-rendering="crispEdges" aria-hidden="true">
        <rect width="12" height="12" :fill="avatar.background" />
        <template v-for="(row, y) in avatar.pixels" :key="y">
            <template v-for="(char, x) in row.split('')" :key="x">
                <rect v-if="char !== '.'" :x="x" :y="y" width="1.02" height="1.02" :fill="avatar.palette[char]" />
            </template>
        </template>
    </svg>
</template>
