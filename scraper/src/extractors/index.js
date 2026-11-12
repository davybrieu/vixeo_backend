// Extractor registry, ported from the ONYX extractor list: each entry maps a
// set of host names to a function that turns a player URL into a stream.
const { extractVoe } = require('./voe');
const { extractGoodstream } = require('./goodstream');
const { extractBigWarp } = require('./bigwarp');
const { extractDropload } = require('./dropload');
const { extractFastream } = require('./fastream');
const { extractGupload } = require('./gupload');
const { extractHxfile } = require('./hxfile');
const { extractOneupload } = require('./oneupload');
const { extractPixeldrain } = require('./pixeldrain');
const { extractSavefiles } = require('./savefiles');
const { extractStreamhub } = require('./streamhub');
const { extractYourUpload } = require('./yourupload');
const { extractZilla } = require('./zilla');
const { extractVtube } = require('./vtube');
const { extractVideoapp } = require('./videoapp');
const { extractGxPlayer } = require('./gxplayer');
const { extractHexload } = require('./hexload');
const { extractMailRu } = require('./mailru');
const { extractOkru } = require('./okru');
const { extractMyFileStorage } = require('./myfilestorage');
const { extractMoviesapi } = require('./moviesapi');
const { extractStreamUp } = require('./streamup');
const { extractUpZur } = require('./upzur');
const { extractVidflix } = require('./vidflix');
const { extractTwoEmbed } = require('./twoembed');
const { extractDeltaBit } = require('./deltabit');
const { extractLamovie } = require('./lamovie');
const { extractLoadX } = require('./loadx');
const { extractEinschalten } = require('./einschalten');
const { extractPcloud } = require('./pcloud');
const { extractRidoo } = require('./ridoo');
const { extractShareCloudy } = require('./sharecloudy');
const { extractStreamix } = require('./streamix');
const { extractVidPly } = require('./vidply');
const { extractVidnest } = require('./vidnest');
const { extractTurbovid } = require('./turbovid');
const { extractDailymotion } = require('./dailymotion');
const { extractAmazonDrive } = require('./amazondrive');
const { extractVidGuard } = require('./vidguard');
const { extractFsvid } = require('./fsvid');
const { extractTurbovio } = require('./turbovio');
const { extractVidplay } = require('./vidplay');
const { extractVeev } = require('./veev');
const { extractMoflix } = require('./moflix');
const { extractMovieMaster } = require('./moviemaster');
const { extractVidsrcNet } = require('./vidsrcnet');
const { extractVidLink } = require('./vidlink');
const { extractVidsrcRu } = require('./vidsrcru');
const { extractUpzone } = require('./upzone');
const { extractVidsrcTo } = require('./vidsrcto');
const { extractGoogleDrive } = require('./googledrive');
const { extractLuluVdo } = require('./luluvdo');
const { extractUstr } = require('./ustr');
const { extractVideoSibNet } = require('./videosibnet');
const { extractVidmoly } = require('./vidmoly');
const { extractStreamWish } = require('./streamwish');
const { extractFilemoon } = require('./filemoon');
const { extractVidHide } = require('./vidhide');
const { extractStreamruby } = require('./streamruby');
const { extractSupervideo } = require('./supervideo');
const { extractVidara } = require('./vidara');
const { extractVidsonic } = require('./vidsonic');
const { extractRpmvid } = require('./rpmvid');
const { extractVidzy } = require('./vidzy');
const { extractUqload } = require('./uqload');
const { extractStreamtape } = require('./streamtape');
const { extractMixDrop } = require('./mixdrop');
const { extractDood } = require('./dood');
const { extractNetu } = require('./netu');
const { extractVidoza } = require('./vidoza');
const { extractSendvid } = require('./sendvid');
const { extractGeneric } = require('./generic');

