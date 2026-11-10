<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PhoneFrame from '../mock/PhoneFrame.vue';
import PhoneHomeScreen from '../mock/PhoneHomeScreen.vue';
import TvCodeScreen from '../mock/TvCodeScreen.vue';
import TvFrame from '../mock/TvFrame.vue';
import TvHomeScreen from '../mock/TvHomeScreen.vue';
import Icon from './Icon.vue';
import InstallButtons from './InstallButtons.vue';

// "Choisissez votre écran": tabs for Android, TV and the web. The visitor's device (guessed by
// the server from the User-Agent) is selected first.
const props = defineProps({ latestAndroid: { type: Object, default: null } });

const page = usePage();
const screens = [
    { id: 'android', label: 'Téléphone et tablette', short: 'Android', icon: 'phone' },
    { id: 'tv', label: 'TV', short: 'TV', icon: 'tv' },
    { id: 'web', label: 'Navigateur', short: 'Web', icon: 'globe' },
];
const selected = ref(screens.some((s) => s.id === page.props.platform) ? page.props.platform : 'web');
const tabs = ref([]);
const minimum = computed(() => page.props.site.android.minimum);
const android = computed(() => page.props.site.android);
// Short link to type with a TV remote (route /apk).
const apkShort = computed(() => `${page.props.site.url.replace(/^https?:\/\//, '')}/apk`);

function onKey(event, index) {
    const moves = { ArrowRight: 1, ArrowLeft: -1, Home: -index, End: screens.length - 1 - index };
    if (!(event.key in moves)) return;
    event.preventDefault();
    const next = (index + moves[event.key] + screens.length) % screens.length;
    selected.value = screens[next].id;
    tabs.value[next]?.focus();
}
</script>

<template>
    <div>
        <div
            role="tablist"
            aria-label="Votre écran"
            class="inline-flex max-w-full gap-1 overflow-x-auto rounded-2xl border border-line bg-surface p-1.5"
        >
            <button
                v-for="(screen, i) in screens"
                :id="`onglet-${screen.id}`"
                :key="screen.id"
                ref="tabs"
                type="button"
                role="tab"
                :aria-selected="selected === screen.id"
                :aria-controls="`panneau-${screen.id}`"
                :tabindex="selected === screen.id ? 0 : -1"
                :class="[
                    'inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold whitespace-nowrap transition',
                    selected === screen.id ? 'bg-accent text-on-accent' : 'text-text-secondary hover:bg-white/5 hover:text-text',
                ]"
                @click="selected = screen.id"
                @keydown="onKey($event, i)"
            >
                <Icon :name="screen.icon" :size="18" />
                <span class="sm:hidden">{{ screen.short }}</span>
                <span class="hidden sm:inline">{{ screen.label }}</span>
                <span v-if="page.props.platform === screen.id" class="sr-only">(votre appareil)</span>
            </button>
        </div>

        <div
            v-for="screen in screens"
            v-show="selected === screen.id"
            :id="`panneau-${screen.id}`"
            :key="screen.id"
            role="tabpanel"
            :aria-labelledby="`onglet-${screen.id}`"
            class="mt-6 grid items-center gap-10 overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-surface to-bg p-6 sm:p-10 lg:grid-cols-[1.05fr_1fr] lg:gap-14"
        >
            <div>
                <p v-if="page.props.platform === screen.id" class="mb-4 inline-flex items-center gap-2 rounded-full bg-accent/12 px-3 py-1 text-xs font-semibold text-accent">
                    <Icon name="check" :size="14" /> Votre appareil
                </p>

                <template v-if="screen.id === 'android'">
                    <h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour Android</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Parcourez le catalogue, tenez votre liste à jour et reprenez où vous en étiez, sur téléphone comme
                        sur tablette. Compatible {{ minimum }} et versions plus récentes.
                    </p>
                </template>
                <template v-else-if="screen.id === 'tv'">
                    <h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour Android TV et Google TV</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Une interface pensée pour la télécommande, avec de grandes images et un repère vert sur l’élément
                        choisi. Pour vous connecter, scannez le QR code de la TV avec votre téléphone : aucun mot de passe à
                        taper.
                    </p>
                </template>
                <template v-else>
                    <h3 class="text-2xl font-bold sm:text-3xl">Vixeo dans le navigateur</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Rien à installer : ouvrez Vixeo dans Chrome, Edge, Firefox ou Safari, sur ordinateur, tablette ou
                        téléphone. Au clavier, les flèches et la touche Entrée suffisent, comme une télécommande.
                    </p>
                </template>

                <InstallButtons :screen="screen.id" class="mt-7" />

                <p v-if="screen.id === 'android' && android.apkUrl" class="mt-4 text-sm leading-relaxed text-text-muted">
                    La première fois, Android demande d’autoriser votre navigateur à installer des applis : acceptez,
                    puis appuyez sur « Installer ».
                    <span v-if="android.apkSha256" class="mt-2 block break-all">
                        Empreinte SHA-256 : <code class="rounded bg-bg px-1.5 py-0.5 font-mono text-xs text-text-soft">{{ android.apkSha256 }}</code>
                    </span>
                </p>
                <p v-if="screen.id === 'tv' && android.apkUrl" class="mt-4 text-sm leading-relaxed text-text-muted">
                    Sur la TV, dans une appli qui télécharge des fichiers (un navigateur par exemple), ouvrez
                    <strong class="text-text">{{ apkShort }}</strong>, puis installez Vixeo.
                </p>

                <p v-if="screen.id !== 'web' && latestAndroid" class="mt-4 text-sm text-text-muted">
                    Dernière version : {{ latestAndroid.version }}
                </p>
            </div>

            <div class="mx-auto w-full" :class="screen.id === 'android' ? 'max-w-[15rem]' : 'max-w-xl'">
                <PhoneFrame v-if="screen.id === 'android'" label="L’accueil de Vixeo sur un téléphone Android">
                    <PhoneHomeScreen />
                </PhoneFrame>
                <TvFrame v-else-if="screen.id === 'tv'" label="La connexion de la TV par QR code">
                    <TvCodeScreen />
                </TvFrame>
                <TvFrame v-else label="L’accueil de Vixeo dans le navigateur, sur un ordinateur">
                    <TvHomeScreen />
                </TvFrame>
            </div>
        </div>
    </div>
</template>
