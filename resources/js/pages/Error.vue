<script setup>
import { computed } from 'vue';
import Seo from '../components/Seo.vue';
import VxButton from '../components/VxButton.vue';

const props = defineProps({ status: { type: Number, required: true } });

const messages = {
    403: ['Accès refusé', 'Vous n’avez pas accès à cette page.'],
    404: ['Page introuvable', 'Cette page n’existe pas, ou plus.'],
    405: ['Page introuvable', 'Cette adresse ne s’ouvre pas de cette façon.'],
    419: ['Page expirée', 'Rechargez la page pour continuer.'],
    429: ['Trop de requêtes', 'Patientez un instant avant de réessayer.'],
    500: ['Une erreur est survenue', 'Le problème vient de nous. Réessayez dans quelques instants.'],
    503: ['Maintenance en cours', 'Le site revient dans quelques minutes.'],
};
const message = computed(() => messages[props.status] ?? messages[500]);
</script>

<template>
    <Seo :title="message[0]" :description="message[1]" />
    <section class="mx-auto flex min-h-[70vh] max-w-xl flex-col items-center justify-center px-5 pt-28 pb-16 text-center">
        <p class="font-display text-7xl font-extrabold text-accent sm:text-8xl">{{ status }}</p>
        <h1 class="mt-6 text-3xl font-bold">{{ message[0] }}</h1>
        <p class="mt-4 text-text-secondary">{{ message[1] }}</p>
        <div class="mt-9 flex flex-wrap justify-center gap-3">
            <VxButton href="/">Retour à l’accueil</VxButton>
            <VxButton href="/#installer" variant="outline">Installer Vixeo</VxButton>
        </div>
    </section>
</template>
