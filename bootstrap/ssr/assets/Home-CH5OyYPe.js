import { i as server_renderer_exports, n as _sfc_main$17, o as usePage, r as _sfc_main$18, s as vue_exports, t as _sfc_main$19 } from "../ssr.js";
import { t as _sfc_main$20 } from "./Seo-LbnFsiRl.js";
// resources/js/components/FaqList.vue
var _sfc_main$16 = {
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
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
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
var _sfc_setup$16 = _sfc_main$16.setup;
_sfc_main$16.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/FaqList.vue");
	return _sfc_setup$16 ? _sfc_setup$16(props, ctx) : void 0;
};
// resources/js/components/FeatureRow.vue
var _sfc_main$15 = {
	__name: "FeatureRow",
	__ssrInlineRender: true,
	props: {
		title: {
			type: String,
			required: true
		},
		icon: {
			type: String,
			required: true
		},
		reverse: {
			type: Boolean,
			default: false
		},
		points: {
			type: Array,
			default: () => []
		}
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "grid items-center gap-10 lg:grid-cols-2 lg:gap-20" }, _attrs))}><div class="${(0, server_renderer_exports.ssrRenderClass)(__props.reverse ? "lg:order-2" : "")}"><span class="grid size-12 place-items-center rounded-2xl bg-accent/12 text-accent">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
				name: __props.icon,
				size: 24
			}, null, _parent));
			_push(`</span><h3 class="mt-5 text-2xl font-bold sm:text-3xl">${(0, server_renderer_exports.ssrInterpolate)(__props.title)}</h3><p class="mt-4 text-base leading-relaxed text-text-secondary sm:text-lg">`);
			(0, server_renderer_exports.ssrRenderSlot)(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</p>`);
			if (__props.points.length) {
				_push(`<ul class="mt-6 space-y-3"><!--[-->`);
				(0, server_renderer_exports.ssrRenderList)(__props.points, (point) => {
					_push(`<li class="flex gap-3 text-text-soft">`);
					_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
						name: "check",
						size: 20,
						class: "mt-0.5 text-accent"
					}, null, _parent));
					_push(`<span>${(0, server_renderer_exports.ssrInterpolate)(point)}</span></li>`);
				});
				_push(`<!--]--></ul>`);
			} else _push(`<!---->`);
			_push(`</div><div class="${(0, server_renderer_exports.ssrRenderClass)(__props.reverse ? "lg:order-1" : "")}">`);
			(0, server_renderer_exports.ssrRenderSlot)(_ctx.$slots, "visual", {}, null, _push, _parent);
			_push(`</div></div>`);
		};
	}
};
var _sfc_setup$15 = _sfc_main$15.setup;
_sfc_main$15.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/FeatureRow.vue");
	return _sfc_setup$15 ? _sfc_setup$15(props, ctx) : void 0;
};
// resources/js/mock/PhoneFrame.vue
var _sfc_main$14 = {
	__name: "PhoneFrame",
	__ssrInlineRender: true,
	props: { label: {
		type: String,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<figure${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({
				class: "relative",
				role: "img",
				"aria-label": __props.label
			}, _attrs))}><div class="rounded-[14cqw] border border-white/12 bg-[#0c0f0d] p-[3.2%] shadow-[0_30px_80px_-20px_rgba(0,0,0,0.95)] @container"><div class="relative aspect-[9/19.5] overflow-hidden rounded-[11cqw] bg-bg" aria-hidden="true"><div class="absolute top-[1.6%] left-1/2 z-20 h-[2.6%] w-[28%] -translate-x-1/2 rounded-full bg-black"></div>`);
			(0, server_renderer_exports.ssrRenderSlot)(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</div></div></figure>`);
		};
	}
};
var _sfc_setup$14 = _sfc_main$14.setup;
_sfc_main$14.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PhoneFrame.vue");
	return _sfc_setup$14 ? _sfc_setup$14(props, ctx) : void 0;
};
// resources/js/mock/PosterTile.vue
var _sfc_main$13 = {
	__name: "PosterTile",
	__ssrInlineRender: true,
	props: {
		seed: {
			type: Number,
			default: 0
		},
		wide: {
			type: Boolean,
			default: false
		},
		focused: {
			type: Boolean,
			default: false
		},
		progress: {
			type: Number,
			default: null
		}
	},
	setup(__props) {
		const props = __props;
		const tones = [
			"#1b2a22",
			"#22261f",
			"#1a2230",
			"#2a2026",
			"#14221e",
			"#2b2a1e",
			"#1f1b2a",
			"#18261f"
		];
		const glows = [
			"46,229,138",
			"96,140,255",
			"255,140,90",
			"200,110,255",
			"255,205,90",
			"90,210,230"
		];
		const style = (0, vue_exports.computed)(() => {
			const tone = tones[props.seed % tones.length];
			const glow = glows[(props.seed * 7 + 3) % glows.length];
			return { background: `radial-gradient(circle at ${20 + props.seed * 37 % 60}% 28%, rgba(${glow},0.38), transparent 52%), linear-gradient(165deg, ${tone}, #070a08 125%)` };
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({
				class: [
					"relative shrink-0 overflow-hidden rounded-[0.6cqw] transition",
					__props.wide ? "aspect-video" : "aspect-[2/3]",
					__props.focused ? "z-10 scale-[1.08] ring-[0.35cqw] ring-accent" : ""
				],
				style: style.value
			}, _attrs))}><div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/45 to-transparent"></div>`);
			if (__props.progress !== null) _push(`<div class="absolute inset-x-[6%] bottom-[8%] h-[4%] min-h-[2px] rounded-full bg-white/25"><div class="h-full rounded-full bg-accent" style="${(0, server_renderer_exports.ssrRenderStyle)({ width: `${__props.progress}%` })}"></div></div>`);
			else _push(`<!---->`);
			_push(`</div>`);
		};
	}
};
var _sfc_setup$13 = _sfc_main$13.setup;
_sfc_main$13.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PosterTile.vue");
	return _sfc_setup$13 ? _sfc_setup$13(props, ctx) : void 0;
};
// resources/js/mock/PhoneHomeScreen.vue
var _sfc_main$12 = {
	__name: "PhoneHomeScreen",
	__ssrInlineRender: true,
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0 flex flex-col pt-[12cqw]" }, _attrs))}><div class="px-[6cqw]">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$18, { size: "h-[6.3cqw]" }, null, _parent));
			_push(`</div><div class="relative mx-[5cqw] mt-[4cqw] aspect-[4/5] overflow-hidden rounded-[5cqw]" style="${(0, server_renderer_exports.ssrRenderStyle)({ "background": "radial-gradient(circle at 60% 30%, rgba(46,229,138,0.4), transparent 55%), linear-gradient(170deg, #1b2a22, #070a08 110%)" })}"><div class="absolute inset-x-[6cqw] bottom-[6cqw]"><div class="h-[6cqw] w-[60%] rounded-[1.4cqw] bg-white/90"></div><div class="mt-[2.4cqw] h-[2.4cqw] w-[42%] rounded bg-white/40"></div><div class="mt-[4cqw] flex gap-[2.4cqw]"><span class="flex h-[9cqw] flex-1 items-center justify-center rounded-[2.4cqw] bg-accent text-[3.6cqw] font-semibold text-on-accent">▶ Lecture</span><span class="size-[9cqw] rounded-full bg-white/15"></span></div></div></div><div class="mt-[6cqw] px-[6cqw] text-[3.8cqw] font-semibold">Continuer à regarder</div><div class="mt-[2.4cqw] flex gap-[2.6cqw] pl-[6cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(3, (i) => {
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$13, {
					key: i,
					seed: i + 4,
					wide: "",
					progress: [
						60,
						25,
						85
					][i - 1],
					class: "w-[40cqw]"
				}, null, _parent));
			});
			_push(`<!--]--></div><div class="mt-[5cqw] px-[6cqw] text-[3.8cqw] font-semibold">Films tendance</div><div class="mt-[2.4cqw] flex gap-[2.6cqw] pl-[6cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(4, (i) => {
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$13, {
					key: i,
					seed: i * 2 + 1,
					class: "w-[25cqw]"
				}, null, _parent));
			});
			_push(`<!--]--></div><div class="absolute inset-x-0 bottom-0 flex justify-around border-t border-white/8 bg-bg/95 px-[4cqw] pt-[3cqw] pb-[7cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(4, (i) => {
				_push(`<span class="${(0, server_renderer_exports.ssrRenderClass)(["h-[5cqw] w-[11cqw] rounded-full", i === 1 ? "bg-accent/25" : "bg-white/10"])}"></span>`);
			});
			_push(`<!--]--></div></div>`);
		};
	}
};
var _sfc_setup$12 = _sfc_main$12.setup;
_sfc_main$12.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PhoneHomeScreen.vue");
	return _sfc_setup$12 ? _sfc_setup$12(props, ctx) : void 0;
};
// resources/js/mock/TvCodeScreen.vue
var size = 21;
var _sfc_main$11 = {
	__name: "TvCodeScreen",
	__ssrInlineRender: true,
	setup(__props) {
		const cells = [];
		let s = 7;
		for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
			const finder = (cx, cy) => x >= cx && x < cx + 7 && y >= cy && y < cy + 7;
			if (finder(0, 0) || finder(14, 0) || finder(0, 14)) {
				const lx = x % 7 === x ? x : x - 14;
				const ly = y < 7 ? y : y - 14;
				if (lx === 0 || lx === 6 || ly === 0 || ly === 6 || lx >= 2 && lx <= 4 && ly >= 2 && ly <= 4) cells.push([x, y]);
				continue;
			}
			s = s * 1103515245 + 12345 & 2147483647;
			if (s % 100 < 46) cells.push([x, y]);
		}
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0 flex items-center justify-center gap-[3cqw] bg-[radial-gradient(circle_at_20%_100%,rgba(46,229,138,0.10),transparent_55%)] px-[5cqw]" }, _attrs))}><div class="w-[46cqw] rounded-[2cqw] border border-white/10 bg-surface p-[2.6cqw]"><span class="rounded-[0.5cqw] bg-accent px-[0.8cqw] py-[0.2cqw] text-[1.1cqw] font-semibold text-on-accent">Le plus simple</span><div class="mt-[1.4cqw] font-display text-[2.4cqw] font-bold">Avec votre téléphone</div><div class="mt-[2cqw] flex gap-[2.4cqw]"><div class="size-[15cqw] shrink-0 rounded-[1cqw] bg-text p-[0.9cqw]"><svg viewBox="0 0 21 21" class="size-full" shape-rendering="crispEdges"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(cells, ([x, y], i) => {
				_push(`<rect${(0, server_renderer_exports.ssrRenderAttr)("x", x)}${(0, server_renderer_exports.ssrRenderAttr)("y", y)} width="1" height="1" fill="#070a08"></rect>`);
			});
			_push(`<!--]--></svg></div><div class="text-[1.35cqw] leading-snug text-text-soft"><div>1. Scannez le code</div><div class="mt-[1cqw]">2. Saisissez ce code :</div><div class="mt-[0.8cqw] font-display text-[3.6cqw] font-extrabold tracking-[0.3cqw] text-accent">K7F-29Q</div><div class="mt-[0.8cqw] text-[1.05cqw] text-text-muted">Un nouveau code toutes les 10 minutes.</div></div></div></div><div class="w-[30cqw] rounded-[2cqw] border border-white/10 bg-surface p-[2.6cqw]"><div class="font-display text-[2.2cqw] font-bold">Connectez-vous</div><div class="mt-[2cqw] h-[3.4cqw] rounded-[0.8cqw] border border-white/10 bg-field"></div><div class="mt-[1.2cqw] h-[3.4cqw] rounded-[0.8cqw] border border-white/10 bg-field"></div><div class="mt-[2cqw] grid h-[3.8cqw] place-items-center rounded-[0.8cqw] bg-accent text-[1.4cqw] font-semibold text-on-accent"> Se connecter </div></div></div>`);
		};
	}
};
var _sfc_setup$11 = _sfc_main$11.setup;
_sfc_main$11.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/TvCodeScreen.vue");
	return _sfc_setup$11 ? _sfc_setup$11(props, ctx) : void 0;
};
// resources/js/mock/TvFrame.vue
var _sfc_main$10 = {
	__name: "TvFrame",
	__ssrInlineRender: true,
	props: { label: {
		type: String,
		required: true
	} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<figure${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({
				class: "relative",
				role: "img",
				"aria-label": __props.label
			}, _attrs))}><div class="rounded-[1.6cqw] border border-white/10 bg-[#0c0f0d] p-[0.9%] shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)] @container"><div class="relative aspect-video overflow-hidden rounded-[1cqw] bg-bg" aria-hidden="true">`);
			(0, server_renderer_exports.ssrRenderSlot)(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</div></div><div class="mx-auto h-[1.2cqw] w-[18%] rounded-b-[1cqw] bg-gradient-to-b from-[#151a17] to-[#0b0e0c]" aria-hidden="true"></div></figure>`);
		};
	}
};
var _sfc_setup$10 = _sfc_main$10.setup;
_sfc_main$10.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/TvFrame.vue");
	return _sfc_setup$10 ? _sfc_setup$10(props, ctx) : void 0;
};
// resources/js/mock/TvHomeScreen.vue
var _sfc_main$9 = {
	__name: "TvHomeScreen",
	__ssrInlineRender: true,
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0 flex" }, _attrs))}><div class="flex w-[6.5cqw] flex-col items-center gap-[2.6cqw] border-r border-white/5 pt-[3cqw]">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$18, {
				mark: "",
				size: "h-[1.6cqw]"
			}, null, _parent));
			_push(`<!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(5, (i) => {
				_push(`<span class="${(0, server_renderer_exports.ssrRenderClass)(["size-[1.8cqw] rounded-[0.5cqw]", i === 1 ? "bg-accent" : "bg-white/15"])}"></span>`);
			});
			_push(`<!--]--></div><div class="relative flex-1"><div class="absolute inset-x-0 top-0 h-[60%]" style="${(0, server_renderer_exports.ssrRenderStyle)({ "background": "radial-gradient(circle at 72% 30%, rgba(96,140,255,0.45), transparent 55%), linear-gradient(120deg, #1a2230 10%, #070a08 75%)" })}"></div><div class="absolute inset-x-0 top-[30%] h-[30%] bg-gradient-to-b from-transparent to-bg"></div><div class="relative px-[3.5cqw] pt-[6cqw]"><div class="h-[3.2cqw] w-[26cqw] rounded-[0.5cqw] bg-white/90"></div><div class="mt-[1.2cqw] h-[1.1cqw] w-[18cqw] rounded bg-white/35"></div><div class="mt-[0.7cqw] h-[1.1cqw] w-[30cqw] rounded bg-white/20"></div><div class="mt-[2cqw] flex items-center gap-[1cqw]"><span class="flex h-[3.2cqw] items-center gap-[0.6cqw] rounded-[0.8cqw] bg-accent px-[1.6cqw] text-[1.3cqw] font-semibold text-on-accent"> ▶ Lecture </span><span class="size-[3.2cqw] rounded-full bg-white/12"></span><span class="size-[3.2cqw] rounded-full bg-white/12"></span></div></div><div class="absolute inset-x-0 bottom-[3cqw] space-y-[1.2cqw] px-[3.5cqw]"><div class="text-[1.35cqw] font-semibold text-text-soft">Continuer à regarder</div><div class="flex gap-[1.2cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(5, (i) => {
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$13, {
					key: i,
					seed: i + 2,
					wide: "",
					focused: i === 2,
					progress: [
						35,
						70,
						15,
						55,
						80
					][i - 1],
					class: "w-[15.5cqw]"
				}, null, _parent));
			});
			_push(`<!--]--></div></div></div></div>`);
		};
	}
};
var _sfc_setup$9 = _sfc_main$9.setup;
_sfc_main$9.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/TvHomeScreen.vue");
	return _sfc_setup$9 ? _sfc_setup$9(props, ctx) : void 0;
};
// resources/js/components/InstallButtons.vue
var _sfc_main$8 = {
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
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
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
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
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
			} else if (hasAndroid.value) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
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
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
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
var _sfc_setup$8 = _sfc_main$8.setup;
_sfc_main$8.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/InstallButtons.vue");
	return _sfc_setup$8 ? _sfc_setup$8(props, ctx) : void 0;
};
// resources/js/components/PlatformPicker.vue
var _sfc_main$7 = {
	__name: "PlatformPicker",
	__ssrInlineRender: true,
	props: { latestAndroid: {
		type: Object,
		default: null
	} },
	setup(__props) {
		const page = usePage();
		const screens = [
			{
				id: "android",
				label: "Téléphone et tablette",
				short: "Android",
				icon: "phone"
			},
			{
				id: "tv",
				label: "TV",
				short: "TV",
				icon: "tv"
			},
			{
				id: "web",
				label: "Navigateur",
				short: "Web",
				icon: "globe"
			}
		];
		const selected = (0, vue_exports.ref)(screens.some((s) => s.id === page.props.platform) ? page.props.platform : "web");
		(0, vue_exports.ref)([]);
		const minimum = (0, vue_exports.computed)(() => page.props.site.android.minimum);
		const android = (0, vue_exports.computed)(() => page.props.site.android);
		const apkShort = (0, vue_exports.computed)(() => `${page.props.site.url.replace(/^https?:\/\//, "")}/apk`);
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)(_attrs)}><div role="tablist" aria-label="Votre écran" class="inline-flex max-w-full gap-1 overflow-x-auto rounded-2xl border border-line bg-surface p-1.5"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(screens, (screen, i) => {
				_push(`<button${(0, server_renderer_exports.ssrRenderAttr)("id", `onglet-${screen.id}`)} type="button" role="tab"${(0, server_renderer_exports.ssrRenderAttr)("aria-selected", selected.value === screen.id)}${(0, server_renderer_exports.ssrRenderAttr)("aria-controls", `panneau-${screen.id}`)}${(0, server_renderer_exports.ssrRenderAttr)("tabindex", selected.value === screen.id ? 0 : -1)} class="${(0, server_renderer_exports.ssrRenderClass)(["inline-flex h-11 items-center gap-2 rounded-xl px-4 text-sm font-semibold whitespace-nowrap transition", selected.value === screen.id ? "bg-accent text-on-accent" : "text-text-secondary hover:bg-white/5 hover:text-text"])}">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
					name: screen.icon,
					size: 18
				}, null, _parent));
				_push(`<span class="sm:hidden">${(0, server_renderer_exports.ssrInterpolate)(screen.short)}</span><span class="hidden sm:inline">${(0, server_renderer_exports.ssrInterpolate)(screen.label)}</span>`);
				if ((0, vue_exports.unref)(page).props.platform === screen.id) _push(`<span class="sr-only">(votre appareil)</span>`);
				else _push(`<!---->`);
				_push(`</button>`);
			});
			_push(`<!--]--></div><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(screens, (screen) => {
				_push(`<div${(0, server_renderer_exports.ssrRenderAttr)("id", `panneau-${screen.id}`)} role="tabpanel"${(0, server_renderer_exports.ssrRenderAttr)("aria-labelledby", `onglet-${screen.id}`)} class="mt-6 grid items-center gap-10 overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-surface to-bg p-6 sm:p-10 lg:grid-cols-[1.05fr_1fr] lg:gap-14" style="${(0, server_renderer_exports.ssrRenderStyle)(selected.value === screen.id ? null : { display: "none" })}"><div>`);
				if ((0, vue_exports.unref)(page).props.platform === screen.id) {
					_push(`<p class="mb-4 inline-flex items-center gap-2 rounded-full bg-accent/12 px-3 py-1 text-xs font-semibold text-accent">`);
					_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
						name: "check",
						size: 14
					}, null, _parent));
					_push(` Votre appareil </p>`);
				} else _push(`<!---->`);
				if (screen.id === "android") _push(`<!--[--><h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour Android</h3><p class="mt-4 leading-relaxed text-text-secondary"> Parcourez le catalogue, tenez votre liste à jour et reprenez où vous en étiez, sur téléphone comme sur tablette. Compatible ${(0, server_renderer_exports.ssrInterpolate)(minimum.value)} et versions plus récentes. </p><!--]-->`);
				else if (screen.id === "tv") _push(`<!--[--><h3 class="text-2xl font-bold sm:text-3xl">Vixeo pour Android TV et Google TV</h3><p class="mt-4 leading-relaxed text-text-secondary"> Une interface pensée pour la télécommande, avec de grandes images et un repère vert sur l’élément choisi. Pour vous connecter, scannez le QR code de la TV avec votre téléphone : aucun mot de passe à taper. </p><!--]-->`);
				else _push(`<!--[--><h3 class="text-2xl font-bold sm:text-3xl">Vixeo dans le navigateur</h3><p class="mt-4 leading-relaxed text-text-secondary"> Rien à installer : ouvrez Vixeo dans Chrome, Edge, Firefox ou Safari, sur ordinateur, tablette ou téléphone. Au clavier, les flèches et la touche Entrée suffisent, comme une télécommande. </p><!--]-->`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$8, {
					screen: screen.id,
					class: "mt-7"
				}, null, _parent));
				if (screen.id === "android" && android.value.apkUrl) {
					_push(`<p class="mt-4 text-sm leading-relaxed text-text-muted"> La première fois, Android demande d’autoriser votre navigateur à installer des applis : acceptez, puis appuyez sur « Installer ». `);
					if (android.value.apkSha256) _push(`<span class="mt-2 block break-all"> Empreinte SHA-256 : <code class="rounded bg-bg px-1.5 py-0.5 font-mono text-xs text-text-soft">${(0, server_renderer_exports.ssrInterpolate)(android.value.apkSha256)}</code></span>`);
					else _push(`<!---->`);
					_push(`</p>`);
				} else _push(`<!---->`);
				if (screen.id === "tv" && android.value.apkUrl) _push(`<p class="mt-4 text-sm leading-relaxed text-text-muted"> Sur la TV, dans une appli qui télécharge des fichiers (un navigateur par exemple), ouvrez <strong class="text-text">${(0, server_renderer_exports.ssrInterpolate)(apkShort.value)}</strong>, puis installez Vixeo. </p>`);
				else _push(`<!---->`);
				if (screen.id !== "web" && __props.latestAndroid) _push(`<p class="mt-4 text-sm text-text-muted"> Dernière version : ${(0, server_renderer_exports.ssrInterpolate)(__props.latestAndroid.version)}</p>`);
				else _push(`<!---->`);
				_push(`</div><div class="${(0, server_renderer_exports.ssrRenderClass)([screen.id === "android" ? "max-w-[15rem]" : "max-w-xl", "mx-auto w-full"])}">`);
				if (screen.id === "android") _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$14, { label: "L’accueil de Vixeo sur un téléphone Android" }, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$12, null, null, _parent, _scopeId));
						else return [(0, vue_exports.createVNode)(_sfc_main$12)];
					}),
					_: 2
				}, _parent));
				else if (screen.id === "tv") _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$10, { label: "La connexion de la TV par QR code" }, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$11, null, null, _parent, _scopeId));
						else return [(0, vue_exports.createVNode)(_sfc_main$11)];
					}),
					_: 2
				}, _parent));
				else _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$10, { label: "L’accueil de Vixeo dans le navigateur, sur un ordinateur" }, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$9, null, null, _parent, _scopeId));
						else return [(0, vue_exports.createVNode)(_sfc_main$9)];
					}),
					_: 2
				}, _parent));
				_push(`</div></div>`);
			});
			_push(`<!--]--></div>`);
		};
	}
};
var _sfc_setup$7 = _sfc_main$7.setup;
_sfc_main$7.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/PlatformPicker.vue");
	return _sfc_setup$7 ? _sfc_setup$7(props, ctx) : void 0;
};
// resources/js/components/SectionHeading.vue
var _sfc_main$6 = {
	__name: "SectionHeading",
	__ssrInlineRender: true,
	props: {
		eyebrow: {
			type: String,
			default: null
		},
		title: {
			type: String,
			required: true
		},
		centered: {
			type: Boolean,
			default: false
		},
		id: {
			type: String,
			default: null
		}
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: ["max-w-2xl", __props.centered ? "mx-auto text-center" : ""] }, _attrs))}>`);
			if (__props.eyebrow) _push(`<p class="mb-3 text-sm font-semibold tracking-wide text-accent uppercase">${(0, server_renderer_exports.ssrInterpolate)(__props.eyebrow)}</p>`);
			else _push(`<!---->`);
			_push(`<h2${(0, server_renderer_exports.ssrRenderAttr)("id", __props.id)} class="text-3xl leading-tight font-bold sm:text-4xl lg:text-5xl">${(0, server_renderer_exports.ssrInterpolate)(__props.title)}</h2>`);
			if (_ctx.$slots.default) {
				_push(`<p class="mt-4 text-base leading-relaxed text-text-secondary sm:text-lg">`);
				(0, server_renderer_exports.ssrRenderSlot)(_ctx.$slots, "default", {}, null, _push, _parent);
				_push(`</p>`);
			} else _push(`<!---->`);
			_push(`</div>`);
		};
	}
};
var _sfc_setup$6 = _sfc_main$6.setup;
_sfc_main$6.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/components/SectionHeading.vue");
	return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
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
	const apkShort = `${site.url.replace(/^https?:\/\//, "")}/apk`;
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
			answer: ["Non. Vixeo n’héberge, ne stocke et ne diffuse aucun film ni aucune série. Les informations et les images des titres viennent de TMDb."]
		},
		{
			id: "appareils",
			question: "Sur quels appareils puis-je utiliser Vixeo ?",
			answer: [`Sur les téléphones et tablettes Android (${escape(site.android.minimum)} et plus récents), sur les téléviseurs et boîtiers Android TV et Google TV, et dans un navigateur récent (Chrome, Edge, Firefox ou Safari) : ${link(app, "ouvrir Vixeo")}.`, "Sur iPhone et iPad, utilisez Vixeo dans Safari."]
		},
		{
			id: "play-store",
			question: "Vixeo est-il sur le Play Store ?",
			answer: [
				`Non. Sur Android (téléphone, tablette et TV), Vixeo s’installe uniquement depuis ce site, avec un fichier APK : ${link("#installer", "télécharger l’APK")}. Ne l’installez depuis aucune autre source.`,
				"Le fichier est signé : Android vérifie que chaque mise à jour vient bien de nous. Son empreinte SHA-256 est indiquée sous le bouton de téléchargement.",
				"La première fois, Android demande d’autoriser votre navigateur à installer des applis (Paramètres, Applications, Accès spécial des applications, Installer des applis inconnues) : acceptez, puis appuyez sur « Installer »."
			]
		},
		{
			id: "installer-tv",
			question: "Comment installer Vixeo sur ma TV ?",
			answer: [`Sur la TV, installez depuis Google Play une appli qui télécharge des fichiers (un navigateur ou une appli de téléchargement). Dans cette appli, ouvrez l’adresse <strong>${escape(apkShort)}</strong> : le fichier de Vixeo se télécharge. Autorisez cette appli à installer des applis quand Android le demande, puis installez Vixeo.`]
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
			answer: ["Sur Android, Vixeo vous prévient quand une nouvelle version est disponible : il télécharge le fichier, le vérifie, puis Android vous demande de confirmer l’installation. Dans le navigateur, vous avez toujours la dernière version."]
		},
		{
			id: "cookies",
			question: "Ce site utilise-t-il des cookies ?",
			answer: [`Non : aucun cookie, aucune mesure d’audience, aucun service tiers. ${link("/confidentialite", "Confidentialité du site")}.${contact}`]
		}
	];
}
// resources/js/mock/PhoneListScreen.vue
var _sfc_main$5 = {
	__name: "PhoneListScreen",
	__ssrInlineRender: true,
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0 flex flex-col px-[6cqw] pt-[14cqw]" }, _attrs))}><div class="font-display text-[7.5cqw] font-bold">Ma liste</div><div class="mt-[2cqw] flex gap-[2cqw] text-[3.2cqw]"><span class="rounded-full bg-accent px-[3cqw] py-[1cqw] font-semibold text-on-accent">Tout</span><span class="rounded-full bg-white/10 px-[3cqw] py-[1cqw]">Films</span><span class="rounded-full bg-white/10 px-[3cqw] py-[1cqw]">Séries</span></div><div class="mt-[5cqw] grid grid-cols-3 gap-[3cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(9, (i) => {
				_push(`<div class="relative">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$13, { seed: i * 5 + 1 }, null, _parent));
				if ([2, 5].includes(i)) _push(`<span class="absolute top-[4%] right-[6%] grid size-[6cqw] place-items-center rounded-full bg-accent text-[3.4cqw] font-bold text-on-accent">✓</span>`);
				else _push(`<!---->`);
				_push(`</div>`);
			});
			_push(`<!--]--></div></div>`);
		};
	}
};
var _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PhoneListScreen.vue");
	return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
// resources/js/data/avatars.js
var avatars_default = [
	{
		"id": "quest-knight",
		"name": "Chevalier",
		"background": "#1E2A3A",
		"palette": {
			"R": "#E5484D",
			"S": "#B8C2CC",
			"D": "#4A5560",
			"Y": "#F2C94C",
			"B": "#2F5DA8"
		},
		"pixels": [
			"......RR....",
			".....RR.....",
			"...SSSSSS...",
			"..SSSSSSSS..",
			"..SDDDDDDS..",
			"..SD.YY.DS..",
			"..SDDDDDDS..",
			"..SSSSSSSS..",
			"...SSSSSS...",
			"..BBBBBBBB..",
			".BBBBYYBBBB.",
			".BBBBBBBBBB."
		]
	},
	{
		"id": "galaxy-astronaut",
		"name": "Astronaute",
		"background": "#141C2E",
		"palette": {
			"W": "#E9EDF2",
			"V": "#1F3A5F",
			"L": "#6FA8DC",
			"O": "#F97316"
		},
		"pixels": [
			"...WWWWWW...",
			"..WWWWWWWW..",
			".WWVVVVVVWW.",
			".WVVVVVVVVW.",
			".WVVLVVVVVW.",
			".WVVVVVVVVW.",
			".WWVVVVVVWW.",
			"..WWWWWWWW..",
			".WWWWOOWWWW.",
			"WWWWWWWWWWWW",
			"WW.WWWWWW.WW",
			"...WW..WW..."
		]
	},
	{
		"id": "monsters-slime",
		"name": "Gelée",
		"background": "#1C2614",
		"palette": {
			"G": "#A3E635",
			"L": "#ECFCCB",
			"K": "#1A2E05",
			"M": "#4D7C0F"
		},
		"pixels": [
			"............",
			"............",
			".....GG.....",
			"....GGGG....",
			"...GGLGGG...",
			"..GGLGGGGG..",
			".GGGKGGKGGG.",
			".GGGKGGKGGG.",
			"GGGGGGGGGGGG",
			"GGGGGMMGGGGG",
			"GGGGGGGGGGGG",
			".GGGGGGGGGG."
		]
	},
	{
		"id": "arcade-cat",
		"name": "Chat",
		"background": "#2A2112",
		"palette": {
			"K": "#F59E0B",
			"G": "#14532D",
			"P": "#FB7185",
			"W": "#FDE68A"
		},
		"pixels": [
			".K........K.",
			".KK......KK.",
			".KKK....KKK.",
			".KKKKKKKKKK.",
			".KKGKKKKGKK.",
			".KKGKKKKGKK.",
			".KKKKPPKKKK.",
			"WKKKKKKKKKKW",
			".KKKKKKKKKK.",
			"WKKKKKKKKKKW",
			"..KKKKKKKK..",
			"...KKKKKK..."
		]
	},
	{
		"id": "galaxy-robot",
		"name": "Robot",
		"background": "#1B2129",
		"palette": {
			"Y": "#FACC15",
			"S": "#9AA5B1",
			"D": "#2B3440",
			"C": "#22D3EE",
			"M": "#64748B",
			"R": "#EF4444"
		},
		"pixels": [
			".....YY.....",
			".....SS.....",
			"..SSSSSSSS..",
			"..SDDDDDDS..",
			"..SDCDDCDS..",
			"..SDDDDDDS..",
			"..SDMMMMDS..",
			"..SSSSSSSS..",
			"...SSSSSS...",
			".SSSSRRSSSS.",
			".S.SSSSSS.S.",
			"...SS..SS..."
		]
	},
	{
		"id": "quest-wizard",
		"name": "Magicien",
		"background": "#221A33",
		"palette": {
			"P": "#7B4BC4",
			"Y": "#F2C94C",
			"K": "#F0C29A",
			"E": "#2B1B12",
			"W": "#EDEDED"
		},
		"pixels": [
			".....PP.....",
			"....PPPP....",
			"....PYPP....",
			"...PPPPPP...",
			"..PPPPPPPP..",
			".PPPPPPPPPP.",
			"...KKKKKK...",
			"...KEKKEK...",
			"...KKKKKK...",
			"...WWWWWW...",
			"....WWWW....",
			".....WW....."
		]
	}
];
// resources/js/mock/PixelAvatar.vue
var _sfc_main$4 = {
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
var _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PixelAvatar.vue");
	return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
// resources/js/mock/PhoneParentalScreen.vue
var _sfc_main$3 = {
	__name: "PhoneParentalScreen",
	__ssrInlineRender: true,
	setup(__props) {
		const ages = [
			"Tous publics",
			"7 ans",
			"10 ans",
			"12 ans",
			"16 ans"
		];
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0 flex flex-col gap-[4cqw] px-[6cqw] pt-[14cqw]" }, _attrs))}><div class="font-display text-[6.4cqw] font-bold">Contrôle parental</div><div class="rounded-[4cqw] border border-white/8 bg-surface p-[5cqw]"><div class="flex items-center justify-between"><span class="text-[4cqw] font-semibold">Code PIN parental</span><span class="rounded-[1.4cqw] bg-accent px-[2cqw] text-[3cqw] font-semibold text-on-accent">Activé</span></div><div class="mt-[2.4cqw] h-[2.4cqw] w-[85%] rounded bg-white/20"></div><div class="mt-[1.4cqw] h-[2.4cqw] w-[60%] rounded bg-white/20"></div></div><div class="rounded-[4cqw] border border-white/8 bg-surface p-[5cqw]"><div class="flex items-center gap-[3cqw]"><div class="size-[11cqw] overflow-hidden rounded-[2.4cqw]">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$4, { avatar: (0, vue_exports.unref)(avatars_default)[3] }, null, _parent));
			_push(`</div><span class="text-[4.2cqw] font-semibold">Léa</span></div><div class="mt-[4cqw] flex items-center justify-between text-[3.6cqw]"><span>Profil enfant</span><span class="relative h-[6cqw] w-[11cqw] rounded-full bg-accent"><span class="absolute top-[0.8cqw] right-[0.8cqw] size-[4.4cqw] rounded-full bg-on-accent"></span></span></div><div class="mt-[4cqw] text-[3.2cqw] text-text-secondary">Âge maximum</div><div class="mt-[2cqw] flex flex-wrap gap-[2cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(ages, (age) => {
				_push(`<span class="${(0, server_renderer_exports.ssrRenderClass)(["rounded-full px-[3cqw] py-[1.2cqw] text-[3cqw] font-semibold", age === "12 ans" ? "bg-accent/15 text-accent" : "border border-white/15"])}">${(0, server_renderer_exports.ssrInterpolate)(age)}</span>`);
			});
			_push(`<!--]--></div></div></div>`);
		};
	}
};
var _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/PhoneParentalScreen.vue");
	return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
