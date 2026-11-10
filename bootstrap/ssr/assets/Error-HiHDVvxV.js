import { c as vue_exports, i as server_renderer_exports, t as _sfc_main$1 } from "../ssr.js";
import { t as _sfc_main$2 } from "./Seo-5b7tehtx.js";
// resources/js/pages/Error.vue
var _sfc_main = {
	__name: "Error",
	__ssrInlineRender: true,
	props: { status: {
		type: Number,
		required: true
	} },
	setup(__props) {
		const props = __props;
		const messages = {
			403: ["Accès refusé", "Vous n’avez pas accès à cette page."],
			404: ["Page introuvable", "Cette page n’existe pas, ou plus."],
			405: ["Page introuvable", "Cette adresse ne s’ouvre pas de cette façon."],
			419: ["Page expirée", "Rechargez la page pour continuer."],
			429: ["Trop de requêtes", "Patientez un instant avant de réessayer."],
			500: ["Une erreur est survenue", "Le problème vient de nous. Réessayez dans quelques instants."],
			503: ["Maintenance en cours", "Le site revient dans quelques minutes."]
		};
		const message = (0, vue_exports.computed)(() => messages[props.status] ?? messages[500]);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$2, {
				title: message.value[0],
				description: message.value[1]
			}, null, _parent));
			_push(`<section class="mx-auto flex min-h-[70vh] max-w-xl flex-col items-center justify-center px-5 pt-28 pb-16 text-center"><p class="font-display text-7xl font-extrabold text-accent sm:text-8xl">${(0, server_renderer_exports.ssrInterpolate)(__props.status)}</p><h1 class="mt-6 text-3xl font-bold">${(0, server_renderer_exports.ssrInterpolate)(message.value[0])}</h1><p class="mt-4 text-text-secondary">${(0, server_renderer_exports.ssrInterpolate)(message.value[1])}</p><div class="mt-9 flex flex-wrap justify-center gap-3">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, { href: "/" }, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Retour à l’accueil`);
					else return [(0, vue_exports.createTextVNode)("Retour à l’accueil")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				href: "/installer",
				variant: "outline"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Installer Vixeo`);
					else return [(0, vue_exports.createTextVNode)("Installer Vixeo")];
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
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Error.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as default };
