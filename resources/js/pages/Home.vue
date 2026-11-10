<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FaqList from '../components/FaqList.vue';
import FeatureRow from '../components/FeatureRow.vue';
import Icon from '../components/Icon.vue';
import PlatformPicker from '../components/PlatformPicker.vue';
import SectionHeading from '../components/SectionHeading.vue';
import Seo from '../components/Seo.vue';
import VxButton from '../components/VxButton.vue';
import faq from '../data/faq.js';
import PhoneFrame from '../mock/PhoneFrame.vue';
import PhoneHomeScreen from '../mock/PhoneHomeScreen.vue';
import PhoneListScreen from '../mock/PhoneListScreen.vue';
import PhoneParentalScreen from '../mock/PhoneParentalScreen.vue';
import TvCodeScreen from '../mock/TvCodeScreen.vue';
import TvDetailsScreen from '../mock/TvDetailsScreen.vue';
import TvFrame from '../mock/TvFrame.vue';
import TvHomeScreen from '../mock/TvHomeScreen.vue';
import TvWhoScreen from '../mock/TvWhoScreen.vue';

defineProps({ latestAndroid: { type: Object, default: null } });

const page = usePage();
const site = page.props.site;
const onAndroid = computed(() => page.props.platform !== 'web');
const hasAndroid = computed(() => !!site.android.apkUrl);
// On an Android device, installing comes first; elsewhere, opening in the browser.
const androidFirst = computed(() => onAndroid.value && hasAndroid.value);
const questions = faq(site);

const screens = [
    { icon: 'phone', label: 'Téléphone Android' },
    { icon: 'phone', label: 'Tablette Android' },
    { icon: 'tv', label: 'Android TV' },
    { icon: 'tv', label: 'Google TV' },
    { icon: 'globe', label: 'Chrome, Edge, Firefox, Safari' },
];
</script>

