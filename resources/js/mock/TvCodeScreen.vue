<script setup>
// TV sign-in: QR code + short code to approve from a phone (like TvCodeCard in the app).
// The QR here is only a drawing.
const size = 21;
const cells = [];
let s = 7;
for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
        const finder = (cx, cy) => x >= cx && x < cx + 7 && y >= cy && y < cy + 7;
        if (finder(0, 0) || finder(14, 0) || finder(0, 14)) {
            const lx = x % 7 === x ? x : x - 14;
            const ly = y < 7 ? y : y - 14;
            const ring = lx === 0 || lx === 6 || ly === 0 || ly === 6;
            const core = lx >= 2 && lx <= 4 && ly >= 2 && ly <= 4;
            if (ring || core) cells.push([x, y]);
            continue;
        }
        s = (s * 1103515245 + 12345) & 0x7fffffff;
        if (s % 100 < 46) cells.push([x, y]);
    }
}
</script>

<template>
    <div class="absolute inset-0 flex items-center justify-center gap-[3cqw] bg-[radial-gradient(circle_at_20%_100%,rgba(46,229,138,0.10),transparent_55%)] px-[5cqw]">
        <div class="w-[46cqw] rounded-[2cqw] border border-white/10 bg-surface p-[2.6cqw]">
            <span class="rounded-[0.5cqw] bg-accent px-[0.8cqw] py-[0.2cqw] text-[1.1cqw] font-semibold text-on-accent">Le plus simple</span>
            <div class="mt-[1.4cqw] font-display text-[2.4cqw] font-bold">Avec votre téléphone</div>
            <div class="mt-[2cqw] flex gap-[2.4cqw]">
                <div class="size-[15cqw] shrink-0 rounded-[1cqw] bg-text p-[0.9cqw]">
                    <svg viewBox="0 0 21 21" class="size-full" shape-rendering="crispEdges">
                        <rect v-for="([x, y], i) in cells" :key="i" :x="x" :y="y" width="1" height="1" fill="#070a08" />
                    </svg>
                </div>
                <div class="text-[1.35cqw] leading-snug text-text-soft">
                    <div>1. Scannez le code</div>
                    <div class="mt-[1cqw]">2. Saisissez ce code :</div>
                    <div class="mt-[0.8cqw] font-display text-[3.6cqw] font-extrabold tracking-[0.3cqw] text-accent">K7F-29Q</div>
                    <div class="mt-[0.8cqw] text-[1.05cqw] text-text-muted">Un nouveau code toutes les 10 minutes.</div>
                </div>
            </div>
        </div>
        <div class="w-[30cqw] rounded-[2cqw] border border-white/10 bg-surface p-[2.6cqw]">
            <div class="font-display text-[2.2cqw] font-bold">Connectez-vous</div>
            <div class="mt-[2cqw] h-[3.4cqw] rounded-[0.8cqw] border border-white/10 bg-field" />
            <div class="mt-[1.2cqw] h-[3.4cqw] rounded-[0.8cqw] border border-white/10 bg-field" />
            <div class="mt-[2cqw] grid h-[3.8cqw] place-items-center rounded-[0.8cqw] bg-accent text-[1.4cqw] font-semibold text-on-accent">
                Se connecter
            </div>
        </div>
    </div>
</template>
