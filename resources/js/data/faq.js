// Questions of the FAQ section of the home page (#faq). Answers are HTML written here (never user input): links only.
// Keep them true to what the app does today (CLAUDE.md, « Textes »).
const escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const link = (href, text) => `<a href="${escape(href)}" class="font-semibold text-accent hover:text-accent-hover">${escape(text)}</a>`;

export default function faq(site) {
    const apkShort = `${site.url.replace(/^https?:\/\//, '')}/apk`;
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
            ],
        },
        {
            id: 'appareils',
            question: 'Sur quels appareils puis-je utiliser Vixeo ?',
            answer: [
                `Sur les téléphones et tablettes Android (${escape(site.android.minimum)} et plus récents), sur les téléviseurs et boîtiers Android TV et Google TV, et sur les ordinateurs Windows (${escape(site.windows.minimum)}) : ${link('#installer', 'installer Vixeo')}.`,
                'Vixeo n’a pas de version web. Une version pour iPhone et iPad est en préparation.',
            ],
        },
        {
            id: 'play-store',
            question: 'Vixeo est-il sur le Play Store ?',
            answer: [
                `Non. Sur Android (téléphone, tablette et TV), Vixeo s’installe uniquement depuis ce site, avec un fichier APK : ${link('#installer', 'télécharger l’APK')}. Ne l’installez depuis aucune autre source.`,
                'Le fichier est signé : Android vérifie que chaque mise à jour vient bien de nous. Son empreinte SHA-256 est indiquée sous le bouton de téléchargement.',
                'La première fois, Android demande d’autoriser votre navigateur à installer des applis (Paramètres, Applications, Accès spécial des applications, Installer des applis inconnues) : acceptez, puis appuyez sur « Installer ».',
            ],
        },
        {
            id: 'installer-tv',
            question: 'Comment installer Vixeo sur ma TV ?',
            answer: [
                `Sur la TV, installez depuis Google Play une appli qui télécharge des fichiers (un navigateur ou une appli de téléchargement). Dans cette appli, ouvrez l’adresse <strong>${escape(apkShort)}</strong> : le fichier de Vixeo se télécharge. Autorisez cette appli à installer des applis quand Android le demande, puis installez Vixeo.`,
            ],
        },
        {
            id: 'installer-windows',
            question: 'Comment installer Vixeo sur Windows ?',
            answer: [
                `Téléchargez l’installateur depuis ce site (${link('#installer', 'version Windows')}) et ouvrez-le : Vixeo s’installe pour votre compte Windows, sans droits d’administrateur.`,
                'Windows peut afficher « Windows a protégé votre ordinateur » : choisissez « Informations complémentaires », puis « Exécuter quand même ».',
            ],
        },
        {
            id: 'compte',
            question: 'Faut-il un compte ?',
            answer: [
                'Oui : une adresse e-mail et un mot de passe. Le même compte sert sur tous vos écrans et accueille jusqu’à 5 profils. Créez-le dans l’appli, depuis l’écran de connexion (« Créer un compte »).',
            ],
        },
        {
            id: 'tv',
            question: 'Comment connecter ma TV sans taper mon mot de passe ?',
            answer: [
                'Ouvrez Vixeo sur la TV : l’écran de connexion affiche un code court. Sur votre téléphone (déjà connecté), ouvrez Vixeo, puis « Paramètres » → « Connecter une TV », tapez le code et appuyez sur « Autoriser ». La TV se connecte toute seule.',
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
                'Pas de publicité ni de pistage. Depuis l’appli (Paramètres, puis Compte), vous pouvez recevoir une copie de vos données ou supprimer votre compte à tout moment.',
            ],
        },
        {
            id: 'mises-a-jour',
            question: 'Comment mettre Vixeo à jour ?',
            answer: [
                'Vixeo vous prévient quand une nouvelle version est disponible : il télécharge le fichier et le vérifie. Sur Android, confirmez ensuite l’installation ; sur Windows, elle se fait toute seule et Vixeo redémarre.',
            ],
        },
        {
            id: 'cookies',
            question: 'Ce site utilise-t-il des cookies ?',
            answer: [`Non : aucun cookie, aucune mesure d’audience, aucun service tiers. ${link('/confidentialite', 'Confidentialité du site')}.${contact}`],
        },
    ];
}
