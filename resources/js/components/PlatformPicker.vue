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

// "Choisissez votre écran": tabs for Android, TV, Windows, then macOS and iPhone (« Bientôt
// disponible », developer's request, 21/11/2026). The visitor's device (guessed by the server from
// the User-Agent) is selected first. No web app any more (developer's request).
const props = defineProps({
    latestAndroid: { type: Object, default: null },
    latestWindows: { type: Object, default: null },
});

const page = usePage();
const screens = [
    { id: 'android', label: 'Téléphone et tablette', short: 'Android', icon: 'phone' },
    { id: 'tv', label: 'TV', short: 'TV', icon: 'tv' },
    { id: 'windows', label: 'Ordinateur Windows', short: 'Windows', icon: 'monitor' },
    { id: 'macos', label: 'Mac', short: 'Mac', icon: 'laptop', soon: true },
    { id: 'ios', label: 'iPhone', short: 'iPhone', icon: 'phone', soon: true },
];
const selected = ref(screens.some((s) => s.id === page.props.platform) ? page.props.platform : 'windows');
const tabs = ref([]);
const minimum = computed(() => page.props.site.android.minimum);
const android = computed(() => page.props.site.android);
const windows = computed(() => page.props.site.windows);
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
        <!-- Phone: three tabs a row (no sideways scrolling); wider: one row. -->
        <div
            role="tablist"
            aria-label="Votre écran"
            class="grid grid-cols-3 gap-1 rounded-2xl border border-line bg-surface p-1.5 sm:inline-flex sm:max-w-full"
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
                    'inline-flex h-11 items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold whitespace-nowrap transition sm:px-4',
                    selected === screen.id ? 'bg-accent text-on-accent' : 'text-text-secondary hover:bg-white/5 hover:text-text',
                ]"
                @click="selected = screen.id"
                @keydown="onKey($event, i)"
            >
                <Icon :name="screen.icon" :size="18" />
                <span class="lg:hidden">{{ screen.short }}</span>
                <span class="hidden lg:inline">{{ screen.label }}</span>
                <span v-if="screen.soon" class="sr-only">(bientôt disponible)</span>
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
                        choisi. Pour vous connecter, tapez sur votre téléphone le code affiché par la TV : aucun mot de passe
                        à taper avec la télécommande.
                    </p>
                </template>
                <template v-else-if="screen.id === 'windows'">
                    <h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour Windows</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Sur votre ordinateur, à la souris ou au clavier : les flèches et la touche Entrée suffisent, comme
                        une télécommande. Compatible {{ windows.minimum }}, sans droits d’administrateur.
                    </p>
                </template>
                <template v-else-if="screen.id === 'macos'">
                    <h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour Mac</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Vixeo arrive bientôt sur Mac : le même compte, vos profils, votre liste et votre progression, à la
                        souris et au clavier.
                    </p>
                </template>
                <template v-else>
                    <h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour iPhone</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Vixeo arrive bientôt sur iPhone : votre liste, votre progression et vos profils dans la poche, avec
                        le même compte que sur vos autres écrans.
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

                <p v-if="screen.id === 'windows' && windows.installerUrl" class="mt-4 text-sm leading-relaxed text-text-muted">
                    Windows peut afficher « Windows a protégé votre ordinateur » : choisissez « Informations
                    complémentaires », puis « Exécuter quand même ». Vixeo se met ensuite à jour tout seul.
                    <span v-if="windows.installerSha256" class="mt-2 block break-all">
                        Empreinte SHA-256 : <code class="rounded bg-bg px-1.5 py-0.5 font-mono text-xs text-text-soft">{{ windows.installerSha256 }}</code>
                    </span>
                </p>

                <p v-if="['android', 'tv'].includes(screen.id) && latestAndroid" class="mt-4 text-sm text-text-muted">
                    Dernière version : {{ latestAndroid.version }}
                </p>
                <p v-if="screen.id === 'windows' && latestWindows" class="mt-4 text-sm text-text-muted">
                    Dernière version : {{ latestWindows.version }}
                </p>
            </div>

            <div class="mx-auto w-full" :class="['android', 'ios'].includes(screen.id) ? 'max-w-[15rem]' : 'max-w-xl'">
                <PhoneFrame v-if="screen.id === 'android'" label="L’accueil de Vixeo sur un téléphone Android">
                    <PhoneHomeScreen />
                </PhoneFrame>
                <PhoneFrame v-else-if="screen.id === 'ios'" label="L’accueil de Vixeo sur un téléphone">
                    <PhoneHomeScreen />
                </PhoneFrame>
                <TvFrame v-else-if="screen.id === 'tv'" label="La connexion de la TV avec un code court">
                    <TvCodeScreen />
                </TvFrame>
                <TvFrame v-else :label="screen.id === 'macos' ? 'L’accueil de Vixeo sur un ordinateur' : 'L’accueil de Vixeo sur un ordinateur Windows'">
                    <TvHomeScreen />
                </TvFrame>
            </div>
        </div>
    </div>
</template>