const extractors = [
  { name: 'goodstream', fn: extractGoodstream, domains: ['goodstream.one'] },
  { name: 'bigwarp', fn: extractBigWarp, domains: ['bigwarp.cc', 'bigwarp.io', 'bigwarp.pro'] },
  { name: 'dropload', fn: extractDropload, domains: ['dropload.tv', 'dropload.io', 'dropload.pro', 'dr0pstream.com'] },
  { name: 'fastream', fn: extractFastream, domains: ['fastream.to'] },
  { name: 'gupload', fn: extractGupload, domains: ['gupload.xyz'] },
  { name: 'hxfile', fn: extractHxfile, domains: ['hxfile.co', 'xshotcok.com'] },
  { name: 'oneupload', fn: extractOneupload, domains: ['oneupload.net', 'tipfly.xyz'] },
  { name: 'pixeldrain', fn: extractPixeldrain, domains: ['pixeldrain.com'] },
  { name: 'savefiles', fn: extractSavefiles, domains: ['savefiles.com', 'streamhls.to'] },
  { name: 'streamhub', fn: extractStreamhub, domains: ['streamhub.to'] },
  { name: 'yourupload', fn: extractYourUpload, domains: ['yourupload.com', 'yucache.net'] },
  { name: 'zilla', fn: extractZilla, domains: ['player.zilla-networks.com'] },
  { name: 'vtube', fn: extractVtube, domains: ['vtbe.to', 'vtube.to'] },
  { name: 'videoapp', fn: extractVideoapp, domains: ['videoapp.zip'] },
  { name: 'gxplayer', fn: extractGxPlayer, domains: ['watch.gxplayer.xyz'] },
  { name: 'hexload', fn: extractHexload, domains: ['hexload.com'] },
  { name: 'mailru', fn: extractMailRu, domains: ['my.mail.ru'] },
  { name: 'okru', fn: extractOkru, domains: ['ok.ru'] },
  { name: 'myfilestorage', fn: extractMyFileStorage, domains: ['myfilestorage.xyz'] },
  { name: 'moviesapi', fn: extractMoviesapi, domains: ['moviesapi.club'] },
  { name: 'streamup', fn: extractStreamUp, domains: ['strmup.to'] },
  { name: 'upzur', fn: extractUpZur, domains: ['upzur.com'] },
  { name: 'vidflix', fn: extractVidflix, domains: ['vidflix.club'] },
  { name: 'twoembed', fn: extractTwoEmbed, domains: ['2embed.cc'] },
  { name: 'deltabit', fn: extractDeltaBit, domains: ['deltabit.co'] },
  { name: 'lamovie', fn: extractLamovie, domains: ['lamovie.link', 'vimeos.net'] },
  { name: 'loadx', fn: extractLoadX, domains: ['loadx.ws'] },
  { name: 'einschalten', fn: extractEinschalten, domains: ['einschalten.in'] },
  { name: 'pcloud', fn: extractPcloud, domains: ['u.pcloud.link'] },
  { name: 'ridoo', fn: extractRidoo, domains: ['ridoo.net'] },
  { name: 'sharecloudy', fn: extractShareCloudy, domains: ['sharecloudy.com'] },
  { name: 'streamix', fn: extractStreamix, domains: ['streamix.so', 'stmix.io'] },
  { name: 'vidply', fn: extractVidPly, domains: ['vidply.com'] },
  { name: 'vidnest', fn: extractVidnest, domains: ['vidnest.io', 'vidnest.live'] },
  { name: 'turbovid', fn: extractTurbovid, domains: ['turbovid.me'] },
  { name: 'dailymotion', fn: extractDailymotion, domains: ['dailymotion.com', 'geo.dailymotion.com'] },
  { name: 'amazondrive', fn: extractAmazonDrive, domains: ['amazon.com'] },
  { name: 'vidguard', fn: extractVidGuard, domains: ['vidguard.to', 'vembed.net', 'bembed.cc', 'vgfplay.com', 'listeamed.net'] },
  { name: 'fsvid', fn: extractFsvid, domains: ['fsvid.lol'] },
  { name: 'turbovio', fn: extractTurbovio, domains: ['turboembed.com'] },
  { name: 'vidplay', fn: extractVidplay, domains: ['vidplay.site', 'mcloud.bz', 'vidplay.online'] },
  { name: 'veev', fn: extractVeev, domains: ['veev.to', 'kinoger.pw', 'poophq.com', 'doods.to', 'doods.com'] },
  { name: 'moflix', fn: extractMoflix, domains: ['moflix-stream.xyz'] },
  { name: 'moviemaster', fn: extractMovieMaster, domains: ['api.moviemaster.lat'] },
  { name: 'vidsrcnet', fn: extractVidsrcNet, domains: ['vidsrc-embed.ru', 'vsembed.ru'] },
  { name: 'vidlink', fn: extractVidLink, domains: ['vidlink.pro'] },
  { name: 'vidsrcru', fn: extractVidsrcRu, domains: ['vidsrc.ru'] },
  { name: 'upzone', fn: extractUpzone, domains: ['upzone.cc', 'upzone.to', 'upzone.net', 'upzone.link'] },
  { name: 'vidsrcto', fn: extractVidsrcTo, domains: ['vidsrc.to'] },
  { name: 'googledrive', fn: extractGoogleDrive, domains: ['drive.google.com'] },
  { name: 'luluvdo', fn: extractLuluVdo, domains: ['luluvdo.com', 'luluvdoo.com', 'luluvid.com', 'lulust.com'] },
  { name: 'ustr', fn: extractUstr, domains: ['ups2up.fun', 'up4stream.com', 'up4fun.top'] },
  { name: 'videosibnet', fn: extractVideoSibNet, domains: ['video.sibnet.ru'] },
  { name: 'vidmoly', fn: extractVidmoly, domains: ['vidmoly.me', 'vidmoly.net', 'vidmoly.org', 'vidmoly.to'] },
  {
    name: 'voe',
    fn: extractVoe,
    domains: [
      'voe.sx', 'jessicachoosemake.com', 'jamesbornmain.com', 'jilliandescribecompany.com',
      'mikaylaarealike.com', 'christopheruntilpoint.com', 'walterprettytheir.com',
      'crystaltreatmenteast.com', 'lauradaydo.com', 'lancewhosedifficult.com',
      'dianaavoidthey.com', 'jefferycontrolmodel.com', 'sandratableother.com',
      'marissasharecareer.com', 'ralphysuccessfull.org', 'charlestoughrace.com',
      'timmaybealready.com', 'maryspecialwatch.com', 'rebeccacostthousand.com',
      'rebeccapracticeloss.com', 'johnbeyondnation.com',
      'bryantenunder.com',
    ],
  },
  {
    name: 'streamwish',
    fn: extractStreamWish,
    domains: [
      'streamwish.com', 'streamwish.to', 'streamwish.site', 'streamwish.fun', 'embedwish.com',
      'awish.pro', 'awish.top', 'dwish.pro', 'kisswish.com', 'hlswish.com', 'swishsrv.com',
      'playerwish.com', 'swiftplayers.com', 'uqloads.xyz', 'wishembed.pro', 'strmwis.xyz',
      'vidmoviesb.xyz', 'cilootv.store', 'tuktukcinema.store', 'doodporn.xyz', 'volvovideo.top',
      'wishfast.top', 'sfastwish.com', 'playembed.online', 'flaswish.com', 'obeywish.com',
      'cdnwish.com', 'javsw.me', 'cinemathek.online', 'mohahhda.site', 'ma2d.store',
      'dancima.shop', 'swhoi.com', 'jodwish.com', 'swdyu.com', 'strwish.com', 'asnwish.com',
      'wishonly.site', 'katomen.store', 'iplayerhls.com', 'hlsflast.com', 'ghbrisk.com',
      'cybervynx.com', 'dhcplay.com', 'gradehgplus.com', 'ultpreplayer.com', 'hglink.to',
      'haxloppd.com', 'hgcloud.to', 'vidwish.live', 'hgplaycdn.com', 'hanerix.com',
    ],
  },
  {
    name: 'filemoon',
    fn: extractFilemoon,
    domains: [
      'filemoon.org', 'filemoon.sx', 'filemoon.to', 'filemoon.in', 'filemoon.nl',
      'bysejikuar.com', '1day1movie.website', 'bysezoxexe.com',
      'bysebuho.com', 'bysezejataos.com', 'bysekoze.com', 'bysesayeveum.com', 'lukefirst.lol',
      'weneverbeenfree.com', 'gn1r5n.org', 'mfw09.org', 'kaydendown.lol',
    ],
  },
  {
    name: 'vidhide',
    fn: extractVidHide,
    domains: [
      'dhtpre.com', 'peytonepre.com', 'vidhideplus.com', 'dingtezuni.com', 'minochinos.com',
      'moflix-stream.click', 'filelions.to', 'filelions.com', 'streamhide.to', 'streamhide.com',
      'morencius.com', 'mivalyo.com', 'dinisglows.com',
    ],
  },
  {
    name: 'streamruby',
    fn: extractStreamruby,
    domains: [
      'streamruby.com', 'stmruby.com', 'rubystm.com', 'rubyvid.com', 'moflix-stream.fans',
    ],
  },
  {
    name: 'supervideo',
    fn: extractSupervideo,
    domains: ['supervideo.cc', 'supervideo.tv'],
  },
  {
    name: 'vidara',
    fn: extractVidara,
    domains: [
      'vidara.to', 'viewdara.com', 'vidara.so', 'ano.cx', 'bufsao.com', 'vidaraa.cc',
      'vidaraa.com', 'vidvara.site', 'vidar.cc', 'gaqghe.com', 'ykfrtza.com', 'h308sa.com',
    ],
  },
  {
    name: 'vidsonic',
    fn: extractVidsonic,
    domains: ['vidsonic.net'],
  },
  {
    name: 'rpmvid',
    fn: extractRpmvid,
    domains: [
      'rpmvid.com', 'cubeembed.rpmvid.com', 'bummi.upns.xyz', 'loadm.cam', 'anibum.playerp2p.online',
      'pelisplus.upns.pro', 'pelisplus.rpmstream.live', 'pelisplus.strp2p.com', 'flemmix.upns.pro',
      'moflix.rpmplay.xyz', 'moflix.upns.xyz', 'flix2day.xyz', 'primevid.click',
      'totocoutouno.rpmlive.online', 'dismoiceline.uns.bio', 'doremifasol.ezplayer.me',
      'marcus.p2pstream.vip', 'animeav1.uns.bio', 'serix.upns.live', 'barbapapa.p2pstream.online',
      'coflix.upn.one', 'flemmix.farm', 'flemmix.rpmlive.online', 'flemmix.upns.xyz',
      'flemmix.rpmstream.live', 'flemmix.strp2p.com', 'flemmix.ezplayer.me', 'flemmix.uns.bio',
      'flemmix.upns.live', 'flemmix.upn.one', 'flemmix.p2pstream.vip', 'flemmix.prof',
    ],
  },
  {
    name: 'vidzy',
    fn: extractVidzy,
    domains: ['vidzy.org', 'vidzy.live', 'vidzy.cc', 'vidzy.to'],
  },
  {
    name: 'uqload',
    fn: extractUqload,
    domains: ['uqload.is', 'uqload.cx', 'uqload.co', 'uqload.net', 'uqload.bz', 'uqload.io', 'uqload.com', 'uqload.ws', 'uqload.vc', 'uqload.to'],
  },
  {
    name: 'streamtape',
    fn: extractStreamtape,
    domains: ['streamtape.com', 'streamtape.net', 'streamtape.to', 'streamtape.xyz', 'streamta.site', 'strtape.cloud', 'stape.fun', 'shavetape.cash', 'advtpe.com', 'watchadsontape.com'],
  },
  {
    name: 'mixdrop',
    fn: extractMixDrop,
    domains: ['mixdrop.co', 'mixdrop.bz', 'mixdrop.ag', 'mixdrop.ch', 'mixdrop.to', 'mixdrop.cv', 'mxdrop.to', 'mixdrop.club', 'm1xdrop.net', 'mixdrop.is', 'mixdrop.gl', 'mixdrop.sx', 'mxdrop.sx', 'mixdrop.ps', 'miixdrop.net', 'miiixdrop.net', 'mxdrop.top', 'mxdrop.click', 'mixdrop.si'],
  },
  {
    name: 'dood',
    fn: extractDood,
    domains: ['dood.la', 'dood.li', 'dood.so', 'dood.to', 'dood.watch', 'dood.yt', 'dood.re', 'dood.wf', 'dood.ws', 'dood.pm', 'doodstream.com', 'doodstream.me', 'doods.pro', 'dsvplay.com', 'myvidplay.com', 'playmogo.com', 'do7go.com', 'd000d.com', 'doply.net', 'ds2video.com', 'vvide0.com', 'vide0.net'],
  },
  {
    name: 'netu',
    fn: extractNetu,
    domains: ['waaw1.tv', 'netu.tv', 'hqq.tv', 'waaw.tv', 'hqcloud.to', 'wsaw.to', 'netu.ac', 'hqq.ac', 'waaw.ac', 'waaw.to', 'younetu.org'],
  },
  {
    name: 'vidoza',
    fn: extractVidoza,
    domains: ['vidoza.net', 'videzz.net'],
  },
  {
    name: 'sendvid',
    fn: extractSendvid,
    domains: ['sendvid.com'],
  },
];

