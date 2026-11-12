const { searchFrenchStream } = require('./frenchstream');
const { searchFrembed } = require('./frembed');
const { searchUnJourUnFilm } = require('./1jour1film');

const MAZQUEST_BASE_URL = 'https://embed.maz.quest';

const SOURCE_PROVIDERS = [
  { id: '1jour1film', run: searchUnJourUnFilm },
  { id: 'frenchstream', run: searchFrenchStream },
  { id: 'frembed', run: searchFrembed },
  { id: 'mazquest', run: searchMazQuest },
];

function positiveInteger(value) {
  const number = Number.parseInt(value, 10);
  return Number.isInteger(number) && number > 0 ? number : null;
}

/**
 * The fallback servers are the same TMDB-ID based servers created by
 * Onyx's TmdbProvider when Frembed returns no links.
 */
function buildMazQuestEpisodeUrl(payload = {}) {
  const tmdbId = String(payload.tmdb_id ?? '').trim();
  const season = positiveInteger(payload.season);
  const episode = positiveInteger(payload.episode);
  const isNumericId = tmdbId.length > 0 && [...tmdbId].every((char) => char >= '0' && char <= '9');

  if (payload.type !== 'tv' || !isNumericId || !season || !episode) return null;

  return `${MAZQUEST_BASE_URL}/tv/api/${tmdbId}/${String(season).padStart(2, '0')}/${String(episode).padStart(2, '0')}`;
}

async function searchMazQuest(payload = {}, fetchImpl = fetch) {
  const url = buildMazQuestEpisodeUrl(payload);
  if (!url) return [];

  const response = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    signal: AbortSignal.timeout(12000),
  });

  if (!response.ok) {
    throw new Error(`MazQuest API returned HTTP ${response.status}`);
  }

  const data = await response.json();
  if (data?.error === true || !Array.isArray(data?.links) || data.links.length === 0) {
    return [];
  }

  return [{
    provider: 'mazquest',
    name: 'Yandex VF (Maz)',
    url,
  }];
}

function buildFallbackSources(payload = {}) {
  const tmdbId = String(payload.tmdb_id ?? '').trim();
  if (!/^\d+$/.test(tmdbId)) return [];

  const type = payload.type === 'tv' ? 'tv' : 'movie';
  const season = positiveInteger(payload.season);
  const episode = positiveInteger(payload.episode);

  if (type === 'tv' && (!season || !episode)) return [];

  const urls = type === 'movie'
    ? [
      ['VixSrc', `https://vixsrc.to/api/movie/${tmdbId}`],
      ['VidsrcNet', `https://vsembed.ru/embed/movie?tmdb=${tmdbId}`],
      ['VidLink', `https://vidlink.pro/movie/${tmdbId}`],
      ['VidsrcRu', `https://vidsrc.ru/movie/${tmdbId}`],
      ['TwoEmbed', `https://www.2embed.cc/embed/${tmdbId}`],
    ]
    : [
      ['VixSrc', `https://vixsrc.to/api/tv/${tmdbId}/${season}/${episode}`],
      ['VidsrcNet', `https://vsembed.ru/embed/tv?tmdb=${tmdbId}&season=${season}&episode=${episode}`],
      ['VidLink', `https://vidlink.pro/tv/${tmdbId}/${season}/${episode}`],
      ['VidsrcRu', `https://vidsrc.ru/tv/${tmdbId}/${season}/${episode}`],
      ['TwoEmbed', `https://www.2embed.cc/embedtv/${tmdbId}?s=${season}&e=${episode}`],
    ];

  return urls.map(([name, url]) => ({
    provider: 'tmdb-fr',
    name,
    url,
    fallback: true,
  }));
}

function isVostfr(source) {
  const value = `${source.language || ''} ${source.name || ''}`.toLowerCase();
  return /\b(vostfr|vost)\b|(?:^|[\s[(])vo(?:$|[\s)\]])/.test(value);
}

function sourcePriority(source) {
  const value = `${source.name || ''} ${source.url || ''}`.toLowerCase();
  if (['vidzy', 'dood', 'voe', 'filemoon', 'filmoon', 'netu', 'sharecloudy', 'playmogo']
    .some((name) => value.includes(name))) return 0;
  if (['premium', 'fsvid', 'uqload'].some((name) => value.includes(name))) return 2;
  return 1;
}

