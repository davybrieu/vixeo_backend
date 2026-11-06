const { extractVoe } = require('./voe');
const { extractUqload } = require('./uqload');
const { extractStreamtape } = require('./streamtape');
const { extractDood } = require('./dood');
const { extractFilemoon } = require('./filemoon');
const { extractStreamWish } = require('./streamwish');
const { extractMixDrop } = require('./mixdrop');
const { extractGeneric } = require('./generic');

const extractors = [
  { name: 'voe', domains: ['voe.sx', 'voe-unblock', 'jilliandescribecompany.com', 'mikaylaarealike.com', 'christopheruntilpoint.com', 'walterprettytheir.com', 'crystaltreatmenteast.com', 'lauradaydo.com', 'lancewhosedifficult.com', 'dianaavoidthey.com', 'jefferycontrolmodel.com', 'richardquestionbuilding.com', 'jessicayeahcatch.com', 'juliewomanwish.com', 'rebeccapracticeloss.com', 'johnbeyondnation.com', 'jamesbornmain.com'], fn: extractVoe },
  { name: 'uqload', domains: ['uqload.com', 'uqload.net', 'uqload.io', 'uqload.co'], fn: extractUqload },
  { name: 'streamtape', domains: ['streamtape.com', 'streamtape.net', 'streamtape.to', 'streamtape.xyz'], fn: extractStreamtape },
  { name: 'dood', domains: ['doodstream.com', 'dood.watch', 'dood.to', 'dood.so', 'dood.la', 'dood.li', 'dood.ws', 'dood.pm', 'dood.re', 'dood.yt', 'doods.pro'], fn: extractDood },
  { name: 'filemoon', domains: ['filemoon.sx', 'filemoon.to', 'filemoon.in', 'filemoon.nl'], fn: extractFilemoon },
  { name: 'streamwish', domains: ['streamwish.to', 'streamwish.com', 'awish.pro', 'embedwish.com', 'kisswish.com', 'hlswish.com', 'swishsrv.com', 'playerwish.com'], fn: extractStreamWish },
  { name: 'mixdrop', domains: ['mixdrop.co', 'mixdrop.to', 'mixdrop.ch', 'mixdrop.bz', 'mixdrop.ag'], fn: extractMixDrop },
];

function findExtractor(url) {
  const host = new URL(url).hostname.toLowerCase().replace(/^www\./, '');
  for (const ext of extractors) {
    if (ext.domains.some(d => host === d || host.endsWith('.' + d) || host.includes(d.split('.')[0]))) {
      return ext;
    }
  }
  return null;
}

async function extract(url) {
  if (!url || !url.startsWith('http')) return null;
  const ext = findExtractor(url);
  try {
    if (ext) {
      const result = await ext.fn(url);
      if (result) return { extractor: ext.name, ...result };
    }
    // fallback generic
    const generic = await extractGeneric(url);
    if (generic) return { extractor: 'generic', ...generic };
  } catch (e) {
    console.error('Extract error', url, e.message);
  }
  return null;
}

module.exports = { extract, findExtractor, extractors: extractors.map(e => e.name) };
