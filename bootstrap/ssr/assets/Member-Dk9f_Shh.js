import { i as server_renderer_exports, s as vue_exports, t as _sfc_main$1 } from "../ssr.js";
import { t as _sfc_main$2 } from "./Seo-BAMcyPOF.js";
import { t as _sfc_main$3 } from "./PixelAvatar-DE-ftQk1.js";
// resources/js/pages/Member.vue
var _sfc_main = {
	__name: "Member",
	__ssrInlineRender: true,
	props: {
		member: {
			type: Object,
			required: true
		},
		openInApp: {
			type: String,
			default: null
		}
	},
	setup(__props) {
		const props = __props;
		const initial = (0, vue_exports.computed)(() => (props.member.name || props.member.username || "?").trim().charAt(0).toUpperCase());
		const pixelAvatar = (0, vue_exports.computed)(() => props.member.avatar?.pixels ? props.member.avatar : null);
		const description = (0, vue_exports.computed)(() => props.member.bio || `Le profil de ${props.member.name} (@${props.member.username}) sur Vixeo.`);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$2, {
				title: `${__props.member.name} (@${__props.member.username})`,
				description: description.value,
				noindex: ""
			}, null, _parent));
			_push(`<section class="mx-auto flex max-w-xl flex-col items-center px-5 pt-32 pb-24 text-center sm:px-8 sm:pt-40"><div class="size-32 overflow-hidden rounded-full bg-surface-high ring-4 ring-line sm:size-36">`);
			if (__props.member.photoUrl) _push(`<img${(0, server_renderer_exports.ssrRenderAttr)("src", __props.member.photoUrl)} alt="" class="size-full object-cover">`);
			else if (pixelAvatar.value) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, { avatar: pixelAvatar.value }, null, _parent));
			else if (__props.member.avatar?.imageUrl) _push(`<img${(0, server_renderer_exports.ssrRenderAttr)("src", __props.member.avatar.imageUrl)} alt="" class="size-full object-cover">`);
			else _push(`<span class="flex size-full items-center justify-center text-5xl font-extrabold text-text" aria-hidden="true">${(0, server_renderer_exports.ssrInterpolate)(initial.value)}</span>`);
			_push(`</div><h1 class="mt-6 text-3xl font-extrabold text-text sm:text-4xl">${(0, server_renderer_exports.ssrInterpolate)(__props.member.name)}</h1><p class="mt-1 text-lg text-text-muted"> @${(0, server_renderer_exports.ssrInterpolate)(__props.member.username)}`);
			if (__props.member.isPrivate) _push(`<span> · Compte privé</span>`);
			else _push(`<!---->`);
			_push(`</p>`);
			if (__props.member.bio) _push(`<p class="mt-5 max-w-md text-base leading-relaxed whitespace-pre-line text-text-secondary">${(0, server_renderer_exports.ssrInterpolate)(__props.member.bio)}</p>`);
			else _push(`<!---->`);
			if (__props.member.links?.length) {
				_push(`<ul class="mt-6 flex flex-wrap justify-center gap-2"><!--[-->`);
				(0, server_renderer_exports.ssrRenderList)(__props.member.links, (link) => {
					_push(`<li><a${(0, server_renderer_exports.ssrRenderAttr)("href", link.url)} rel="me noopener nofollow" target="_blank" class="inline-flex items-center gap-1.5 rounded-full border border-line px-4 py-2 text-sm font-medium text-text-secondary transition hover:border-accent hover:text-text"><span class="text-text">${(0, server_renderer_exports.ssrInterpolate)(link.label)}</span>`);
					if (link.label !== "Site web") _push(`<span class="text-text-muted">${(0, server_renderer_exports.ssrInterpolate)(link.handle)}</span>`);
					else _push(`<!---->`);
					_push(`</a></li>`);
				});
				_push(`<!--]--></ul>`);
			} else _push(`<!---->`);
			_push(`<div class="mt-10 flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-center">`);
			if (__props.openInApp) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				href: __props.openInApp,
				size: "lg",
				icon: "play"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Ouvrir dans Vixeo`);
					else return [(0, vue_exports.createTextVNode)("Ouvrir dans Vixeo")];
				}),
				_: 1
			}, _parent));
			else _push(`<!---->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, {
				href: "/#installer",
				variant: __props.openInApp ? "outline" : "primary",
				size: "lg",
				icon: "download"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Télécharger Vixeo `);
					else return [(0, vue_exports.createTextVNode)(" Télécharger Vixeo ")];
				}),
				_: 1
			}, _parent));
			_push(`</div><p class="mt-6 max-w-md text-sm text-text-muted">`);
			if (__props.member.isPrivate) _push(`<!--[--> Ce compte est privé : dans Vixeo, demandez à le suivre pour voir ses avis et sa liste. <!--]-->`);
			else _push(`<!--[--> Dans Vixeo, retrouvez ce profil, ses avis et sa liste : sur Android, ce lien l’ouvre directement dans l’appli. <!--]-->`);
			_push(`</p></section><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Member.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as default };
