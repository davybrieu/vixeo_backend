const test = require('node:test');
const assert = require('node:assert/strict');
const { normalizeTitle, matchesTitle } = require('../src/providers/title-matcher');

const {
  extractFrenchStreamId,
  isFrenchStreamSeries,
  normalizeSearchTitle,
  matchesRequestedTitle,
  normalizePlayerEntries,
  normalizeEpisodeEntries,
} = require('../src/providers/frenchstream');
const { resolveFrembedLink, searchFrembed } = require('../src/providers/frembed');
const { getProviderErrorEvent } = require('../src/provider-errors');
const {
  normalizeBaseUrl,
  extractLoadVideoUrl,
  parseWiflixResults,
  parseMovieServers,
  parseEpisodeServers,
} = require('../src/providers/wiflix');

test('FrenchStream extracts the identifier from the Kotlin onclick format', () => {
  assert.equal(extractFrenchStreamId("open('/newsid=12345')"), '12345');
  assert.equal(extractFrenchStreamId("location.href='https://fs.example/index.php?newsid=98765&x=1'"), '98765');
  assert.equal(extractFrenchStreamId("open('/film-en-streaming/resident-evil.html')"), '');
  assert.equal(extractFrenchStreamId("open('/newsid-54321.html')"), '54321');
  assert.equal(extractFrenchStreamId(''), '');
  assert.equal(isFrenchStreamSeries('L’Odyssée - Saison 1'), true);
  assert.equal(isFrenchStreamSeries('L’Odyssée (2026)'), false);
});

test('FrenchStream requires an exact normalized title match and checks an available year', () => {
  assert.equal(normalizeSearchTitle("L’Odyssée (2026)"), 'odyssee');
  assert.equal(matchesRequestedTitle("L’Odyssée (2026)", {
    title: 'Odyssée',
    originalTitle: 'The Odyssey',
    year: 2026,
  }), true);
  assert.equal(matchesRequestedTitle("L’Odyssée (1997)", {
    title: 'Odyssée',
    originalTitle: 'The Odyssey',
    year: 2026,
  }), false);
  assert.equal(matchesRequestedTitle("L’Odyssée d’un roi (2026)", {
    title: 'Odyssée',
    originalTitle: 'The Odyssey',
    year: 2026,
  }), false);
  assert.equal(matchesRequestedTitle('Un autre film', {
    title: 'Odyssée',
    originalTitle: 'The Odyssey',
    year: 2026,
  }), false);
});

test('FrenchStream maps movie players, language labels and default links without duplicates', () => {
  const sources = normalizePlayerEntries({
    voe: { vff: 'https://voe.example/a', vostfr: 'https://voe.example/a', default: 'https://voe.example/b' },
    'Dood.Stream': { vfq: 'https://dood.example/bigwar5/skip' },
    broken: { vo: 'not-a-url' },
  });
  assert.deepEqual(sources.map(source => source.url), [
    'https://voe.example/a',
    'https://voe.example/b',
  ]);
  assert.equal(sources[0].name, 'Voe (TrueFrench)');
  assert.equal(sources[1].name, 'Voe');
});

test('FrenchStream maps requested episode sources and all episodes when no number is provided', () => {
  const data = {
    vf: { '1': { voe: 'https://voe.example/1' }, '2': { voe: 'https://voe.example/2' } },
    vostfr: { '2': { filemoon: 'https://filemoon.example/2' } },
  };
  assert.deepEqual(
    normalizeEpisodeEntries(data, 2).map(source => source.url),
    ['https://voe.example/2', 'https://filemoon.example/2'],
  );
  assert.equal(normalizeEpisodeEntries(data).length, 3);
});

test('Wiflix normalizes its active domain and extracts loadVideo URLs', () => {
  assert.equal(normalizeBaseUrl('https://example.test/path'), 'https://example.test/path/');
  assert.equal(normalizeBaseUrl('javascript:alert(1)'), null);
  assert.equal(extractLoadVideoUrl("loadVideo('https://player.example/embed/1')"), 'https://player.example/embed/1');
});

test('Wiflix classifies only Kotlin-supported movie and series result paths', () => {
  const results = parseWiflixResults([
    { href: '/film-en-streaming/movie-one/', title: 'Movie' },
    { href: '/serie-en-streaming/show-one/', title: 'Show' },
    { href: '/other/unrelated/', title: 'Other' },
  ], 'https://flemmix.team/');
  assert.deepEqual(results.map(result => result.type), ['movie', 'tv']);
  assert.equal(results[0].id, 'movie-one');
});

test('Wiflix maps movie and episode servers and excludes ignored hosts', () => {
  const movies = parseMovieServers([
    { name: 'VOE', onclick: "loadVideo('https://voe.example/embed/1')" },
    { name: 'Netu', onclick: "loadVideo('https://netu.example/embed/1')" },
  ]);
  assert.equal(movies.length, 1);
  assert.equal(movies[0].url, 'https://voe.example/embed/1');

  const episodes = parseEpisodeServers([{
    label: 'Episode 2',
    anchors: [
      { name: 'Vudeo', onclick: "loadVideo('https://vudeo.example/1')" },
      { name: 'VOE', onclick: "loadVideo('https://voe.example/2')" },
    ],
  }]);
  assert.equal(episodes.length, 1);
  assert.match(episodes[0].name, /Episode 2/);
});


test('Shared provider matcher rejects partial and unrelated movie titles', () => {
  const requested = { title: 'L’Odyssée', originalTitle: 'The Odyssey', year: 2026 };
  assert.equal(normalizeTitle('L’Odyssée (2026)'), 'odyssee');
  assert.equal(matchesTitle('L’Odyssée (2026)', requested), true);
  assert.equal(matchesTitle('The Odyssey (2026)', requested), true);
  assert.equal(matchesTitle('L’Odyssée d’un roi (2026)', requested), false);
  assert.equal(matchesTitle('Resident Evil (2021)', requested), false);
  assert.equal(matchesTitle('Odyssée (1997)', requested), false);
  assert.equal(matchesTitle('The Odysseys (2026)', requested), false);
});

test('Frembed resolves a player redirect to the actual extractor host', async () => {
  const originalFetch = global.fetch;
  global.fetch = async () => ({
    status: 302,
    headers: { get: (name) => name.toLowerCase() === 'location' ? 'https://voe.sx/e/abc123' : null },
  });
  try {
    assert.equal(
      await resolveFrembedLink('https://frembed.casa/player/123'),
      'https://voe.sx/e/abc123',
    );
  } finally {
    global.fetch = originalFetch;
  }
});


test('Frembed propagates API timeouts so the server reports a provider failure', async () => {
  const originalFetch = global.fetch;
  const timeout = new Error('The operation was aborted due to timeout');
  timeout.name = 'TimeoutError';
  global.fetch = async () => { throw timeout; };

  try {
    await assert.rejects(
      searchFrembed({ type: 'movie', tmdb_id: 1368337 }),
      (error) => error === timeout,
    );
    assert.equal(getProviderErrorEvent(timeout), 'PROVIDER_TIMEOUT');
  } finally {
    global.fetch = originalFetch;
  }
});

test('provider errors are classified as timeouts only when the error indicates a timeout', () => {
  assert.equal(getProviderErrorEvent(Object.assign(new Error('page.goto: Timeout 20000ms exceeded'), { name: 'Error' })), 'PROVIDER_TIMEOUT');
  assert.equal(getProviderErrorEvent(Object.assign(new Error('Connection reset'), { code: 'ECONNRESET' })), 'PROVIDER_SEARCH_FAILED');
});
