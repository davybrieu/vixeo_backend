import { a as head_default, c as vue_exports, i as server_renderer_exports, s as usePage } from "../ssr.js";
// resources/js/components/Seo.vue
var _sfc_main = {
	__name: "Seo",
	__ssrInlineRender: true,
	props: {
		title: {
			type: String,
			default: null
		},
		description: {
			type: String,
			required: true
		}
	},
	setup(__props) {
		const props = __props;
		const page = usePage();
		const url = (0, vue_exports.computed)(() => page.props.site.url + (page.props.path === "/" ? "/" : page.props.path));
		const fullTitle = (0, vue_exports.computed)(() => props.title ? `${props.title} · Vixeo` : "Vixeo");
		return (_ctx, _push, _parent, _attrs) => {
			_push((0, server_renderer_exports.ssrRenderComponent)((0, vue_exports.unref)(head_default), (0, vue_exports.mergeProps)({ title: __props.title }, _attrs), {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<meta head-key="description" name="description"${(0, server_renderer_exports.ssrRenderAttr)("content", __props.description)}${_scopeId}><link head-key="canonical" rel="canonical"${(0, server_renderer_exports.ssrRenderAttr)("href", url.value)}${_scopeId}><meta head-key="og:title" property="og:title"${(0, server_renderer_exports.ssrRenderAttr)("content", fullTitle.value)}${_scopeId}><meta head-key="og:description" property="og:description"${(0, server_renderer_exports.ssrRenderAttr)("content", __props.description)}${_scopeId}><meta head-key="og:url" property="og:url"${(0, server_renderer_exports.ssrRenderAttr)("content", url.value)}${_scopeId}>`);
					else return [
						(0, vue_exports.createVNode)("meta", {
							"head-key": "description",
							name: "description",
							content: __props.description
						}, null, 8, ["content"]),
						(0, vue_exports.createVNode)("link", {
							"head-key": "canonical",
							rel: "canonical",
							href: url.value
						}, null, 8, ["href"]),
						(0, vue_exports.createVNode)("meta", {
							"head-key": "og:title",
							property: "og:title",
							content: fullTitle.value
						}, null, 8, ["content"]),
						(0, vue_exports.createVNode)("meta", {
							"head-key": "og:description",
							property: "og:description",
							content: __props.description
						}, null, 8, ["content"]),
						(0, vue_exports.createVNode)("meta", {
							"head-key": "og:url",
							property: "og:url",
							content: url.value
						}, null, 8, ["content"])
					];
				}),
				_: 1
			}, _parent));
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/Seo.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as t };
