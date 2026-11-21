import { i as server_renderer_exports, s as vue_exports } from "../ssr.js";
// resources/js/mock/PixelAvatar.vue
var _sfc_main = {
	__name: "PixelAvatar",
	__ssrInlineRender: true,
	props: { avatar: {
		type: Object,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<svg${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({
				viewBox: "0 0 12 12",
				class: "block size-full",
				"shape-rendering": "crispEdges",
				"aria-hidden": "true"
			}, _attrs))}><rect width="12" height="12"${(0, server_renderer_exports.ssrRenderAttr)("fill", __props.avatar.background)}></rect><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(__props.avatar.pixels, (row, y) => {
				_push(`<!--[--><!--[-->`);
				(0, server_renderer_exports.ssrRenderList)(row.split(""), (char, x) => {
					_push(`<!--[-->`);
					if (char !== ".") _push(`<rect${(0, server_renderer_exports.ssrRenderAttr)("x", x)}${(0, server_renderer_exports.ssrRenderAttr)("y", y)} width="1.02" height="1.02"${(0, server_renderer_exports.ssrRenderAttr)("fill", __props.avatar.palette[char])}></rect>`);
					else _push(`<!---->`);
					_push(`<!--]-->`);
				});
				_push(`<!--]--><!--]-->`);
			});
			_push(`<!--]--></svg>`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PixelAvatar.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as t };
