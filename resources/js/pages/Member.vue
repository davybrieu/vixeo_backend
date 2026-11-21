<script setup>
import { computed } from 'vue';
import Seo from '../components/Seo.vue';
import VxButton from '../components/VxButton.vue';
import PixelAvatar from '../mock/PixelAvatar.vue';

// Public page of a Vixeo profile (www.vixeo.app/pseudo, like Twitter): the link shared from the app
// (« Partager » on a profile). With Vixeo installed on Android, the link opens the app instead.
const props = defineProps({
    member: { type: Object, required: true },
    // Android: an intent:// link that opens the profile in the app (or comes back here).
    openInApp: { type: String, default: null },
});

const initial = computed(() => (props.member.name || props.member.username || '?').trim().charAt(0).toUpperCase());
const pixelAvatar = computed(() => (props.member.avatar?.pixels ? props.member.avatar : null));
const description = computed(
    () => props.member.bio || `Le profil de ${props.member.name} (@${props.member.username}) sur Vixeo.`,
);
</script>

<template>
    <Seo :title="`${member.name} (@${member.username})`" :description="description" noindex />
    <section class="mx-auto flex max-w-xl flex-col items-center px-5 pt-32 pb-24 text-center sm:px-8 sm:pt-40">
        <div class="size-32 overflow-hidden rounded-full bg-surface-high ring-4 ring-line sm:size-36">
            <PixelAvatar v-if="pixelAvatar" :avatar="pixelAvatar" />
            <img
                v-else-if="member.avatar?.imageUrl"
                :src="member.avatar.imageUrl"
                alt=""
                class="size-full object-cover"
            />
            <span v-else class="flex size-full items-center justify-center text-5xl font-extrabold text-text" aria-hidden="true">
                {{ initial }}
            </span>
        </div>

        <h1 class="mt-6 text-3xl font-extrabold text-text sm:text-4xl">{{ member.name }}</h1>
        <p class="mt-1 text-lg text-text-muted">
            @{{ member.username }}<span v-if="member.isPrivate"> · Compte privé</span>
        </p>
        <p v-if="member.bio" class="mt-5 max-w-md text-base leading-relaxed whitespace-pre-line text-text-secondary">
            {{ member.bio }}
        </p>
        <!-- Social networks: plain links, nothing loaded from them (no third-party service). -->
        <ul v-if="member.links?.length" class="mt-6 flex flex-wrap justify-center gap-2">
            <li v-for="link in member.links" :key="link.url">
                <a
                    :href="link.url"
                    rel="me noopener nofollow"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-sm font-medium text-text-secondary transition hover:border-accent hover:text-text"
                >
                    <span class="text-text">{{ link.label }}</span>
                    <span v-if="link.label !== 'Site web'" class="text-text-muted">{{ link.handle }}</span>
                </a>
            </li>
        </ul>

        <div class="mt-10 flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-center">
            <VxButton v-if="openInApp" :href="openInApp" size="lg" icon="play">Ouvrir dans Vixeo</VxButton>
            <VxButton href="/#installer" :variant="openInApp ? 'outline' : 'primary'" size="lg" icon="download">
                Télécharger Vixeo
            </VxButton>
        </div>
        <p class="mt-6 max-w-md text-sm text-text-muted">
            <template v-if="member.isPrivate">
                Ce compte est privé : dans Vixeo, demandez à le suivre pour voir ses avis et sa liste.
            </template>
            <template v-else>
                Dans Vixeo, retrouvez ce profil, ses avis et sa liste : sur Android, ce lien l’ouvre directement dans l’appli.
            </template>
        </p>
    </section>
</template>