function domainMatches(host, domain) {
  return host === domain || host.endsWith(`.${domain}`);
}

function findExtractor(url) {
  let host;
  try {
    host = new URL(url).hostname.toLowerCase().replace(/^www\./, '');
  } catch (e) {
    return null;
  }
  for (const extractor of extractors) {
    if (extractor.domains.some((domain) => domainMatches(host, domain))) {
      return extractor;
    }
  }
  // Rpmvid mirrors rotate on many hosts: match the flemmix.* / *.upns.* roots.
  if (/flemmix\.|\.upns\.|\.rpmlive\.|\.rpmstream\.|\.p2pstream\.|\.ezplayer\./.test(host)) {
    return extractors.find((e) => e.name === 'rpmvid');
  }
  return null;
}

// Extracts a stream from a player URL. Returns { url, type, headers, subtitles,
// extractor } or null.
async function extract(url) {
  if (!url || !/^https?:/i.test(url)) return null;

  const found = findExtractor(url);
  if (found) {
    try {
      const result = await found.fn(url);
      if (result && result.url) return { extractor: found.name, ...result };
    } catch (e) {
      console.error(`Extractor ${found.name} failed for ${url}:`, e.message);
    }
  }

  try {
    const generic = await extractGeneric(url);
    if (generic && generic.url) return { extractor: 'generic', ...generic };
  } catch (e) {
    console.error('Generic extractor failed:', e.message);
  }

  return null;
}

module.exports = {
  extract,
  findExtractor,
  extractorNames: extractors.map((e) => e.name),
  supportedDomains: extractors.flatMap((e) => e.domains),
};
