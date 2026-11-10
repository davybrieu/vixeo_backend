<script setup>
import { usePage } from '@inertiajs/vue3';
import Seo from '../components/Seo.vue';

// Privacy of this website only (the app has its own policy, at <app>/confidentialite).
// Keep it true: no cookie, no analytics, no third party (CLAUDE.md).
const site = usePage().props.site;
</script>

<template>
    <Seo title="Confidentialité du site" description="Ce que le site Vixeo fait de vos données : aucun cookie, aucune mesure d’audience, aucun service tiers." />
    <article class="mx-auto max-w-3xl space-y-10 px-5 pt-32 pb-24 leading-relaxed text-text-secondary sm:px-8 sm:pt-40">
        <h1 class="text-4xl font-extrabold text-text sm:text-5xl">Confidentialité du site</h1>

        <section class="space-y-3" aria-labelledby="resume">
            <h2 id="resume" class="text-xl font-bold text-text">En bref</h2>
            <p>
                Ce site ne dépose <strong class="text-text">aucun cookie</strong> et n’utilise ni mesure d’audience, ni
                publicité, ni service tiers : ses polices et ses images sont servies par ce site lui-même.
            </p>
        </section>

        <section class="space-y-3" aria-labelledby="journaux">
            <h2 id="journaux" class="text-xl font-bold text-text">Journaux du serveur</h2>
            <p>
                Comme tout serveur web, celui qui héberge ce site enregistre des journaux techniques (adresse IP, page
                demandée, date, navigateur) pour sa sécurité et son bon fonctionnement. Ils sont conservés pendant une
                durée limitée et ne servent à rien d’autre.
            </p>
            <p>
                Pour afficher la dernière version Android, notre serveur interroge GitHub, où chaque version de l’appli
                est publiée : votre navigateur n’y participe pas.
            </p>
        </section>

        <section class="space-y-3" aria-labelledby="liens">
            <h2 id="liens" class="text-xl font-bold text-text">Liens vers d’autres services</h2>
            <p>
                Le bouton « Télécharger l’APK » télécharge l’appli depuis GitHub, où chaque version est publiée. « Ouvrir dans le navigateur » mène à
                l’appli Vixeo, décrite dans sa
                <a :href="`${site.appUrl}/confidentialite`" class="font-semibold text-accent hover:text-accent-hover">politique de confidentialité</a>.
            </p>
        </section>

        <section class="space-y-3" aria-labelledby="droits">
            <h2 id="droits" class="text-xl font-bold text-text">Vos droits</h2>
            <p>
                Vous pouvez demander l’accès à vos données ou leur effacement<template v-if="site.contactEmail"> en écrivant à
                <a :href="`mailto:${site.contactEmail}`" class="font-semibold text-accent hover:text-accent-hover">{{ site.contactEmail }}</a></template>,
                et adresser une réclamation à la CNIL (cnil.fr).
            </p>
        </section>
    </article>
</template>
