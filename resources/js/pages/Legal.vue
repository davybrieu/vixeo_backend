<script setup>
import { usePage } from '@inertiajs/vue3';
import Seo from '../components/Seo.vue';

// Mentions légales (LCEN, article 6). Values come from the .env (config/vixeo.php); a missing
// one is simply not shown.
const props = defineProps({
    publisher: { type: Object, default: () => ({}) },
    host: { type: Object, default: () => ({}) },
});
const site = usePage().props.site;
</script>

<template>
    <Seo title="Mentions légales" description="Éditeur, hébergeur et informations légales du site Vixeo." />
    <article class="mx-auto max-w-3xl px-5 pt-32 pb-24 sm:px-8 sm:pt-40">
        <h1 class="text-4xl font-extrabold sm:text-5xl">Mentions légales</h1>

        <section class="mt-12 space-y-3" aria-labelledby="editeur">
            <h2 id="editeur" class="text-xl font-bold">Éditeur du site</h2>
            <dl class="grid gap-x-6 gap-y-2 text-text-secondary sm:grid-cols-[12rem_1fr]">
                <template v-if="publisher.name"><dt class="text-text-muted">Nom</dt><dd>{{ publisher.name }}</dd></template>
                <template v-if="publisher.address"><dt class="text-text-muted">Adresse</dt><dd>{{ publisher.address }}</dd></template>
                <template v-if="publisher.registration"><dt class="text-text-muted">Immatriculation</dt><dd>{{ publisher.registration }}</dd></template>
                <template v-if="publisher.director"><dt class="text-text-muted">Directeur de la publication</dt><dd>{{ publisher.director }}</dd></template>
                <template v-if="publisher.email">
                    <dt class="text-text-muted">Contact</dt>
                    <dd><a :href="`mailto:${publisher.email}`" class="font-semibold text-accent hover:text-accent-hover">{{ publisher.email }}</a></dd>
                </template>
            </dl>
        </section>

        <section class="mt-10 space-y-3" aria-labelledby="hebergeur">
            <h2 id="hebergeur" class="text-xl font-bold">Hébergement</h2>
            <dl class="grid gap-x-6 gap-y-2 text-text-secondary sm:grid-cols-[12rem_1fr]">
                <template v-if="host.name"><dt class="text-text-muted">Hébergeur</dt><dd>{{ host.name }}</dd></template>
                <template v-if="host.address"><dt class="text-text-muted">Adresse</dt><dd>{{ host.address }}</dd></template>
                <template v-if="host.phone"><dt class="text-text-muted">Téléphone</dt><dd>{{ host.phone }}</dd></template>
            </dl>
        </section>

        <section class="mt-10 space-y-3 leading-relaxed text-text-secondary" aria-labelledby="propriete">
            <h2 id="propriete" class="text-xl font-bold text-text">Propriété intellectuelle</h2>
            <p>Le nom Vixeo, son logo, les textes et les illustrations de ce site appartiennent à leur éditeur.</p>
            <p>
                Les informations et images des films et séries affichées dans l’appli proviennent de TMDb (The Movie
                Database). This product uses the TMDB API but is not endorsed or certified by TMDB.
            </p>
            <p>Polices Sora et Inter, sous licence SIL Open Font License (<a href="/fonts/OFL-Sora.txt" class="text-accent">Sora</a>, <a href="/fonts/OFL-Inter.txt" class="text-accent">Inter</a>).</p>
            <p>Android, Google TV et Google Play sont des marques de leurs propriétaires respectifs.</p>
        </section>

        <section class="mt-10 space-y-3 leading-relaxed text-text-secondary" aria-labelledby="donnees">
            <h2 id="donnees" class="text-xl font-bold text-text">Données personnelles</h2>
            <p>
                Ce site ne dépose aucun cookie : voir la <a href="/confidentialite" class="font-semibold text-accent hover:text-accent-hover">confidentialité du site</a>.
                Pour l’appli, voir sa <a :href="`${site.appUrl}/confidentialite`" class="font-semibold text-accent hover:text-accent-hover">politique de confidentialité</a>.
            </p>
        </section>
    </article>
</template>
