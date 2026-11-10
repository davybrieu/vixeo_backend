import { i as server_renderer_exports, o as usePage, s as vue_exports } from "../ssr.js";
import { t as _sfc_main$1 } from "./Seo-LbnFsiRl.js";
// resources/js/pages/Privacy.vue
var _sfc_main = {
	__name: "Privacy",
	__ssrInlineRender: true,
	setup(__props) {
		const site = usePage().props.site;
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				title: "Confidentialité du site",
				description: "Ce que le site Vixeo fait de vos données : aucun cookie, aucune mesure d’audience, aucun service tiers."
			}, null, _parent));
			_push(`<article class="mx-auto max-w-3xl space-y-10 px-5 pt-32 pb-24 leading-relaxed text-text-secondary sm:px-8 sm:pt-40"><h1 class="text-4xl font-extrabold text-text sm:text-5xl">Confidentialité du site</h1><section class="space-y-3" aria-labelledby="resume"><h2 id="resume" class="text-xl font-bold text-text">En bref</h2><p> Ce site ne dépose <strong class="text-text">aucun cookie</strong> et n’utilise ni mesure d’audience, ni publicité, ni service tiers : ses polices et ses images sont servies par ce site lui-même. </p></section><section class="space-y-3" aria-labelledby="journaux"><h2 id="journaux" class="text-xl font-bold text-text">Journaux du serveur</h2><p> Comme tout serveur web, celui qui héberge ce site enregistre des journaux techniques (adresse IP, page demandée, date, navigateur) pour sa sécurité et son bon fonctionnement. Ils sont conservés pendant une durée limitée et ne servent à rien d’autre. </p><p> Pour afficher la dernière version Android, notre serveur interroge GitHub, où chaque version de l’appli est publiée : votre navigateur n’y participe pas. </p></section><section class="space-y-3" aria-labelledby="liens"><h2 id="liens" class="text-xl font-bold text-text">Liens vers d’autres services</h2><p> Le bouton « Télécharger l’APK » télécharge l’appli depuis GitHub, où chaque version est publiée. « Ouvrir dans le navigateur » mène à l’appli Vixeo, décrite dans sa <a${(0, server_renderer_exports.ssrRenderAttr)("href", `${(0, vue_exports.unref)(site).appUrl}/confidentialite`)} class="font-semibold text-accent hover:text-accent-hover">politique de confidentialité</a>. </p></section><section class="space-y-3" aria-labelledby="droits"><h2 id="droits" class="text-xl font-bold text-text">Vos droits</h2><p> Vous pouvez demander l’accès à vos données ou leur effacement`);
			if ((0, vue_exports.unref)(site).contactEmail) _push(`<!--[--> en écrivant à <a${(0, server_renderer_exports.ssrRenderAttr)("href", `mailto:${(0, vue_exports.unref)(site).contactEmail}`)} class="font-semibold text-accent hover:text-accent-hover">${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(site).contactEmail)}</a><!--]-->`);
			else _push(`<!---->`);
			_push(`, et adresser une réclamation à la CNIL (cnil.fr). </p></section></article><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Privacy.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as default };
