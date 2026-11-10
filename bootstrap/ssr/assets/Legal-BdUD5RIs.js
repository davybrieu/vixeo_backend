import { c as vue_exports, i as server_renderer_exports, s as usePage } from "../ssr.js";
import { t as _sfc_main$1 } from "./Seo-5b7tehtx.js";
// resources/js/pages/Legal.vue
var _sfc_main = {
	__name: "Legal",
	__ssrInlineRender: true,
	props: {
		publisher: {
			type: Object,
			default: () => ({})
		},
		host: {
			type: Object,
			default: () => ({})
		}
	},
	setup(__props) {
		const site = usePage().props.site;
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				title: "Mentions légales",
				description: "Éditeur, hébergeur et informations légales du site Vixeo."
			}, null, _parent));
			_push(`<article class="mx-auto max-w-3xl px-5 pt-32 pb-24 sm:px-8 sm:pt-40"><h1 class="text-4xl font-extrabold sm:text-5xl">Mentions légales</h1><section class="mt-12 space-y-3" aria-labelledby="editeur"><h2 id="editeur" class="text-xl font-bold">Éditeur du site</h2><dl class="grid gap-x-6 gap-y-2 text-text-secondary sm:grid-cols-[12rem_1fr]">`);
			if (__props.publisher.name) _push(`<!--[--><dt class="text-text-muted">Nom</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.publisher.name)}</dd><!--]-->`);
			else _push(`<!---->`);
			if (__props.publisher.address) _push(`<!--[--><dt class="text-text-muted">Adresse</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.publisher.address)}</dd><!--]-->`);
			else _push(`<!---->`);
			if (__props.publisher.registration) _push(`<!--[--><dt class="text-text-muted">Immatriculation</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.publisher.registration)}</dd><!--]-->`);
			else _push(`<!---->`);
			if (__props.publisher.director) _push(`<!--[--><dt class="text-text-muted">Directeur de la publication</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.publisher.director)}</dd><!--]-->`);
			else _push(`<!---->`);
			if (__props.publisher.email) _push(`<!--[--><dt class="text-text-muted">Contact</dt><dd><a${(0, server_renderer_exports.ssrRenderAttr)("href", `mailto:${__props.publisher.email}`)} class="font-semibold text-accent hover:text-accent-hover">${(0, server_renderer_exports.ssrInterpolate)(__props.publisher.email)}</a></dd><!--]-->`);
			else _push(`<!---->`);
			_push(`</dl></section><section class="mt-10 space-y-3" aria-labelledby="hebergeur"><h2 id="hebergeur" class="text-xl font-bold">Hébergement</h2><dl class="grid gap-x-6 gap-y-2 text-text-secondary sm:grid-cols-[12rem_1fr]">`);
			if (__props.host.name) _push(`<!--[--><dt class="text-text-muted">Hébergeur</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.host.name)}</dd><!--]-->`);
			else _push(`<!---->`);
			if (__props.host.address) _push(`<!--[--><dt class="text-text-muted">Adresse</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.host.address)}</dd><!--]-->`);
			else _push(`<!---->`);
			if (__props.host.phone) _push(`<!--[--><dt class="text-text-muted">Téléphone</dt><dd>${(0, server_renderer_exports.ssrInterpolate)(__props.host.phone)}</dd><!--]-->`);
			else _push(`<!---->`);
			_push(`</dl></section><section class="mt-10 space-y-3 leading-relaxed text-text-secondary" aria-labelledby="propriete"><h2 id="propriete" class="text-xl font-bold text-text">Propriété intellectuelle</h2><p>Le nom Vixeo, son logo, les textes et les illustrations de ce site appartiennent à leur éditeur.</p><p> Les informations et images des films et séries affichées dans l’appli proviennent de TMDb (The Movie Database). This product uses the TMDB API but is not endorsed or certified by TMDB. </p><p>Polices Sora et Inter, sous licence SIL Open Font License (<a href="/fonts/OFL-Sora.txt" class="text-accent">Sora</a>, <a href="/fonts/OFL-Inter.txt" class="text-accent">Inter</a>).</p><p>Jellyfin, Plex, Emby, Android, Google TV et Google Play sont des marques de leurs propriétaires respectifs.</p></section><section class="mt-10 space-y-3 leading-relaxed text-text-secondary" aria-labelledby="donnees"><h2 id="donnees" class="text-xl font-bold text-text">Données personnelles</h2><p> Ce site ne dépose aucun cookie : voir la <a href="/confidentialite" class="font-semibold text-accent hover:text-accent-hover">confidentialité du site</a>. Pour l’appli, voir sa <a${(0, server_renderer_exports.ssrRenderAttr)("href", `${(0, vue_exports.unref)(site).appUrl}/confidentialite`)} class="font-semibold text-accent hover:text-accent-hover">politique de confidentialité</a>. </p></section></article><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Legal.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as default };
