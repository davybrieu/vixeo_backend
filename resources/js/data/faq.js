// Questions of the FAQ section of the home page (#faq): only the most useful ones, asked before
// installing (developer's request, 21/11/2026; the rest is said by the page itself). Answers are
// HTML written here (never user input): links only. Keep them true to what the app does today
// (CLAUDE.md, « Textes »).
const escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const link = (href, text) => `<a href="${escape(href)}" class="font-semibold text-accent hover:text-accent-hover">${escape(text)}</a>`;

export default function faq(site) {
    const apkShort = `${site.url.replace(/^https?:\/\//, '')}/apk`;

    return [
        {
            id: 'contenus',
            question: 'Vixeo propose-t-il des films ou des séries à regarder ?',
            answer: ['Non. Vixeo n’héberge, ne stocke et ne diffuse aucun film ni aucune série.'],
        },
        {
            id: 'appareils',
            question: 'Sur quels appareils puis-je utiliser Vixeo ?',
            answer: [
                `Sur les téléphones et tablettes Android (${escape(site.android.minimum)} et plus récents), sur les téléviseurs et boîtiers Android TV et Google TV, et sur les ordinateurs Windows (${escape(site.windows.minimum)}) : ${link('#installer', 'installer Vixeo')}.`,
                'Les versions pour Mac, iPhone et iPad seront bientôt disponibles. Vixeo n’a pas de version web.',
            ],
        },
        {
            id: 'play-store',
            question: 'Vixeo est-il sur le Play Store ?',
            answer: [
                `Non. Sur Android (téléphone, tablette et TV), Vixeo s’installe uniquement depuis ce site, avec un fichier APK signé : ${link('#installer', 'télécharger l’APK')}. Son empreinte SHA-256 est indiquée sous le bouton.`,
                'La première fois, Android demande d’autoriser votre navigateur à installer des applis : acceptez, puis appuyez sur « Installer ».',
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
            id: 'compte',
            question: 'Faut-il un compte ?',
            answer: [
                'Oui : une adresse e-mail et un mot de passe. Le même compte sert sur tous vos écrans et accueille jusqu’à 5 profils. Créez-le dans l’appli, depuis l’écran de connexion (« Créer un compte »).',
            ],
        },
        {
            id: 'mises-a-jour',
            question: 'Comment mettre Vixeo à jour ?',
            answer: [
                'Vixeo vous prévient quand une nouvelle version est disponible : il télécharge le fichier et le vérifie. Sur Android, confirmez ensuite l’installation ; sur Windows, elle se fait toute seule et Vixeo redémarre.',
            ],
        },
    ];
}