// resources/js/mock/TvDetailsScreen.vue
var _sfc_main$2 = {
	__name: "TvDetailsScreen",
	__ssrInlineRender: true,
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0" }, _attrs))}><div class="absolute inset-0" style="${(0, server_renderer_exports.ssrRenderStyle)({ "background": "radial-gradient(circle at 75% 35%, rgba(255,140,90,0.35), transparent 50%), linear-gradient(110deg, #070a08 30%, #2a2026 100%)" })}"></div><div class="relative flex h-full flex-col justify-end gap-[1.4cqw] p-[4cqw]"><div class="h-[4cqw] w-[34cqw] rounded-[0.6cqw] bg-white/90"></div><div class="flex gap-[1cqw] text-[1.3cqw] text-text-soft"><span>2024</span><span>·</span><span>2 h 06</span><span>·</span><span class="rounded-[0.4cqw] border border-white/25 px-[0.6cqw]">12+</span><span>·</span><span>Drame, Aventure</span></div><div class="flex items-center gap-[1cqw]"><span class="flex h-[3.6cqw] items-center rounded-[0.8cqw] bg-accent px-[1.8cqw] text-[1.4cqw] font-semibold text-on-accent">▶ Lecture</span><span class="grid size-[3.6cqw] place-items-center rounded-full bg-white/14 text-[1.6cqw]">＋</span><span class="grid size-[3.6cqw] place-items-center rounded-full bg-white/14 text-[1.6cqw]">✓</span><span class="grid size-[3.6cqw] place-items-center rounded-full bg-white/14 text-[1.4cqw]">▶</span></div><div class="w-[46cqw] space-y-[0.6cqw]"><div class="h-[1cqw] rounded bg-white/25"></div><div class="h-[1cqw] w-[90%] rounded bg-white/25"></div><div class="h-[1cqw] w-[70%] rounded bg-white/25"></div></div><div class="mt-[1cqw] text-[1.3cqw] font-semibold text-text-soft">Distribution</div><div class="flex gap-[1.6cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(6, (i) => {
				_push(`<div class="flex flex-col items-center gap-[0.5cqw]"><div class="size-[5cqw] overflow-hidden rounded-full">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$13, {
					seed: i * 3,
					class: "!rounded-none"
				}, null, _parent));
				_push(`</div><div class="h-[0.8cqw] w-[4cqw] rounded bg-white/25"></div></div>`);
			});
			_push(`<!--]--></div></div></div>`);
		};
	}
};
var _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/TvDetailsScreen.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
// resources/js/mock/TvWhoScreen.vue
var _sfc_main$1 = {
	__name: "TvWhoScreen",
	__ssrInlineRender: true,
	setup(__props) {
		const profiles = [
			{
				name: "Davy",
				avatar: avatars_default[0]
			},
			{
				name: "Léa",
				avatar: avatars_default[3],
				kid: true
			},
			{
				name: "Sam",
				avatar: avatars_default[1]
			},
			{
				name: "Inès",
				avatar: avatars_default[2]
			}
		];
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${(0, server_renderer_exports.ssrRenderAttrs)((0, vue_exports.mergeProps)({ class: "absolute inset-0 flex flex-col items-center justify-center bg-[radial-gradient(circle_at_50%_0%,rgba(46,229,138,0.12),transparent_60%)]" }, _attrs))}><div class="font-display text-[4cqw] font-bold">Qui regarde ?</div><div class="mt-[4cqw] flex gap-[3.5cqw]"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(profiles, (p, i) => {
				_push(`<div class="flex flex-col items-center gap-[1.2cqw]"><div class="${(0, server_renderer_exports.ssrRenderClass)(["size-[12cqw] overflow-hidden rounded-[2cqw]", i === 0 ? "scale-105 ring-[0.45cqw] ring-text" : ""])}">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$4, { avatar: p.avatar }, null, _parent));
				_push(`</div><div class="text-[1.6cqw] font-medium">${(0, server_renderer_exports.ssrInterpolate)(p.name)}</div>`);
				if (p.kid) _push(`<div class="-mt-[0.6cqw] rounded bg-surface-high px-[0.6cqw] text-[1cqw] text-text-secondary">Enfant</div>`);
				else _push(`<!---->`);
				_push(`</div>`);
			});
			_push(`<!--]--><div class="flex flex-col items-center gap-[1.2cqw]"><div class="grid size-[12cqw] place-items-center rounded-[2cqw] border-[0.25cqw] border-white/20 text-[5cqw] text-text-muted">+</div><div class="text-[1.6cqw] text-text-muted">Ajouter</div></div></div></div>`);
		};
	}
};
var _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/mock/TvWhoScreen.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
// resources/js/pages/Home.vue
var _sfc_main = {
	__name: "Home",
	__ssrInlineRender: true,
	props: { latestAndroid: {
		type: Object,
		default: null
	} },
	setup(__props) {
		const page = usePage();
		const site = page.props.site;
		const onAndroid = (0, vue_exports.computed)(() => page.props.platform !== "web");
		const hasAndroid = (0, vue_exports.computed)(() => !!site.android.apkUrl);
		const androidFirst = (0, vue_exports.computed)(() => onAndroid.value && hasAndroid.value);
		const questions = faq(site);
		const screens = [
			{
				icon: "phone",
				label: "Téléphone Android"
			},
			{
				icon: "phone",
				label: "Tablette Android"
			},
			{
				icon: "tv",
				label: "Android TV"
			},
			{
				icon: "tv",
				label: "Google TV"
			},
			{
				icon: "globe",
				label: "Chrome, Edge, Firefox, Safari"
			}
		];
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$20, { description: "Vixeo rassemble vos films et séries : fiches complètes, un profil par personne, votre liste et votre progression sur votre téléphone Android, votre TV et votre navigateur." }, null, _parent));
			_push(`<section class="relative overflow-hidden pt-32 pb-16 sm:pt-40"><div class="pointer-events-none absolute inset-x-0 top-0 h-[44rem] bg-[radial-gradient(ellipse_at_50%_0%,rgba(46,229,138,0.16),transparent_60%)]" aria-hidden="true"></div><div class="relative mx-auto max-w-6xl px-5 text-center sm:px-8"><p class="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-4 py-1.5 text-sm text-text-soft"><span class="size-2 rounded-full bg-accent"></span> Android · Android TV · Navigateur web </p><h1 class="mx-auto mt-7 max-w-4xl text-[2.6rem] leading-[1.05] font-extrabold tracking-tight sm:text-6xl lg:text-7xl"> Vos films et séries, <span class="text-accent">sur tous vos écrans.</span></h1><p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-text-secondary sm:text-xl"> Des fiches complètes, un profil pour chaque personne et votre liste qui vous suit du téléphone à la TV. </p><div class="mt-9 flex flex-wrap items-center justify-center gap-3">`);
			if (androidFirst.value) {
				_push(`<!--[-->`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
					href: "#installer",
					icon: "download",
					size: "lg"
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`${(0, server_renderer_exports.ssrInterpolate)((0, vue_exports.unref)(page).props.platform === "tv" ? "Installer sur la TV" : "Installer sur Android")}`);
						else return [(0, vue_exports.createTextVNode)((0, vue_exports.toDisplayString)((0, vue_exports.unref)(page).props.platform === "tv" ? "Installer sur la TV" : "Installer sur Android"), 1)];
					}),
					_: 1
				}, _parent));
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
					href: (0, vue_exports.unref)(site).appUrl,
					icon: "globe",
					variant: "outline",
					size: "lg"
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`Ouvrir dans le navigateur`);
						else return [(0, vue_exports.createTextVNode)("Ouvrir dans le navigateur")];
					}),
					_: 1
				}, _parent));
				_push(`<!--]-->`);
			} else {
				_push(`<!--[-->`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
					href: (0, vue_exports.unref)(site).appUrl,
					icon: "globe",
					size: "lg"
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`Ouvrir dans le navigateur`);
						else return [(0, vue_exports.createTextVNode)("Ouvrir dans le navigateur")];
					}),
					_: 1
				}, _parent));
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
					href: "#installer",
					icon: "phone",
					variant: "outline",
					size: "lg"
				}, {
					default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
						if (_push) _push(`Installer sur Android`);
						else return [(0, vue_exports.createTextVNode)("Installer sur Android")];
					}),
					_: 1
				}, _parent));
				_push(`<!--]-->`);
			}
			_push(`</div><p class="mt-5 text-sm text-text-muted">Vixeo n’héberge ni ne diffuse aucun film ni aucune série.</p></div><div class="relative mx-auto mt-16 max-w-5xl px-5 sm:px-8">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$10, { label: "L’accueil de Vixeo sur une TV : la grande image du titre choisi et la rangée « Continuer à regarder »" }, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$9, null, null, _parent, _scopeId));
					else return [(0, vue_exports.createVNode)(_sfc_main$9)];
				}),
				_: 1
			}, _parent));
			_push(`<div class="absolute -bottom-8 right-3 w-[26%] max-w-[12rem] min-w-[6.5rem] sm:right-2 lg:-right-8">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$14, { label: "L’accueil de Vixeo sur un téléphone" }, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$12, null, null, _parent, _scopeId));
					else return [(0, vue_exports.createVNode)(_sfc_main$12)];
				}),
				_: 1
			}, _parent));
			_push(`</div></div><ul class="relative mx-auto mt-20 flex max-w-5xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-5 text-sm text-text-secondary"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)(screens, (screen) => {
				_push(`<li class="flex items-center gap-2">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
					name: screen.icon,
					size: 18,
					class: "text-text-muted"
				}, null, _parent));
				_push(` ${(0, server_renderer_exports.ssrInterpolate)(screen.label)}</li>`);
			});
			_push(`<!--]--></ul></section><section id="installer" class="mx-auto max-w-6xl scroll-mt-24 px-5 py-20 sm:px-8 sm:py-28" aria-labelledby="titre-ecrans">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$6, {
				id: "titre-ecrans",
				title: "Choisissez votre écran."
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Chaque version est pensée pour son écran : au doigt sur le téléphone, à la télécommande sur la TV, au clavier et à la souris dans le navigateur. `);
					else return [(0, vue_exports.createTextVNode)(" Chaque version est pensée pour son écran : au doigt sur le téléphone, à la télécommande sur la TV, au clavier et à la souris dans le navigateur. ")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$7, {
				class: "mt-10",
				"latest-android": __props.latestAndroid
			}, null, _parent));
			_push(`</section><section id="fonctionnalites" class="border-t border-line/60 py-20 sm:py-28" aria-labelledby="titre-fonctionnalites"><div class="mx-auto max-w-6xl px-5 sm:px-8">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$6, {
				id: "titre-fonctionnalites",
				title: "Ce que Vixeo change à la maison.",
				centered: ""
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Tout pour choisir quoi regarder, et s’y retrouver à plusieurs. `);
					else return [(0, vue_exports.createTextVNode)(" Tout pour choisir quoi regarder, et s’y retrouver à plusieurs. ")];
				}),
				_: 1
			}, _parent));
			_push(`<div class="mt-20 space-y-24 sm:space-y-32">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$15, {
				title: "Un profil pour chaque personne",
				icon: "users"
			}, {
				visual: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$10, { label: "L’écran « Qui regarde ? » avec quatre profils et leurs avatars en pixel art" }, {
						default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
							if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$1, null, null, _parent, _scopeId));
							else return [(0, vue_exports.createVNode)(_sfc_main$1)];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [(0, vue_exports.createVNode)(_sfc_main$10, { label: "L’écran « Qui regarde ? » avec quatre profils et leurs avatars en pixel art" }, {
						default: (0, vue_exports.withCtx)(() => [(0, vue_exports.createVNode)(_sfc_main$1)]),
						_: 1
					})];
				}),
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` « Qui regarde ? » s’affiche à l’ouverture : chacun retrouve sa liste, sa progression et son avatar. Un code PIN protège un profil si besoin, et un profil enfant ne voit que les titres de son âge. `);
					else return [(0, vue_exports.createTextVNode)(" « Qui regarde ? » s’affiche à l’ouverture : chacun retrouve sa liste, sa progression et son avatar. Un code PIN protège un profil si besoin, et un profil enfant ne voit que les titres de son âge. ")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$15, {
				title: "Pensé pour la télécommande",
				icon: "remote",
				reverse: ""
			}, {
				visual: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$10, { label: "La connexion de la TV : un QR code à scanner et un code court" }, {
						default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
							if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$11, null, null, _parent, _scopeId));
							else return [(0, vue_exports.createVNode)(_sfc_main$11)];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [(0, vue_exports.createVNode)(_sfc_main$10, { label: "La connexion de la TV : un QR code à scanner et un code court" }, {
						default: (0, vue_exports.withCtx)(() => [(0, vue_exports.createVNode)(_sfc_main$11)]),
						_: 1
					})];
				}),
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Sur Android TV, tout se fait aux flèches et au bouton OK : l’élément choisi est entouré de vert. Pour vous connecter, scannez le QR code affiché sur la TV avec votre téléphone, sans taper de mot de passe. `);
					else return [(0, vue_exports.createTextVNode)(" Sur Android TV, tout se fait aux flèches et au bouton OK : l’élément choisi est entouré de vert. Pour vous connecter, scannez le QR code affiché sur la TV avec votre téléphone, sans taper de mot de passe. ")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$15, {
				title: "Votre liste vous suit",
				icon: "bookmark"
			}, {
				visual: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) {
						_push(`<div class="mx-auto flex max-w-[22rem] items-end justify-center gap-5 pt-8"${_scopeId}>`);
						_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$14, {
							label: "Ma liste sur le téléphone",
							class: "w-1/2"
						}, {
							default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
								if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$5, null, null, _parent, _scopeId));
								else return [(0, vue_exports.createVNode)(_sfc_main$5)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$14, {
							label: "L’accueil et « Continuer à regarder » sur le téléphone",
							class: "w-1/2 -translate-y-8"
						}, {
							default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
								if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$12, null, null, _parent, _scopeId));
								else return [(0, vue_exports.createVNode)(_sfc_main$12)];
							}),
							_: 1
						}, _parent, _scopeId));
						_push(`</div>`);
					} else return [(0, vue_exports.createVNode)("div", { class: "mx-auto flex max-w-[22rem] items-end justify-center gap-5 pt-8" }, [(0, vue_exports.createVNode)(_sfc_main$14, {
						label: "Ma liste sur le téléphone",
						class: "w-1/2"
					}, {
						default: (0, vue_exports.withCtx)(() => [(0, vue_exports.createVNode)(_sfc_main$5)]),
						_: 1
					}), (0, vue_exports.createVNode)(_sfc_main$14, {
						label: "L’accueil et « Continuer à regarder » sur le téléphone",
						class: "w-1/2 -translate-y-8"
					}, {
						default: (0, vue_exports.withCtx)(() => [(0, vue_exports.createVNode)(_sfc_main$12)]),
						_: 1
					})])];
				}),
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Ajoutez un titre à votre liste sur le téléphone, retrouvez-le le soir sur la TV. « Continuer à regarder », titres vus et liste sont synchronisés pour chaque profil. `);
					else return [(0, vue_exports.createTextVNode)(" Ajoutez un titre à votre liste sur le téléphone, retrouvez-le le soir sur la TV. « Continuer à regarder », titres vus et liste sont synchronisés pour chaque profil. ")];
				}),
				_: 1
			}, _parent));
			_push(`</div></div></section><section class="border-y border-line/60 bg-surface/50 py-20 sm:py-28" aria-labelledby="titre-fiches"><div class="mx-auto max-w-6xl px-5 sm:px-8">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$15, {
				title: "Des fiches complètes",
				icon: "film",
				reverse: "",
				points: [
					"Recherche par titre, par acteur ou par genre",
					"Affiches au format vertical ou horizontal, au choix de chaque profil",
					"Informations et images fournies par TMDb"
				]
			}, {
				visual: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$10, { label: "La fiche d’un film : grande image, informations, boutons Ma liste, Vu et Bande-annonce, distribution" }, {
						default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
							if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$2, null, null, _parent, _scopeId));
							else return [(0, vue_exports.createVNode)(_sfc_main$2)];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [(0, vue_exports.createVNode)(_sfc_main$10, { label: "La fiche d’un film : grande image, informations, boutons Ma liste, Vu et Bande-annonce, distribution" }, {
						default: (0, vue_exports.withCtx)(() => [(0, vue_exports.createVNode)(_sfc_main$2)]),
						_: 1
					})];
				}),
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Résumé, distribution, saisons et épisodes, recommandations et bande-annonce : chaque film et chaque série a sa fiche. Touchez un acteur pour voir sa filmographie. `);
					else return [(0, vue_exports.createTextVNode)(" Résumé, distribution, saisons et épisodes, recommandations et bande-annonce : chaque film et chaque série a sa fiche. Touchez un acteur pour voir sa filmographie. ")];
				}),
				_: 1
			}, _parent));
			_push(`</div></section><section class="py-20 sm:py-28"><div class="mx-auto max-w-6xl space-y-24 px-5 sm:px-8">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$15, {
				title: "Un contrôle parental simple",
				icon: "shield"
			}, {
				visual: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$14, {
						label: "Le contrôle parental : code PIN activé et âge maximum de 12 ans pour le profil de Léa",
						class: "mx-auto max-w-[15rem]"
					}, {
						default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
							if (_push) _push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$3, null, null, _parent, _scopeId));
							else return [(0, vue_exports.createVNode)(_sfc_main$3)];
						}),
						_: 1
					}, _parent, _scopeId));
					else return [(0, vue_exports.createVNode)(_sfc_main$14, {
						label: "Le contrôle parental : code PIN activé et âge maximum de 12 ans pour le profil de Léa",
						class: "mx-auto max-w-[15rem]"
					}, {
						default: (0, vue_exports.withCtx)(() => [(0, vue_exports.createVNode)(_sfc_main$3)]),
						_: 1
					})];
				}),
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Un code PIN parental protège la création des profils et leurs réglages. Choisissez un âge maximum pour chaque profil : le catalogue et la recherche s’adaptent aussitôt. `);
					else return [(0, vue_exports.createTextVNode)(" Un code PIN parental protège la création des profils et leurs réglages. Choisissez un âge maximum pour chaque profil : le catalogue et la recherche s’adaptent aussitôt. ")];
				}),
				_: 1
			}, _parent));
			_push(`<div class="grid gap-4 sm:grid-cols-3"><!--[-->`);
			(0, server_renderer_exports.ssrRenderList)([
				{
					icon: "sparkle",
					title: "Sans publicité",
					text: "Aucune publicité dans l’appli, ni sur ce site."
				},
				{
					icon: "lock",
					title: "Sans pistage",
					text: "Ni mesure d’audience, ni revente de données."
				},
				{
					icon: "download",
					title: "Vos données à vous",
					text: "Recevez-en une copie ou supprimez votre compte depuis l’appli."
				}
			], (card) => {
				_push(`<div class="rounded-3xl border border-line bg-surface/60 p-6">`);
				_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$17, {
					name: card.icon,
					size: 24,
					class: "text-accent"
				}, null, _parent));
				_push(`<h3 class="mt-4 text-lg font-bold">${(0, server_renderer_exports.ssrInterpolate)(card.title)}</h3><p class="mt-2 text-sm leading-relaxed text-text-secondary">${(0, server_renderer_exports.ssrInterpolate)(card.text)}</p></div>`);
			});
			_push(`<!--]--></div></div></section><section id="faq" class="mx-auto max-w-4xl scroll-mt-24 px-5 pb-20 sm:px-8 sm:pb-28" aria-labelledby="titre-faq">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$6, {
				id: "titre-faq",
				title: "Questions fréquentes."
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(` Tout ce qu’il faut savoir avant d’installer Vixeo. `);
					else return [(0, vue_exports.createTextVNode)(" Tout ce qu’il faut savoir avant d’installer Vixeo. ")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$16, {
				items: (0, vue_exports.unref)(questions),
				class: "mt-10"
			}, null, _parent));
			_push(`</section><section class="relative overflow-hidden border-t border-line/60 py-24 text-center sm:py-32" aria-labelledby="titre-installer"><div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_50%_120%,rgba(46,229,138,0.18),transparent_60%)]" aria-hidden="true"></div><div class="relative mx-auto max-w-3xl px-5"><h2 id="titre-installer" class="text-3xl font-extrabold sm:text-5xl">Installez Vixeo en une minute.</h2><p class="mt-5 text-lg text-text-secondary">Un seul compte pour votre téléphone, votre TV et votre navigateur.</p><div class="mt-9 flex flex-wrap justify-center gap-3">`);
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
				href: "#installer",
				icon: "download",
				size: "lg"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Installer Vixeo`);
					else return [(0, vue_exports.createTextVNode)("Installer Vixeo")];
				}),
				_: 1
			}, _parent));
			_push((0, server_renderer_exports.ssrRenderComponent)(_sfc_main$19, {
				href: (0, vue_exports.unref)(site).appUrl,
				icon: "globe",
				variant: "outline",
				size: "lg"
			}, {
				default: (0, vue_exports.withCtx)((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Ouvrir dans le navigateur`);
					else return [(0, vue_exports.createTextVNode)("Ouvrir dans le navigateur")];
				}),
				_: 1
			}, _parent));
			_push(`</div></div></section><section class="mx-auto max-w-6xl px-5 pb-20 sm:px-8" aria-labelledby="titre-pas"><div class="grid gap-6 border-t border-line/60 pt-12 md:grid-cols-[1fr_2.2fr]"><h2 id="titre-pas" class="text-lg font-bold">Ce que Vixeo n’est pas.</h2><div class="space-y-4 text-sm leading-relaxed text-text-muted"><p> Vixeo organise des films et des séries. Il n’héberge, ne stocke et ne diffuse aucun film ni aucune série. </p><p> Les informations et images des titres proviennent de TMDb. This product uses the TMDB API but is not endorsed or certified by TMDB. </p><p>Les écrans de cette page sont des illustrations : les affiches y sont remplacées par des aplats de couleur.</p></div></div></section><!--]-->`);
		};
	}
};
var _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
	const ssrContext = (0, vue_exports.useSSRContext)();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/pages/Home.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export { _sfc_main as default };
