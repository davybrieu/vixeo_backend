import { c as vue_exports, i as server_renderer_exports, n as _sfc_main$1, s as usePage, t as _sfc_main$2 } from "../ssr.js";
import { t as _sfc_main$3 } from "./InstallButtons-BkApJZq4.js";
import { t as _sfc_main$4 } from "./Seo-5b7tehtx.js";
// \0plugin-vue:export-helper
var _plugin_vue_export_helper_default = (sfc, props) => {
	const target = sfc.__vccOpts || sfc;
	for (const [key, val] of props) target[key] = val;
	return target;
};
// resources/js/pages/Install.vue
var _sfc_main = {
	__name: "Install",
	__ssrInlineRender: true,
	props: { latestAndroid: {
		type: Object,
		default: null
	} },
	setup(__props) {
		const props = __props;
		const page = usePage();
		const site = page.props.site;
		const android = site.android;
		const appHost = site.appUrl.replace(/^https?:\/\//, "");
		const hasAndroid = !!android.apkUrl;
		const apkShort = `${site.url.replace(/^https?:\/\//, "")}/apk`;
		const cards = [
			{
				id: "android",
				icon: "phone",
				title: "Téléphone et tablette",
				text: `${android.minimum} et versions plus récentes`
			},
			{
				id: "tv",
				icon: "tv",
				title: "TV",
				text: "Android TV et Google TV"
			},
			{
				id: "web",
				icon: "globe",
				title: "Navigateur",
				text: "Ordinateur, tablette, téléphone"
			}
		];
		const releaseDate = (0, vue_exports.computed)(() => props.latestAndroid?.publishedAt ? new Intl.DateTimeFormat("fr-FR", {
			day: "numeric",
			month: "long",
			year: "numeric",
			timeZone: "Europe/Paris"
		}).format(new Date(props.latestAndroid.publishedAt)) : null);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$4, {
				title: "Installer Vixeo",
				description: "Installez Vixeo sur votre téléphone ou votre tablette Android, sur Android TV et Google TV, ou ouvrez-le dans votre navigateur. Les étapes, écran par écran."
			}, null, _parent));
			_push(`<section class="relative overflow-hidden pt-32 pb-12 sm:pt-40" data-v-25923853><div class="pointer-events-none absolute inset-x-0 top-0 h-[30rem] bg-[radial-gradient(ellipse_at_50%_0%,rgba(46,229,138,0.14),transparent_60%)]" aria-hidden="true" data-v-25923853></div><div class="relative mx-auto max-w-5xl px-5 sm:px-8" data-v-25923853><h1 class="text-4xl leading-tight font-extrabold sm:text-6xl" data-v-25923853>Installer ou ouvrir Vixeo</h1><p class="mt-5 max-w-2xl text-lg leading-relaxed text-text-secondary" data-v-25923853> Un compte Vixeo suffit pour tous vos écrans. Choisissez le vôtre : les étapes prennent une minute. </p><nav aria-label="Écrans" class="mt-10 grid gap-3 sm:grid-cols-3" data-v-25923853><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(cards, (card) => {
				_push(`<a${(0, server_renderer_exports.ssrRenderAttr)("href", `#${card.id}`)} class="${(0, server_renderer_exports.ssrRenderClass)(["group rounded-2xl border p-5 transition", (0, vue_exports.unref)(page).props.platform === card.id ? "border-accent/60 bg-accent/8" : "border-line bg-surface/60 hover:border-line-strong"])}" data-v-25923853><div class="flex items-center justify-between" data-v-25923853>`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
					name: card.icon,
					size: 24,
					class: "text-accent"
				}, null, _parent));
				if ((0, vue_exports.unref)(page).props.platform === card.id) _push(`<span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-on-accent" data-v-25923853>Votre appareil</span>`);
				else _push(`<!---->`);
				_push(`</div><div class="mt-4 font-display text-lg font-bold" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)(card.title)}</div><div class="mt-1 text-sm text-text-secondary" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)(card.text)}</div></a>`);
			});
			_push(`<!--]--></nav></div></section><div class="mx-auto max-w-5xl space-y-6 px-5 pb-24 sm:px-8" data-v-25923853><section id="android" class="rounded-3xl border border-line bg-surface/60 p-6 sm:p-10" aria-labelledby="titre-android" data-v-25923853><div class="flex items-center gap-3" data-v-25923853>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				name: "phone",
				size: 26,
				class: "text-accent"
			}, null, _parent));
			_push(`<h2 id="titre-android" class="text-2xl font-bold sm:text-3xl" data-v-25923853>Téléphone et tablette Android</h2></div><p class="mt-4 leading-relaxed text-text-secondary" data-v-25923853> Pour ${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(android).minimum)} et les versions plus récentes. `);
			if (__props.latestAndroid) {
				_push(`<!--[--> Dernière version : <strong class="text-text" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)(__props.latestAndroid.version)}</strong>`);
				if (releaseDate.value) _push(`<!--[-->, du ${(0, server_renderer_exports.ssrInterpolate)(releaseDate.value)}<!--]-->`);
				else _push(`<!---->`);
				_push(`. <!--]-->`);
			} else _push(`<!---->`);
			_push(`</p>`);
			if (__props.latestAndroid?.notes) _push(`<p class="mt-3 rounded-2xl border border-line bg-bg/60 p-4 text-sm leading-relaxed text-text-soft" data-v-25923853><span class="font-semibold text-text" data-v-25923853>Nouveautés :</span> ${(0, server_renderer_exports.ssrInterpolate)(__props.latestAndroid.notes)}</p>`);
			else _push(`<!---->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, {
				screen: "android",
				class: "mt-7"
			}, null, _parent));
			if (hasAndroid) {
				_push(`<div class="mt-10 grid gap-8 lg:grid-cols-2" data-v-25923853><div data-v-25923853><h3 class="text-lg font-bold" data-v-25923853>Installer l’APK</h3><ol class="mt-4 space-y-3 text-text-secondary" data-v-25923853><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>1</span> Téléchargez le fichier, uniquement depuis ce site.</li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>2</span><span data-v-25923853>Ouvrez-le. La première fois, Android demande d’autoriser votre navigateur à installer des applis : acceptez (Paramètres, Applications, Accès spécial des applications, Installer des applis inconnues).</span></li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>3</span> Appuyez sur « Installer », puis ouvrez Vixeo.</li></ol>`);
				if ((0, vue_exports.unref)(site).android.apkSha256) _push(`<p class="mt-4 text-sm break-all text-text-muted" data-v-25923853> Empreinte SHA-256 du fichier : <code class="rounded bg-bg px-1.5 py-0.5 font-mono text-xs text-text-soft" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(site).android.apkSha256)}</code></p>`);
				else _push(`<!---->`);
				_push(`</div><div data-v-25923853><h3 class="text-lg font-bold" data-v-25923853>Mises à jour</h3><p class="mt-4 leading-relaxed text-text-secondary" data-v-25923853> Vixeo n’est pas sur le Play Store : il vous prévient lui-même quand une nouvelle version est disponible, la télécharge depuis ce site et vérifie le fichier. Android vous demande ensuite de confirmer l’installation. </p><p class="mt-3 text-sm leading-relaxed text-text-muted" data-v-25923853> La première fois, Android vous demande aussi d’autoriser Vixeo à installer des applis. </p></div></div>`);
			} else _push(`<p class="mt-6 text-sm leading-relaxed text-text-muted" data-v-25923853> En attendant, Vixeo fonctionne dans le navigateur de votre téléphone : ouvrez ${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(appHost))}, puis, depuis le menu du navigateur, ajoutez-le à l’écran d’accueil pour l’ouvrir comme une appli. </p>`);
			_push(`</section><section id="tv" class="rounded-3xl border border-line bg-surface/60 p-6 sm:p-10" aria-labelledby="titre-tv" data-v-25923853><div class="flex items-center gap-3" data-v-25923853>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				name: "tv",
				size: 26,
				class: "text-accent"
			}, null, _parent));
			_push(`<h2 id="titre-tv" class="text-2xl font-bold sm:text-3xl" data-v-25923853>Android TV et Google TV</h2></div><p class="mt-4 leading-relaxed text-text-secondary" data-v-25923853>Téléviseurs et boîtiers sous Android TV ou Google TV.</p>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, {
				screen: "tv",
				class: "mt-7"
			}, null, _parent));
			_push(`<div class="mt-10 grid gap-8 lg:grid-cols-2" data-v-25923853>`);
			if (hasAndroid) _push(`<div data-v-25923853><h3 class="text-lg font-bold" data-v-25923853>Installer</h3><ol class="mt-4 space-y-3 text-text-secondary" data-v-25923853><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>1</span><span data-v-25923853>Sur la TV, installez depuis Google Play une appli qui télécharge des fichiers (un navigateur ou une appli de téléchargement).</span></li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>2</span><span data-v-25923853>Dans cette appli, ouvrez l’adresse <strong class="text-text" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)(apkShort)}</strong> : le fichier de Vixeo se télécharge.</span></li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>3</span> Autorisez cette appli à installer des applis quand Android le demande, puis installez Vixeo.</li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>4</span> Ouvrez Vixeo depuis la liste des applications de la TV.</li></ol></div>`);
			else _push(`<!---->`);
			_push(`<div data-v-25923853><h3 class="text-lg font-bold" data-v-25923853>Se connecter avec le téléphone</h3><ol class="mt-4 space-y-3 text-text-secondary" data-v-25923853><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>1</span> Sur la TV, l’écran de connexion affiche un QR code et un code court.</li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>2</span><span data-v-25923853>Scannez le QR code avec votre téléphone, ou ouvrez <a${(0, server_renderer_exports.ssrRenderAttr)("href", `${(0, vue_exports.unref)(site).appUrl}/tv`)} class="font-semibold text-accent hover:text-accent-hover" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(appHost))}/tv</a>.</span></li><li class="flex gap-3" data-v-25923853><span class="step" data-v-25923853>3</span> Connectez-vous si besoin, vérifiez le code, puis appuyez sur « Autoriser » : la TV se connecte toute seule.</li></ol><p class="mt-4 text-sm text-text-muted" data-v-25923853>Vous pouvez aussi vous connecter sur la TV avec votre adresse e-mail et votre mot de passe.</p></div></div></section><section id="web" class="rounded-3xl border border-line bg-surface/60 p-6 sm:p-10" aria-labelledby="titre-web" data-v-25923853><div class="flex items-center gap-3" data-v-25923853>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				name: "globe",
				size: 26,
				class: "text-accent"
			}, null, _parent));
			_push(`<h2 id="titre-web" class="text-2xl font-bold sm:text-3xl" data-v-25923853>Dans le navigateur</h2></div><p class="mt-4 leading-relaxed text-text-secondary" data-v-25923853> Rien à installer : Vixeo s’ouvre dans un navigateur récent (Chrome, Edge, Firefox ou Safari), sur ordinateur, tablette ou téléphone. Vous avez toujours la dernière version. </p>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, {
				screen: "web",
				class: "mt-7"
			}, null, _parent));
			_push(`<div class="mt-10 grid gap-6 sm:grid-cols-2" data-v-25923853><div class="flex gap-4" data-v-25923853>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				name: "keyboard",
				size: 24,
				class: "mt-0.5 text-accent"
			}, null, _parent));
			_push(`<p class="text-text-secondary" data-v-25923853><strong class="text-text" data-v-25923853>Sur ordinateur</strong>, naviguez à la souris ou au clavier : les flèches pour vous déplacer, Entrée pour choisir.</p></div><div class="flex gap-4" data-v-25923853>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				name: "phone",
				size: 24,
				class: "mt-0.5 text-accent"
			}, null, _parent));
			_push(`<p class="text-text-secondary" data-v-25923853><strong class="text-text" data-v-25923853>Sur téléphone ou tablette</strong>, ajoutez Vixeo à l’écran d’accueil depuis le menu du navigateur pour l’ouvrir comme une appli.</p></div></div></section><section class="flex flex-col items-start justify-between gap-6 rounded-3xl border border-line p-6 sm:flex-row sm:items-center sm:p-10" aria-label="Aide" data-v-25923853><div data-v-25923853><h2 class="text-xl font-bold" data-v-25923853>Besoin d’aide ?</h2><p class="mt-2 text-text-secondary" data-v-25923853> Les réponses aux questions les plus courantes sont dans la FAQ`);
			if ((0, vue_exports.unref)(site).contactEmail) _push(`<!--[-->, et vous pouvez nous écrire à <a${(0, server_renderer_exports.ssrRenderAttr)("href", `mailto:${(0, vue_exports.unref)(site).contactEmail}`)} class="font-semibold text-accent hover:text-accent-hover" data-v-25923853>${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(site).contactEmail)}</a><!--]-->`);
			else _push(`<!---->`);
			_push(`. </p></div>`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$2, {
				href: "/faq",
				variant: "outline"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Questions fréquentes`);
					else return [(0, vue_exports.createTextVNode)("Questions fréquentes")];
				}),
				_: 1
			}, _parent));
			_push(`</section></div><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Install.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Install_default = /*#__PURE__*/ _plugin_vue_export_helper_default(_sfc_main, [["__scopeId", "data-v-25923853"]]);
export { Install_default as default };
