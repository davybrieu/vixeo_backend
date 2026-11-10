import { c as vue_exports, i as server_renderer_exports, n as _sfc_main$2, s as usePage, t as _sfc_main$3 } from "../ssr.js";
import { t as _sfc_main$4 } from "./Seo-5b7tehtx.js";
// resources/js/components/FaqList.vue
var _sfc_main$1 = {
	__name: "FaqList",
	__ssrInlineRender: true,
	props: { items: {
		type: Array,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "divide-y divide-line overflow-hidden rounded-3xl border border-line bg-surface/60" }, _attrs))}><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(__props.items, (item) => {
				_push(`<details${(0, server_renderer_exports.ssrRenderAttr)("id", item.id)} class="group"><summary class="flex cursor-pointer list-none items-center justify-between gap-6 px-5 py-5 text-left font-semibold sm:px-7 [&amp;::-webkit-details-marker]:hidden"><span class="text-base sm:text-lg">${(0, server_renderer_exports.ssrInterpolate)(item.question)}</span><span class="grid size-8 shrink-0 place-items-center rounded-full bg-white/6 text-text-secondary transition group-open:rotate-45 group-open:text-accent">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$2, {
					name: "close",
					size: 16,
					class: "rotate-45"
				}, null, _parent));
				_push(`</span></summary><div class="space-y-3 px-5 pb-6 leading-relaxed text-text-secondary sm:px-7"><!--[-->`);
				(0, server_renderer_exports.ssrRenderList)(item.answer, (paragraph, i) => {
					_push(`<p>${paragraph ?? ""}</p>`);
				});
				_push(`<!--]--></div></details>`);
			});
			_push(`<!--]--></div>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/FaqList.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
// resources/js/data/faq.js
var escape = (value) => String(value).replace(/[&<>"']/g, (c) => ({
	"&": "&amp;",
	"<": "&lt;",
	">": "&gt;",
	"\"": "&quot;",
	"'": "&#39;"
})[c]);
var link = (href, text) => `<a href="${escape(href)}" class="font-semibold text-accent hover:text-accent-hover">${escape(text)}</a>`;
function faq(site) {
	const app = site.appUrl;
	const contact = site.contactEmail ? ` Une autre question ? Écrivez-nous à ${link(`mailto:${site.contactEmail}`, site.contactEmail)}.` : "";
	return [
		{
			id: "vixeo",
			question: "Qu’est-ce que Vixeo ?",
			answer: ["Vixeo est une application pour vos films et séries : des fiches complètes (résumé, distribution, saisons, bande-annonce), un profil pour chaque personne du foyer, votre liste et votre progression synchronisées sur tous vos écrans."]
		},
		{
			id: "contenus",
			question: "Vixeo propose-t-il des films ou des séries à regarder ?",
			answer: ["Non. Vixeo n’héberge, ne stocke et ne diffuse aucun film ni aucune série. Les informations et les images des titres viennent de TMDb.", "La lecture depuis vos propres serveurs multimédias (Jellyfin, Plex, Emby) est en préparation."]
		},
		{
			id: "appareils",
			question: "Sur quels appareils puis-je utiliser Vixeo ?",
			answer: [`Sur les téléphones et tablettes Android (${escape(site.android.minimum)} et plus récents), sur les téléviseurs et boîtiers Android TV et Google TV, et dans un navigateur récent (Chrome, Edge, Firefox ou Safari) : ${link(app, "ouvrir Vixeo")}.`, "Sur iPhone et iPad, utilisez Vixeo dans Safari."]
		},
		{
			id: "play-store",
			question: "Vixeo est-il sur le Play Store ?",
			answer: [`Non. Sur Android (téléphone, tablette et TV), Vixeo s’installe uniquement depuis ce site, avec un fichier APK : ${link("/installer#android", "voir les étapes")}. Ne l’installez depuis aucune autre source.`, "Le fichier est signé : Android vérifie que chaque mise à jour vient bien de nous, et son empreinte SHA-256 est indiquée sur la page Installer."]
		},
		{
			id: "compte",
			question: "Faut-il un compte ?",
			answer: [`Oui : une adresse e-mail et un mot de passe. Le même compte sert sur tous vos écrans et accueille jusqu’à 5 profils. ${link(`${app}/inscription`, "Créer un compte")}.`]
		},
		{
			id: "tv",
			question: "Comment connecter ma TV sans taper mon mot de passe ?",
			answer: [`Ouvrez Vixeo sur la TV : l’écran de connexion affiche un QR code et un code court. Scannez le QR code avec votre téléphone (ou ouvrez ${link(`${app}/tv`, `${app.replace(/^https?:\/\//, "")}/tv`)}), vérifiez le code et appuyez sur « Autoriser ». La TV se connecte toute seule.`]
		},
		{
			id: "controle-parental",
			question: "Comment fonctionne le contrôle parental ?",
			answer: ["Un code PIN parental protège la création des profils et le réglage de leurs restrictions. Pour chaque profil, choisissez un âge maximum : le catalogue et la recherche n’affichent que les titres adaptés."]
		},
		{
			id: "mot-de-passe",
			question: "J’ai oublié mon mot de passe.",
			answer: ["Sur l’écran de connexion, choisissez « Mot de passe oublié ? » : vous recevez par e-mail un code à 6 chiffres, valable 15 minutes, pour choisir un nouveau mot de passe."]
		},
		{
			id: "donnees",
			question: "Que devient mon compte et mes données ?",
			answer: [`Pas de publicité ni de pistage. Depuis l’appli (Mon profil, puis Mon compte), vous pouvez recevoir une copie de vos données ou supprimer votre compte à tout moment. Détails dans la ${link(`${app}/confidentialite`, "politique de confidentialité de l’appli")}.`]
		},
		{
			id: "mises-a-jour",
			question: "Comment mettre Vixeo à jour ?",
			answer: ["Sur Android, Vixeo vous prévient quand une nouvelle version est disponible : il la télécharge depuis ce site, vérifie le fichier, puis Android vous demande de confirmer l’installation. Dans le navigateur, vous avez toujours la dernière version."]
		},
		{
			id: "cookies",
			question: "Ce site utilise-t-il des cookies ?",
			answer: [`Non : aucun cookie, aucune mesure d’audience, aucun service tiers. ${link("/confidentialite", "Confidentialité du site")}.${contact}`]
		}
	];
}
// resources/js/pages/Faq.vue
var _sfc_main = {
	__name: "Faq",
	__ssrInlineRender: true,
	setup(__props) {
		const site = usePage().props.site;
		const items = faq(site);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$4, {
				title: "Questions fréquentes",
				description: "Appareils compatibles, compte, connexion de la TV par QR code, contrôle parental, données personnelles : les réponses aux questions sur Vixeo."
			}, null, _parent));
			_push(`<section class="mx-auto max-w-4xl px-5 pt-32 pb-24 sm:px-8 sm:pt-40"><h1 class="text-4xl leading-tight font-extrabold sm:text-6xl">Questions fréquentes</h1><p class="mt-5 max-w-2xl text-lg text-text-secondary">Tout ce qu’il faut savoir avant d’installer Vixeo.</p>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				items: (0, vue_exports.unref)(items),
				class: "mt-12"
			}, null, _parent));
			_push(`<div class="mt-12 flex flex-wrap gap-3">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, {
				href: "/installer",
				icon: "download"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Installer Vixeo`);
					else return [(0, vue_exports.createTextVNode)("Installer Vixeo")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, {
				href: (0, vue_exports.unref)(site).appUrl,
				icon: "globe",
				variant: "outline"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Ouvrir dans le navigateur`);
					else return [(0, vue_exports.createTextVNode)("Ouvrir dans le navigateur")];
				}),
				_: 1
			}, _parent));
			_push(`</div></section><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Faq.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as default };
