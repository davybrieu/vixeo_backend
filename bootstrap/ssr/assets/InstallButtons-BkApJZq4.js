import { c as vue_exports, i as server_renderer_exports, s as usePage, t as _sfc_main$1 } from "../ssr.js";
// resources/js/components/InstallButtons.vue
var _sfc_main = {
	__name: "InstallButtons",
	__ssrInlineRender: true,
	props: {
		screen: {
			type: String,
			required: true
		},
		size: {
			type: String,
			default: "lg"
		}
	},
	setup(__props) {
		const site = usePage().props.site;
		const android = (0, vue_exports.computed)(() => site.android);
		const hasAndroid = (0, vue_exports.computed)(() => !!android.value.apkUrl);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "flex flex-wrap items-center gap-3" }, _attrs))}>`);
			if (__props.screen === "web") {
				_push(`<!--[-->`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
					href: (0, vue_exports.unref)(site).appUrl,
					icon: "globe",
					size: __props.size
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`Ouvrir dans le navigateur`);
						else return [(0, vue_exports.createTextVNode)("Ouvrir dans le navigateur")];
					}),
					_: 1
				}, _parent));
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
					href: `${(0, vue_exports.unref)(site).appUrl}/inscription`,
					variant: "outline",
					size: __props.size
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`Créer un compte`);
						else return [(0, vue_exports.createTextVNode)("Créer un compte")];
					}),
					_: 1
				}, _parent));
				_push(`<!--]-->`);
			} else if (hasAndroid.value) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				href: android.value.apkUrl,
				icon: "download",
				size: __props.size,
				download: ""
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Télécharger l’APK`);
					else return [(0, vue_exports.createTextVNode)("Télécharger l’APK")];
				}),
				_: 1
			}, _parent));
			else {
				_push(`<!--[--><span class="inline-flex h-11 items-center rounded-xl border border-dashed border-line-strong px-4 text-sm text-text-secondary">${(0, server_renderer_exports.ssrInterpolate)(__props.screen === "tv" ? "Version TV bientôt disponible" : "Version Android bientôt disponible")}</span>`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
					href: (0, vue_exports.unref)(site).appUrl,
					icon: "globe",
					variant: "outline",
					size: __props.size
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`En attendant, ouvrir dans le navigateur`);
						else return [(0, vue_exports.createTextVNode)("En attendant, ouvrir dans le navigateur")];
					}),
					_: 1
				}, _parent));
				_push(`<!--]-->`);
			}
			_push(`</div>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/InstallButtons.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as t };
