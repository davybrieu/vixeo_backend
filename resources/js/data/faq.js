// Questions of the FAQ page. Answers are HTML written here (never user input): links only.
// Keep them true to what the app does today (CLAUDE.md, « Textes »).
const escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const link = (href, text) => `<a href="${escape(href)}" class="font-semibold text-accent hover:text-accent-hover">${escape(text)}</a>`;

export default function faq(site) {
    const app = site.appUrl;
    const contact = site.contactEmail ? ` Une autre question ? Écrivez-nous à ${link(`mailto:${site.contactEmail}`, site.contactEmail)}.` : '';

    return [
        {
            id: 'vixeo',
            question: 'Qu’est-ce que Vixeo ?',
            answer: [
                'Vixeo est une application pour vos films et séries : des fiches complètes (résumé, distribution, saisons, bande-annonce), un profil pour chaque personne du foyer, votre liste et votre progression synchronisées sur tous vos écrans.',
            ],
        },
        {
            id: 'contenus',
            question: 'Vixeo propose-t-il des films ou des séries à regarder ?',
            answer: [
                'Non. Vixeo n’héberge, ne stocke et ne diffuse aucun film ni aucune série. Les informations et les images des titres viennent de TMDb.',
                'La lecture depuis vos propres serveurs multimédias (Jellyfin, Plex, Emby) est en préparation.',
            ],
        },
        {
            id: 'appareils',
            question: 'Sur quels appareils puis-je utiliser Vixeo ?',
            answer: [
                `Sur les téléphones et tablettes Android (${escape(site.android.minimum)} et plus récents), sur les téléviseurs et boîtiers Android TV et Google TV, et dans un navigateur récent (Chrome, Edge, Firefox ou Safari) : ${link(app, 'ouvrir Vixeo')}.`,
                'Sur iPhone et iPad, utilisez Vixeo dans Safari.',
            ],
        },
        {
            id: 'play-store',
            question: 'Vixeo est-il sur le Play Store ?',
            answer: [
                `Non. Sur Android (téléphone, tablette et TV), Vixeo s’installe uniquement depuis ce site, avec un fichier APK : ${link('/installer#android', 'voir les étapes')}. Ne l’installez depuis aucune autre source.`,
                'Le fichier est signé : Android vérifie que chaque mise à jour vient bien de nous, et son empreinte SHA-256 est indiquée sur la page Installer.',
            ],
        },
        {
            id: 'compte',
            question: 'Faut-il un compte ?',
            answer: [
                `Oui : une adresse e-mail et un mot de passe. Le même compte sert sur tous vos écrans et accueille jusqu’à 5 profils. ${link(`${app}/inscription`, 'Créer un compte')}.`,
            ],
        },
        {
            id: 'tv',
            question: 'Comment connecter ma TV sans taper mon mot de passe ?',
            answer: [
                `Ouvrez Vixeo sur la TV : l’écran de connexion affiche un QR code et un code court. Scannez le QR code avec votre téléphone (ou ouvrez ${link(`${app}/tv`, `${app.replace(/^https?:\/\//, '')}/tv`)}), vérifiez le code et appuyez sur « Autoriser ». La TV se connecte toute seule.`,
            ],
        },
        {
            id: 'controle-parental',
            question: 'Comment fonctionne le contrôle parental ?',
            answer: [
                'Un code PIN parental protège la création des profils et le réglage de leurs restrictions. Pour chaque profil, choisissez un âge maximum : le catalogue et la recherche n’affichent que les titres adaptés.',
            ],
        },
        {
            id: 'mot-de-passe',
            question: 'J’ai oublié mon mot de passe.',
            answer: [
                'Sur l’écran de connexion, choisissez « Mot de passe oublié ? » : vous recevez par e-mail un code à 6 chiffres, valable 15 minutes, pour choisir un nouveau mot de passe.',
            ],
        },
        {
            id: 'donnees',
            question: 'Que devient mon compte et mes données ?',
            answer: [
                `Pas de publicité ni de pistage. Depuis l’appli (Mon profil, puis Mon compte), vous pouvez recevoir une copie de vos données ou supprimer votre compte à tout moment. Détails dans la ${link(`${app}/confidentialite`, 'politique de confidentialité de l’appli')}.`,
            ],
        },
        {
            id: 'mises-a-jour',
            question: 'Comment mettre Vixeo à jour ?',
            answer: [
                'Sur Android, Vixeo vous prévient quand une nouvelle version est disponible : il la télécharge depuis ce site, vérifie le fichier, puis Android vous demande de confirmer l’installation. Dans le navigateur, vous avez toujours la dernière version.',
            ],
        },
        {
            id: 'cookies',
            question: 'Ce site utilise-t-il des cookies ?',
            answer: [`Non : aucun cookie, aucune mesure d’audience, aucun service tiers. ${link('/confidentialite', 'Confidentialité du site')}.${contact}`],
        },
    ];
}
