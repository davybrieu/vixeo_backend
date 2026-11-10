<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import VxButton from './VxButton.vue';
import VxLogo from './VxLogo.vue';

// Floating bar: logo, sections, "Ouvrir Vixeo". Phones: a menu button opens the same links.
const page = usePage();
const open = ref(false);
watch(() => page.url, () => (open.value = false));

const links = [
    { href: '/#fonctionnalites', label: 'Fonctionnalités', anchor: true },
    { href: '/installer', label: 'Installer' },
    { href: '/faq', label: 'FAQ' },
];
const current = computed(() => page.props.path);
</script>

<template>
    <header class="fixed inset-x-0 top-3 z-50 px-3 sm:top-4">
        <nav
            aria-label="Navigation principale"
            class="mx-auto flex h-14 max-w-5xl items-center gap-2 rounded-2xl border border-white/8 bg-bg/70 pr-2 pl-4 shadow-[0_8px_40px_-12px_rgba(0,0,0,0.8)] backdrop-blur-xl"
        >
            <Link href="/" class="mr-auto rounded-lg px-1 py-2" aria-label="Vixeo, accueil">
                <VxLogo size="h-6" />
            </Link>

            <ul class="hidden items-center gap-1 md:flex">
                <li v-for="link in links" :key="link.href">
                    <component
                        :is="link.anchor ? 'a' : Link"
                        :href="link.href"
                        :aria-current="current === link.href ? 'page' : null"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm font-medium transition',
                            current === link.href ? 'bg-white/8 text-text' : 'text-text-secondary hover:text-text',
                        ]"
                    >
                        {{ link.label }}
                    </component>
                </li>
            </ul>

            <VxButton :href="page.props.site.appUrl" class="!h-10 !px-4 !text-sm">Ouvrir Vixeo</VxButton>

            <button
                type="button"
                class="grid size-10 place-items-center rounded-xl text-text-soft hover:bg-white/8 md:hidden"
                :aria-expanded="open"
                aria-controls="menu-mobile"
                @click="open = !open"
            >
                <Icon :name="open ? 'close' : 'menu'" :label="open ? 'Fermer le menu' : 'Ouvrir le menu'" />
            </button>
        </nav>

        <div
            v-show="open"
            id="menu-mobile"
            class="mx-auto mt-2 max-w-5xl rounded-2xl border border-white/8 bg-surface/95 p-2 backdrop-blur-xl md:hidden"
        >
            <component
                :is="link.anchor ? 'a' : Link"
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="block rounded-xl px-4 py-3 text-base font-medium text-text-soft hover:bg-white/5"
                @click="open = false"
            >
                {{ link.label }}
            </component>
        </div>
    </header>
</template>
