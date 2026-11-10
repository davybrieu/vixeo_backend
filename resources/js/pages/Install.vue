<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '../components/Icon.vue';
import InstallButtons from '../components/InstallButtons.vue';
import Seo from '../components/Seo.vue';
import VxButton from '../components/VxButton.vue';

const props = defineProps({ latestAndroid: { type: Object, default: null } });

const page = usePage();
const site = page.props.site;
const android = site.android;
const appHost = site.appUrl.replace(/^https?:\/\//, '');
const hasAndroid = !!android.apkUrl;
// Short link to type with a TV remote (route /apk).
const apkShort = `${site.url.replace(/^https?:\/\//, '')}/apk`;

const cards = [
    { id: 'android', icon: 'phone', title: 'Téléphone et tablette', text: `${android.minimum} et versions plus récentes` },
    { id: 'tv', icon: 'tv', title: 'TV', text: 'Android TV et Google TV' },
    { id: 'web', icon: 'globe', title: 'Navigateur', text: 'Ordinateur, tablette, téléphone' },
];
const releaseDate = computed(() =>
    props.latestAndroid?.publishedAt
        ? new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Paris' }).format(new Date(props.latestAndroid.publishedAt))
        : null,
);
</script>

<template>
    <Seo
        title="Installer Vixeo"
        description="Installez Vixeo sur votre téléphone ou votre tablette Android, sur Android TV et Google TV, ou ouvrez-le dans votre navigateur. Les étapes, écran par écran."
    />

    <section class="relative overflow-hidden pt-32 pb-12 sm:pt-40">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[30rem] bg-[radial-gradient(ellipse_at_50%_0%,rgba(46,229,138,0.14),transparent_60%)]" aria-hidden="true" />
        <div class="relative mx-auto max-w-5xl px-5 sm:px-8">
            <h1 class="text-4xl leading-tight font-extrabold sm:text-6xl">Installer ou ouvrir Vixeo</h1>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-text-secondary">
                Un compte Vixeo suffit pour tous vos écrans. Choisissez le vôtre : les étapes prennent une minute.
            </p>
            <nav aria-label="Écrans" class="mt-10 grid gap-3 sm:grid-cols-3">
                <a
                    v-for="card in cards"
                    :key="card.id"
                    :href="`#${card.id}`"
                    :class="[
                        'group rounded-2xl border p-5 transition',
                        page.props.platform === card.id ? 'border-accent/60 bg-accent/8' : 'border-line bg-surface/60 hover:border-line-strong',
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <Icon :name="card.icon" :size="24" class="text-accent" />
                        <span v-if="page.props.platform === card.id" class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-on-accent">Votre appareil</span>
                    </div>
                    <div class="mt-4 font-display text-lg font-bold">{{ card.title }}</div>
                    <div class="mt-1 text-sm text-text-secondary">{{ card.text }}</div>
                </a>
            </nav>
        </div>
    </section>

    <div class="mx-auto max-w-5xl space-y-6 px-5 pb-24 sm:px-8">
        <!-- Android -->
        <section id="android" class="rounded-3xl border border-line bg-surface/60 p-6 sm:p-10" aria-labelledby="titre-android">
            <div class="flex items-center gap-3">
                <Icon name="phone" :size="26" class="text-accent" />
                <h2 id="titre-android" class="text-2xl font-bold sm:text-3xl">Téléphone et tablette Android</h2>
            </div>
            <p class="mt-4 leading-relaxed text-text-secondary">
                Pour {{ android.minimum }} et les versions plus récentes.
                <template v-if="latestAndroid">
                    Dernière version : <strong class="text-text">{{ latestAndroid.version }}</strong><template v-if="releaseDate">, du {{ releaseDate }}</template>.
                </template>
            </p>
            <p v-if="latestAndroid?.notes" class="mt-3 rounded-2xl border border-line bg-bg/60 p-4 text-sm leading-relaxed text-text-soft">
                <span class="font-semibold text-text">Nouveautés :</span> {{ latestAndroid.notes }}
            </p>

            <InstallButtons screen="android" class="mt-7" />

            <div v-if="hasAndroid" class="mt-10 grid gap-8 lg:grid-cols-2">
                <div>
                    <h3 class="text-lg font-bold">Installer l’APK</h3>
                    <ol class="mt-4 space-y-3 text-text-secondary">
                        <li class="flex gap-3"><span class="step">1</span> Téléchargez le fichier, uniquement depuis ce site.</li>
                        <li class="flex gap-3">
                            <span class="step">2</span>
                            <span>Ouvrez-le. La première fois, Android demande d’autoriser votre navigateur à installer des applis :
                            acceptez (Paramètres, Applications, Accès spécial des applications, Installer des applis inconnues).</span>
                        </li>
                        <li class="flex gap-3"><span class="step">3</span> Appuyez sur « Installer », puis ouvrez Vixeo.</li>
                    </ol>
                    <p v-if="site.android.apkSha256" class="mt-4 text-sm break-all text-text-muted">
                        Empreinte SHA-256 du fichier : <code class="rounded bg-bg px-1.5 py-0.5 font-mono text-xs text-text-soft">{{ site.android.apkSha256 }}</code>
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Mises à jour</h3>
                    <p class="mt-4 leading-relaxed text-text-secondary">
                        Vixeo n’est pas sur le Play Store : il vous prévient lui-même quand une nouvelle version est
                        disponible, la télécharge depuis ce site et vérifie le fichier. Android vous demande ensuite de
                        confirmer l’installation.
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-text-muted">
                        La première fois, Android vous demande aussi d’autoriser Vixeo à installer des applis.
                    </p>
                </div>
            </div>
            <p v-else class="mt-6 text-sm leading-relaxed text-text-muted">
                En attendant, Vixeo fonctionne dans le navigateur de votre téléphone : ouvrez {{ appHost }}, puis, depuis le
                menu du navigateur, ajoutez-le à l’écran d’accueil pour l’ouvrir comme une appli.
            </p>
        </section>

        <!-- TV -->
        <section id="tv" class="rounded-3xl border border-line bg-surface/60 p-6 sm:p-10" aria-labelledby="titre-tv">
            <div class="flex items-center gap-3">
                <Icon name="tv" :size="26" class="text-accent" />
                <h2 id="titre-tv" class="text-2xl font-bold sm:text-3xl">Android TV et Google TV</h2>
            </div>
            <p class="mt-4 leading-relaxed text-text-secondary">Téléviseurs et boîtiers sous Android TV ou Google TV.</p>

            <InstallButtons screen="tv" class="mt-7" />

            <div class="mt-10 grid gap-8 lg:grid-cols-2">
                <div v-if="hasAndroid">
                    <h3 class="text-lg font-bold">Installer</h3>
                    <ol class="mt-4 space-y-3 text-text-secondary">
                        <li class="flex gap-3">
                            <span class="step">1</span>
                            <span>Sur la TV, installez depuis Google Play une appli qui télécharge des fichiers (un
                            navigateur ou une appli de téléchargement).</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="step">2</span>
                            <span>Dans cette appli, ouvrez l’adresse <strong class="text-text">{{ apkShort }}</strong> : le fichier de
                            Vixeo se télécharge.</span>
                        </li>
                        <li class="flex gap-3"><span class="step">3</span> Autorisez cette appli à installer des applis quand Android le demande, puis installez Vixeo.</li>
                        <li class="flex gap-3"><span class="step">4</span> Ouvrez Vixeo depuis la liste des applications de la TV.</li>
                    </ol>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Se connecter avec le téléphone</h3>
                    <ol class="mt-4 space-y-3 text-text-secondary">
                        <li class="flex gap-3"><span class="step">1</span> Sur la TV, l’écran de connexion affiche un QR code et un code court.</li>
                        <li class="flex gap-3">
                            <span class="step">2</span>
                            <span>Scannez le QR code avec votre téléphone, ou ouvrez <a :href="`${site.appUrl}/tv`" class="font-semibold text-accent hover:text-accent-hover">{{ appHost }}/tv</a>.</span>
                        </li>
                        <li class="flex gap-3"><span class="step">3</span> Connectez-vous si besoin, vérifiez le code, puis appuyez sur « Autoriser » : la TV se connecte toute seule.</li>
                    </ol>
                    <p class="mt-4 text-sm text-text-muted">Vous pouvez aussi vous connecter sur la TV avec votre adresse e-mail et votre mot de passe.</p>
                </div>
            </div>
        </section>

        <!-- Web -->
        <section id="web" class="rounded-3xl border border-line bg-surface/60 p-6 sm:p-10" aria-labelledby="titre-web">
            <div class="flex items-center gap-3">
                <Icon name="globe" :size="26" class="text-accent" />
                <h2 id="titre-web" class="text-2xl font-bold sm:text-3xl">Dans le navigateur</h2>
            </div>
            <p class="mt-4 leading-relaxed text-text-secondary">
                Rien à installer : Vixeo s’ouvre dans un navigateur récent (Chrome, Edge, Firefox ou Safari), sur
                ordinateur, tablette ou téléphone. Vous avez toujours la dernière version.
            </p>
            <InstallButtons screen="web" class="mt-7" />
            <div class="mt-10 grid gap-6 sm:grid-cols-2">
                <div class="flex gap-4">
                    <Icon name="keyboard" :size="24" class="mt-0.5 text-accent" />
                    <p class="text-text-secondary"><strong class="text-text">Sur ordinateur</strong>, naviguez à la souris ou au clavier : les flèches pour vous déplacer, Entrée pour choisir.</p>
                </div>
                <div class="flex gap-4">
                    <Icon name="phone" :size="24" class="mt-0.5 text-accent" />
                    <p class="text-text-secondary"><strong class="text-text">Sur téléphone ou tablette</strong>, ajoutez Vixeo à l’écran d’accueil depuis le menu du navigateur pour l’ouvrir comme une appli.</p>
                </div>
            </div>
        </section>

        <section class="flex flex-col items-start justify-between gap-6 rounded-3xl border border-line p-6 sm:flex-row sm:items-center sm:p-10" aria-label="Aide">
            <div>
                <h2 class="text-xl font-bold">Besoin d’aide ?</h2>
                <p class="mt-2 text-text-secondary">
                    Les réponses aux questions les plus courantes sont dans la FAQ<template v-if="site.contactEmail">, et vous pouvez nous écrire à
                    <a :href="`mailto:${site.contactEmail}`" class="font-semibold text-accent hover:text-accent-hover">{{ site.contactEmail }}</a></template>.
                </p>
            </div>
            <VxButton href="/faq" variant="outline">Questions fréquentes</VxButton>
        </section>
    </div>
</template>

<style scoped>
@reference '../../css/app.css';

.step {
    @apply grid size-7 shrink-0 place-items-center rounded-full bg-accent/12 text-sm font-bold text-accent;
}
</style>
