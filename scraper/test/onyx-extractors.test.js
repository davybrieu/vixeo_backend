const test = require('node:test');
const assert = require('node:assert/strict');

const { findExtractor, extractorNames } = require('../src/extractors');
const { extractMazQuest } = require('../src/extractors/mazquest');
const { extractYandexDisk, normalizePublicKey } = require('../src/extractors/yandexdisk');
const { buildMazQuestEpisodeUrl, searchMazQuest, buildFallbackSources } = require('../src/providers/onyx-tmdb-fr');

test('Onyx TMDb FR uses the MazQuest TV endpoint with padded season and episode numbers', () => {
  assert.equal(
    buildMazQuestEpisodeUrl({ type: 'tv', tmdb_id: 123, season: 2, episode: 4 }),
    'https://embed.maz.quest/tv/api/123/02/04',
  );
  assert.equal(buildMazQuestEpisodeUrl({ type: 'movie', tmdb_id: 123 }), null);
});

test('MazQuest is queried only for an episode and returns a server only when links exist', async () => {
  const originalFetch = global.fetch;
  global.fetch = async (url) => ({
    ok: true,
    json: async () => ({ error: false, links: ['https://disk.yandex.ru/d/example'] }),
  });

  try {
    const sources = await searchMazQuest({ type: 'tv', tmdb_id: 123, season: 2, episode: 4 });
    assert.equal(sources.length, 1);
    assert.equal(sources[0].provider, 'mazquest');
    assert.equal(sources[0].url, 'https://embed.maz.quest/tv/api/123/02/04');
  } finally {
    global.fetch = originalFetch;
  }
});

test('MazQuest and Yandex Disk are registered in the shared extractor registry', () => {
  assert.ok(extractorNames.includes('mazquest'));
  assert.ok(extractorNames.includes('yandexdisk'));
  assert.equal(findExtractor('https://embed.maz.quest/tv/api/123/02/04')?.name, 'mazquest');
  assert.equal(findExtractor('https://disk.yandex.ru/d/example')?.name, 'yandexdisk');
  assert.equal(findExtractor('https://yadi.sk/d/example')?.name, 'yandexdisk');
});

test('Yandex Disk normalizes public links and extracts a direct video file URL', async () => {
  assert.equal(
    normalizePublicKey('https://disk.yandex.ru/d/example'),
    'https://yadi.sk/d/example',
  );
  const result = await extractYandexDisk('https://disk.yandex.com/d/example', async (url) => ({
    ok: true,
    json: async () => ({
      type: 'file',
      file: 'https://downloader.example/video.mp4',
      mime_type: 'video/mp4',
    }),
  }));
  assert.deepEqual(result, {
    url: 'https://downloader.example/video.mp4',
    type: 'mp4',
  });
});

test('MazQuest resolves Yandex Disk upstream links through the Yandex extractor', async () => {
  const originalFetch = global.fetch;
  let calls = 0;
  global.fetch = async (url) => {
    calls += 1;
    if (String(url).includes('embed.maz.quest')) {
      return { ok: true, json: async () => ({
        error: false,
        links: ['https://disk.yandex.ru/d/example'],
      }) };
    }
    return { ok: true, json: async () => ({
      type: 'file',
      file: 'https://downloader.example/video.mp4',
      mime_type: 'video/mp4',
    }) };
  };

  try {
    const result = await extractMazQuest('https://embed.maz.quest/tv/api/123/02/04');
    assert.equal(result.url, 'https://downloader.example/video.mp4');
    assert.equal(result.type, 'mp4');
    assert.equal(calls, 2);
  } finally {
    global.fetch = originalFetch;
  }
});

test('every Onyx TMDb movie fallback URL is handled by a registered Vixeo extractor', () => {
  const sources = buildFallbackSources({ type: 'movie', tmdb_id: 1368337 });
  assert.deepEqual(
    sources.map((source) => findExtractor(source.url)?.name),
    ['vixsrc', 'vidsrcnet', 'vidlink', 'vidsrcru', 'twoembed'],
  );
});