<template>
    <Seo
        description="Vixeo rassemble vos films et séries : fiches complètes, un profil par personne, votre liste et votre progression sur votre téléphone Android, votre TV et votre navigateur."
    />

    <!-- Hero -->
    <section class="relative overflow-hidden pt-32 pb-16 sm:pt-40">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[44rem] bg-[radial-gradient(ellipse_at_50%_0%,rgba(46,229,138,0.16),transparent_60%)]"
            aria-hidden="true"
        />
        <div class="relative mx-auto max-w-6xl px-5 text-center sm:px-8">
            <p class="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-4 py-1.5 text-sm text-text-soft">
                <span class="size-2 rounded-full bg-accent" /> Android · Android TV · Navigateur web
            </p>
            <h1 class="mx-auto mt-7 max-w-4xl text-[2.6rem] leading-[1.05] font-extrabold tracking-tight sm:text-6xl lg:text-7xl">
                Vos films et séries, <span class="text-accent">sur tous vos écrans.</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-text-secondary sm:text-xl">
                Des fiches complètes, un profil pour chaque personne et votre liste qui vous suit du téléphone à la TV.
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <template v-if="androidFirst">
                    <VxButton href="#installer" icon="download" size="lg">
                        {{ page.props.platform === 'tv' ? 'Installer sur la TV' : 'Installer sur Android' }}
                    </VxButton>
                    <VxButton :href="site.appUrl" icon="globe" variant="outline" size="lg">Ouvrir dans le navigateur</VxButton>
                </template>
                <template v-else>
                    <VxButton :href="site.appUrl" icon="globe" size="lg">Ouvrir dans le navigateur</VxButton>
                    <VxButton href="#installer" icon="phone" variant="outline" size="lg">Installer sur Android</VxButton>
                </template>
            </div>
            <p class="mt-5 text-sm text-text-muted">Vixeo n’héberge ni ne diffuse aucun film ni aucune série.</p>
        </div>

        <div class="relative mx-auto mt-16 max-w-5xl px-5 sm:px-8">
            <TvFrame label="L’accueil de Vixeo sur une TV : la grande image du titre choisi et la rangée « Continuer à regarder »">
                <TvHomeScreen />
            </TvFrame>
            <!-- The phone sits on the right: the title and the start of the row stay visible. -->
            <div class="absolute -bottom-8 right-3 w-[26%] max-w-[12rem] min-w-[6.5rem] sm:right-2 lg:-right-8">
                <PhoneFrame label="L’accueil de Vixeo sur un téléphone">
                    <PhoneHomeScreen />
                </PhoneFrame>
            </div>
        </div>

        <ul class="relative mx-auto mt-20 flex max-w-5xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-5 text-sm text-text-secondary">
            <li v-for="screen in screens" :key="screen.label" class="flex items-center gap-2">
                <Icon :name="screen.icon" :size="18" class="text-text-muted" /> {{ screen.label }}
            </li>
        </ul>
    </section>

    <!-- Choose your screen -->
    <section id="installer" class="mx-auto max-w-6xl scroll-mt-24 px-5 py-20 sm:px-8 sm:py-28" aria-labelledby="titre-ecrans">
        <SectionHeading id="titre-ecrans" title="Choisissez votre écran.">
            Chaque version est pensée pour son écran : au doigt sur le téléphone, à la télécommande sur la TV, au
            clavier et à la souris dans le navigateur.
        </SectionHeading>
        <PlatformPicker class="mt-10" :latest-android="latestAndroid" />
    </section>

    <!-- Features -->
    <section id="fonctionnalites" class="border-t border-line/60 py-20 sm:py-28" aria-labelledby="titre-fonctionnalites">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <SectionHeading id="titre-fonctionnalites" title="Ce que Vixeo change à la maison." centered>
                Tout pour choisir quoi regarder, et s’y retrouver à plusieurs.
            </SectionHeading>

            <div class="mt-20 space-y-24 sm:space-y-32">
                <FeatureRow title="Un profil pour chaque personne" icon="users">
                    « Qui regarde ? » s’affiche à l’ouverture : chacun retrouve sa liste, sa progression et son avatar. Un
                    code PIN protège un profil si besoin, et un profil enfant ne voit que les titres de son âge.
                    <template #visual>
                        <TvFrame label="L’écran « Qui regarde ? » avec quatre profils et leurs avatars en pixel art"><TvWhoScreen /></TvFrame>
                    </template>
                </FeatureRow>

                <FeatureRow title="Pensé pour la télécommande" icon="remote" reverse>
                    Sur Android TV, tout se fait aux flèches et au bouton OK : l’élément choisi est entouré de vert. Pour
                    vous connecter, scannez le QR code affiché sur la TV avec votre téléphone, sans taper de mot de passe.
                    <template #visual>
                        <TvFrame label="La connexion de la TV : un QR code à scanner et un code court"><TvCodeScreen /></TvFrame>
                    </template>
                </FeatureRow>

                <FeatureRow title="Votre liste vous suit" icon="bookmark">
                    Ajoutez un titre à votre liste sur le téléphone, retrouvez-le le soir sur la TV. « Continuer à
                    regarder », titres vus et liste sont synchronisés pour chaque profil.
                    <template #visual>
                        <div class="mx-auto flex max-w-[22rem] items-end justify-center gap-5 pt-8">
                            <PhoneFrame label="Ma liste sur le téléphone" class="w-1/2"><PhoneListScreen /></PhoneFrame>
                            <PhoneFrame label="L’accueil et « Continuer à regarder » sur le téléphone" class="w-1/2 -translate-y-8"><PhoneHomeScreen /></PhoneFrame>
                        </div>
                    </template>
                </FeatureRow>
            </div>
        </div>
    </section>

    <!-- Details band -->
    <section class="border-y border-line/60 bg-surface/50 py-20 sm:py-28" aria-labelledby="titre-fiches">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <FeatureRow
                title="Des fiches complètes"
                icon="film"
                reverse
                :points="[
                    'Recherche par titre, par acteur ou par genre',
                    'Affiches au format vertical ou horizontal, au choix de chaque profil',
                    'Informations et images fournies par TMDb',
                ]"
            >
                Résumé, distribution, saisons et épisodes, recommandations et bande-annonce : chaque film et chaque série a
                sa fiche. Touchez un acteur pour voir sa filmographie.
                <template #visual>
                    <TvFrame label="La fiche d’un film : grande image, informations, boutons Ma liste, Vu et Bande-annonce, distribution"><TvDetailsScreen /></TvFrame>
                </template>
            </FeatureRow>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-6xl space-y-24 px-5 sm:px-8">
            <FeatureRow title="Un contrôle parental simple" icon="shield">
                Un code PIN parental protège la création des profils et leurs réglages. Choisissez un âge maximum pour
                chaque profil : le catalogue et la recherche s’adaptent aussitôt.
                <template #visual>
                    <PhoneFrame label="Le contrôle parental : code PIN activé et âge maximum de 12 ans pour le profil de Léa" class="mx-auto max-w-[15rem]"><PhoneParentalScreen /></PhoneFrame>
                </template>
            </FeatureRow>

            <div class="grid gap-4 sm:grid-cols-3">
                <div v-for="card in [
                    { icon: 'sparkle', title: 'Sans publicité', text: 'Aucune publicité dans l’appli, ni sur ce site.' },
                    { icon: 'lock', title: 'Sans pistage', text: 'Ni mesure d’audience, ni revente de données.' },
                    { icon: 'download', title: 'Vos données à vous', text: 'Recevez-en une copie ou supprimez votre compte depuis l’appli.' },
                ]" :key="card.title" class="rounded-3xl border border-line bg-surface/60 p-6">
                    <Icon :name="card.icon" :size="24" class="text-accent" />
                    <h3 class="mt-4 text-lg font-bold">{{ card.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ card.text }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="mx-auto max-w-4xl scroll-mt-24 px-5 pb-20 sm:px-8 sm:pb-28" aria-labelledby="titre-faq">
        <SectionHeading id="titre-faq" title="Questions fréquentes.">
            Tout ce qu’il faut savoir avant d’installer Vixeo.
        </SectionHeading>
        <FaqList :items="questions" class="mt-10" />
    </section>

    <!-- Final call -->
    <section class="relative overflow-hidden border-t border-line/60 py-24 text-center sm:py-32" aria-labelledby="titre-installer">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_50%_120%,rgba(46,229,138,0.18),transparent_60%)]" aria-hidden="true" />
        <div class="relative mx-auto max-w-3xl px-5">
            <h2 id="titre-installer" class="text-3xl font-extrabold sm:text-5xl">Installez Vixeo en une minute.</h2>
            <p class="mt-5 text-lg text-text-secondary">Un seul compte pour votre téléphone, votre TV et votre navigateur.</p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <VxButton href="#installer" icon="download" size="lg">Installer Vixeo</VxButton>
                <VxButton :href="site.appUrl" icon="globe" variant="outline" size="lg">Ouvrir dans le navigateur</VxButton>
            </div>
        </div>
    </section>

    <!-- What Vixeo is not -->
    <section class="mx-auto max-w-6xl px-5 pb-20 sm:px-8" aria-labelledby="titre-pas">
        <div class="grid gap-6 border-t border-line/60 pt-12 md:grid-cols-[1fr_2.2fr]">
            <h2 id="titre-pas" class="text-lg font-bold">Ce que Vixeo n’est pas.</h2>
            <div class="space-y-4 text-sm leading-relaxed text-text-muted">
                <p>
                    Vixeo organise des films et des séries. Il n’héberge, ne stocke et ne diffuse aucun film ni aucune
                    série.
                </p>
                <p>
                    Les informations et images des titres proviennent de TMDb. This product uses the TMDB API but is not
                    endorsed or certified by TMDB.
                </p>
                <p>Les écrans de cette page sont des illustrations : les affiches y sont remplacées par des aplats de couleur.</p>
            </div>
        </div>
    </section>
</template>