function normalizeAndSortSources(sources) {
  const unique = new Map();

  for (const source of sources) {
    if (!source || typeof source.url !== 'string' || !/^https?:\/\//i.test(source.url)) continue;

    let key;
    try {
      const parsed = new URL(source.url);
      parsed.hash = '';
      key = parsed.href.toLowerCase();
    } catch {
      continue;
    }

    if (!unique.has(key)) {
      unique.set(key, {
        ...source,
        name: source.name || source.provider || 'Source',
      });
    }
  }

  return [...unique.values()].sort((a, b) => {
    const languageOrder = Number(isVostfr(a)) - Number(isVostfr(b));
    return languageOrder || sourcePriority(a) - sourcePriority(b);
  });
}

function logSourceProvider(event, provider, payload, requestId, details = {}) {
  console.log(JSON.stringify({
    timestamp: new Date().toISOString(),
    requestId: requestId || null,
    event,
    provider,
    search: {
      type: payload.type,
      title: payload.title,
      originalTitle: payload.originalTitle,
      year: payload.year,
      tmdbId: payload.tmdb_id,
      ...(payload.type === 'tv' ? { season: payload.season, episode: payload.episode } : {}),
    },
    ...details,
  }));
}

/**
 * Reproduces the TMDb (fr) source-selection flow from Onyx:
 * run its French catalogue providers in parallel, query Frembed with the
 * selected TMDB ID, and add Onyx's TMDB-ID fallback servers when Frembed
 * has no result. The normal Vixeo extractor registry resolves the returned
 * player URLs afterwards.
 */
async function searchOnyxTmdbFr(payload, requestId, sourceProviders = SOURCE_PROVIDERS) {
  const outcomes = await Promise.all(sourceProviders.map(async (provider) => {
    const startedAt = Date.now();
    logSourceProvider('ONYX_SOURCE_PROVIDER_STARTED', provider.id, payload, requestId);

    try {
      const sources = await provider.run(payload);
      if (!Array.isArray(sources)) {
        throw new TypeError(`Provider ${provider.id} returned an invalid result (expected an array).`);
      }

      logSourceProvider(
        sources.length ? 'ONYX_SOURCE_PROVIDER_SOURCES_FOUND' : 'ONYX_SOURCE_PROVIDER_NO_SOURCE_FOUND',
        provider.id,
        payload,
        requestId,
        { durationMs: Date.now() - startedAt, sourceCount: sources.length },
      );

      return { id: provider.id, status: 'fulfilled', sources };
    } catch (error) {
      logSourceProvider('ONYX_SOURCE_PROVIDER_FAILED', provider.id, payload, requestId, {
        durationMs: Date.now() - startedAt,
        error: error.message,
      });
      return { id: provider.id, status: 'rejected', error };
    }
  }));

  const allFailed = outcomes.every((outcome) => outcome.status === 'rejected');
  const frembed = outcomes.find((outcome) => outcome.id === 'frembed');
  const frembedSources = frembed?.status === 'fulfilled' ? frembed.sources : [];

  let sources = outcomes
    .filter((outcome) => outcome.status === 'fulfilled')
    .flatMap((outcome) => outcome.sources.map((source) => ({
      ...source,
      provider: source.provider || outcome.id,
      sourceProvider: outcome.id,
    })));

  let fallbackSources = [];
  if (frembedSources.length === 0) {
    fallbackSources = buildFallbackSources(payload);
    sources.push(...fallbackSources);
  }

  if (allFailed && fallbackSources.length === 0) {
    const errors = outcomes.map((outcome) => outcome.error);
    throw new AggregateError(errors, 'All Onyx TMDb French source providers failed.');
  }

  const normalized = normalizeAndSortSources(sources);

  logSourceProvider(
    normalized.length ? 'ONYX_TMDB_FR_SOURCES_READY' : 'ONYX_TMDB_FR_NO_SOURCES',
    'tmdb-fr',
    payload,
    requestId,
    {
      sourceCount: normalized.length,
      fallbackCount: fallbackSources.length,
      sources: normalized.map(({ provider, sourceProvider, name, url, fallback }) => ({
        provider,
        sourceProvider: sourceProvider || null,
        name,
        url,
        ...(fallback ? { fallback: true } : {}),
      })),
    },
  );

  return normalized;
}

module.exports = {
  searchOnyxTmdbFr,
  buildFallbackSources,
  buildMazQuestEpisodeUrl,
  searchMazQuest,
  normalizeAndSortSources,
};
