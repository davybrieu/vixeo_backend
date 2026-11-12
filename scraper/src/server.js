const http = require('http');
const { randomUUID } = require('crypto');
const { getProviderErrorEvent } = require('./provider-errors');
const { searchFrenchStream } = require('./providers/frenchstream');
const { searchWiflix } = require('./providers/wiflix');
const { searchFrembed } = require('./providers/frembed');
const { searchUnJourUnFilm } = require('./providers/1jour1film');
const { searchOnyxTmdbFr } = require('./providers/onyx-tmdb-fr');
const { extract, supportedDomains } = require('./extractors');

const PORT = process.env.SCRAPER_PORT || 3100;

// The providers the app can show. Each runs on its own so the UI can report a
// per-provider state (loading, failed, links found) instead of one global wait.
const PROVIDERS = [
  { id: 'tmdb-fr', name: 'TMDB Français (Onyx)', run: searchOnyxTmdbFr },
  { id: 'frenchstream', name: 'FrenchStream', run: searchFrenchStream },
  { id: 'wiflix', name: 'Wiflix', run: searchWiflix },
  { id: 'frembed', name: 'Frembed', run: searchFrembed },
  { id: '1jour1film', name: '1Jour1Film', run: searchUnJourUnFilm },
];

function searchContext(payload) {
  return {
    type: payload.type,
    title: payload.title,
    originalTitle: payload.originalTitle,
    year: payload.year,
    ...(payload.type === 'tv' ? { season: payload.season, episode: payload.episode } : {}),
    tmdbId: payload.tmdb_id,
  };
}

function logProviderEvent(level, event, provider, payload, requestId, details = {}) {
  const entry = {
    timestamp: new Date().toISOString(),
    requestId,
    event,
    provider: provider.id,
    providerName: provider.name,
    search: searchContext(payload),
    ...details,
  };
  const output = JSON.stringify(entry);
  if (level === 'error') console.error(output);
  else if (level === 'warn') console.warn(output);
  else console.log(output);
}

async function runProvider(provider, payload, requestId) {
  const startedAt = Date.now();
  logProviderEvent('info', 'PROVIDER_SEARCH_STARTED', provider, payload, requestId);

  try {
    const sources = await provider.run(payload, requestId);
    if (!Array.isArray(sources)) {
      throw new TypeError(`Provider ${provider.id} returned an invalid result (expected an array).`);
    }
    const durationMs = Date.now() - startedAt;

    if (sources.length === 0) {
      logProviderEvent('warn', 'PROVIDER_NO_SOURCE_FOUND', provider, payload, requestId, {
        durationMs,
        reason: 'Provider completed successfully but returned no source. The title, year, season or episode may not exist on this provider, or its page structure may have changed.',
      });
    } else {
      logProviderEvent('info', 'PROVIDER_SOURCES_FOUND', provider, payload, requestId, {
        durationMs,
        sourceCount: sources.length,
      });
    }

    return { id: provider.id, name: provider.name, status: 'ok', count: sources.length, sources };
  } catch (e) {
    const event = getProviderErrorEvent(e);
    logProviderEvent('error', event, provider, payload, requestId, {
      durationMs: Date.now() - startedAt,
      error: e.message,
      stack: e.stack,
    });
    return { id: provider.id, name: provider.name, status: 'error', error: e.message, count: 0, sources: [] };
  }
}

async function handleSources(body) {
  const type = body.type === 'tv' ? 'tv' : 'movie';
  const title = body.title || '';
  const originalTitle = body.original_title || title;
  const year = body.year || null;
  const season = body.season || null;
  const episode = body.episode || null;
  const tmdb_id = body.tmdb_id || null;
  const doExtract = body.extract === true;

  const selected = body.provider === 'all'
    ? PROVIDERS
    : body.provider
      ? PROVIDERS.filter((p) => p.id === body.provider)
      : PROVIDERS.filter((p) => p.id === 'tmdb-fr');

  const payload = { type, title, originalTitle, year, season, episode, tmdb_id };
  const requestId = randomUUID();
  console.log(JSON.stringify({
    timestamp: new Date().toISOString(),
    event: 'SCRAPER_REQUEST_STARTED',
    requestId,
    scope: body.provider || 'tmdb-fr',
    providerCount: selected.length,
    search: searchContext(payload),
  }));
  const results = await Promise.all(selected.map((provider) => runProvider(provider, payload, requestId)));

  const seenSources = new Set();
  let sources = results.flatMap((r) => r.sources).filter((source) => {
    try {
      const parsed = new URL(source.url);
      parsed.hash = '';
      const key = parsed.href.toLowerCase();
      if (seenSources.has(key)) return false;
      seenSources.add(key);
      return true;
    } catch {
      return false;
    }
  });

  if (doExtract) {
    sources = await Promise.all(sources.map(async (s) => {
      try {
        const extracted = await extract(s.url);
        if (!extracted) return s;
        return {
          ...s,
          stream: extracted.url,
          streamType: extracted.type,
          extractor: extracted.extractor,
          ...(extracted.headers ? { headers: extracted.headers } : {}),
          ...(extracted.subtitles && extracted.subtitles.length ? { subtitles: extracted.subtitles } : {}),
        };
      } catch (e) {
        return s;
      }
    }));
  }

  return {
    sources,
    providers: results.map(({ id, name, status, error, count }) => ({ id, name, status, error: error || null, count })),
  };
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'GET') {
    const requestUrl = new URL(req.url, 'http://127.0.0.1');
  }
  if (req.method === 'GET' && req.url === '/providers') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      providers: PROVIDERS.map(({ id, name }) => ({ id, name })),
    }));
    return;
  }

  if (req.method === 'GET' && req.url === '/extractors') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ supported_domains: supportedDomains }));
    return;
  }

  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true }));
    return;
  }

  if (req.method === 'POST' && req.url === '/sources') {
    let raw = '';
    for await (const chunk of req) raw += chunk;
    try {
      const body = JSON.parse(raw || '{}');
      const data = await handleSources(body);
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify(data));
    } catch (e) {
      console.error(e);
      res.writeHead(500, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: e.message, sources: [] }));
    }
    return;
  }

  if (req.method === 'POST' && req.url === '/extract') {
    let raw = '';
    for await (const chunk of req) raw += chunk;
    try {
      const body = JSON.parse(raw || '{}');
      const result = await extract(body.url);
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify(result || { error: 'No extractor matched or extraction failed' }));
    } catch (e) {
      console.error(e);
      res.writeHead(500, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: e.message }));
    }
    return;
  }

  res.writeHead(404);
  res.end();
});

server.listen(PORT, () => console.log(`Vixeo scraper on :${PORT}`));
