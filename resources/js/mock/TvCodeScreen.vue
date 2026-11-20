<script setup>
// TV sign-in: a short code to type in the phone's app (like TvCodeCard in the app). No QR code:
// there is no web page to open any more.
</script>

<template>
    <div class="absolute inset-0 flex items-center justify-center gap-[3cqw] bg-[radial-gradient(circle_at_20%_100%,rgba(46,229,138,0.10),transparent_55%)] px-[5cqw]">
        <div class="w-[46cqw] rounded-[2cqw] border border-white/10 bg-surface p-[2.6cqw]">
            <span class="rounded-[0.5cqw] bg-accent px-[0.8cqw] py-[0.2cqw] text-[1.1cqw] font-semibold text-on-accent">Le plus simple</span>
            <div class="mt-[1.4cqw] font-display text-[2.4cqw] font-bold">Avec votre téléphone</div>
            <div class="mt-[2cqw] text-[1.35cqw] leading-snug text-text-soft">
                <div>1. Sur votre téléphone, ouvrez Vixeo, puis « Paramètres » → « Connecter une TV ».</div>
                <div class="mt-[1cqw]">2. Tapez ce code :</div>
                <div class="mt-[0.8cqw] font-display text-[4.6cqw] font-extrabold tracking-[0.4cqw] text-accent">K7F-29Q</div>
                <div class="mt-[0.8cqw] text-[1.05cqw] text-text-muted">Un nouveau code toutes les 10 minutes.</div>
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
