const test = require('node:test');
const assert = require('node:assert/strict');

const {
  searchOnyxTmdbFr,
  buildFallbackSources,
  normalizeAndSortSources,
} = require('../src/providers/onyx-tmdb-fr');

test('Onyx TMDb FR builds the same TMDB-ID movie fallback servers', () => {
  const sources = buildFallbackSources({ type: 'movie', tmdb_id: 1368337 });

  assert.deepEqual(sources.map((source) => source.name), [
    'VixSrc', 'VidsrcNet', 'VidLink', 'VidsrcRu', 'TwoEmbed',
  ]);
  assert.deepEqual(sources.map((source) => source.url), [
    'https://vixsrc.to/api/movie/1368337',
    'https://vsembed.ru/embed/movie?tmdb=1368337',
    'https://vidlink.pro/movie/1368337',
    'https://vidsrc.ru/movie/1368337',
    'https://www.2embed.cc/embed/1368337',
  ]);
});

test('Onyx TMDb FR builds episode fallback URLs only when season and episode are valid', () => {
  assert.deepEqual(buildFallbackSources({
    type: 'tv', tmdb_id: 123, season: 2, episode: 4,
  }).map((source) => source.url), [
    'https://vixsrc.to/api/tv/123/2/4',
    'https://vsembed.ru/embed/tv?tmdb=123&season=2&episode=4',
    'https://vidlink.pro/tv/123/2/4',
    'https://vidsrc.ru/tv/123/2/4',
    'https://www.2embed.cc/embedtv/123?s=2&e=4',
  ]);

  assert.deepEqual(buildFallbackSources({
    type: 'tv', tmdb_id: 123, season: 0, episode: 4,
  }), []);
});

test('fallback generation rejects invalid TMDB identifiers', () => {
  assert.deepEqual(buildFallbackSources({ type: 'movie', tmdb_id: '123/../../x' }), []);
  assert.deepEqual(buildFallbackSources({ type: 'movie' }), []);
});

test('Onyx TMDb FR merges provider results, removes duplicate URLs and uses fallback when Frembed is empty', async () => {
  const providers = [
    { id: '1jour1film', run: async () => [{ name: 'Voe VF', url: 'https://voe.sx/e/1' }] },
    { id: 'frenchstream', run: async () => [{ name: 'Filemoon VOSTFR', url: 'https://filemoon.sx/e/2' }] },
    { id: 'frembed', run: async () => [] },
  ];

  const sources = await searchOnyxTmdbFr({
    type: 'movie', title: 'L’Odyssée', originalTitle: 'The Odyssey',
    year: 2026, tmdb_id: 1368337,
  }, 'test-request', providers);

  assert.equal(sources.length, 7);
  assert.ok(sources.some((source) => source.url === 'https://vixsrc.to/api/movie/1368337'));
  assert.ok(sources.some((source) => source.url === 'https://filemoon.sx/e/2'));
  assert.equal(sources.filter((source) => source.url === 'https://voe.sx/e/1').length, 1);
  assert.ok(sources.findIndex((source) => source.name === 'Voe VF')
    < sources.findIndex((source) => source.name === 'Filemoon VOSTFR'));
});

test('Onyx TMDb FR does not add fallback servers when Frembed already returned links', async () => {
  const providers = [
    { id: '1jour1film', run: async () => [] },
    { id: 'frenchstream', run: async () => [] },
    { id: 'frembed', run: async () => [{ name: 'Voe (VF)', url: 'https://voe.sx/e/1' }] },
  ];

  const sources = await searchOnyxTmdbFr({
    type: 'movie', title: 'Example', tmdb_id: 123,
  }, 'test-request', providers);

  assert.deepEqual(sources.map((source) => source.url), ['https://voe.sx/e/1']);
});

test('Onyx TMDb FR still returns TMDB fallback servers if all catalogue providers fail', async () => {
  const providers = ['1jour1film', 'frenchstream', 'frembed'].map((id) => ({
    id,
    run: async () => { throw new Error(`${id} unavailable`); },
  }));

  const sources = await searchOnyxTmdbFr({
    type: 'movie', title: 'Example', tmdb_id: 123,
  }, 'test-request', providers);

  assert.equal(sources.length, 5);
  assert.ok(sources.every((source) => source.fallback === true));
});

test('Onyx TMDb FR reports an error when all providers fail and no TMDB fallback can be built', async () => {
  const providers = ['1jour1film', 'frenchstream', 'frembed'].map((id) => ({
    id,
    run: async () => { throw new Error(`${id} unavailable`); },
  }));

  await assert.rejects(
    searchOnyxTmdbFr({ type: 'movie', title: 'Example' }, 'test-request', providers),
    /All Onyx TMDb French source providers failed/,
  );
});

test('Onyx source sorting keeps VOSTFR after French sources and removes URL fragments', () => {
  const sources = normalizeAndSortSources([
    { name: 'Filemoon VOSTFR', url: 'https://filemoon.sx/e/2#player' },
    { name: 'Other VF', url: 'https://example.com/e/3' },
    { name: 'Voe VF', url: 'https://voe.sx/e/1' },
    { name: 'Duplicate', url: 'https://example.com/e/3#second' },
  ]);

  assert.equal(sources.length, 3);
  assert.equal(sources[0].name, 'Voe VF');
  assert.equal(sources[1].name, 'Other VF');
  assert.equal(sources[2].name, 'Filemoon VOSTFR');
});
