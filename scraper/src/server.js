const http = require('http');
const { searchFrenchStream } = require('./providers/frenchstream');
const { searchWiflix } = require('./providers/wiflix');
const { searchFrembed } = require('./providers/frembed');
const { extract } = require('./extractors');

const PORT = process.env.SCRAPER_PORT || 3100;

async function handleSources(body) {
  const type = body.type === 'tv' ? 'tv' : 'movie';
  const title = body.title || '';
  const originalTitle = body.original_title || title;
  const year = body.year || null;
  const season = body.season || null;
  const episode = body.episode || null;
  const tmdb_id = body.tmdb_id || null;
  const doExtract = body.extract === true;

  const jobs = [
    searchFrenchStream({ type, title, originalTitle, year, season, episode }).catch((e) => {
      console.error('FrenchStream:', e.message);
      return [];
    }),
    searchWiflix({ type, title, originalTitle, year, season, episode }).catch((e) => {
      console.error('Wiflix:', e.message);
      return [];
    }),
    searchFrembed({ type, tmdb_id, season, episode }).catch((e) => {
      console.error('Frembed:', e.message);
      return [];
    }),
  ];

  const results = await Promise.all(jobs);
  let sources = results.flat();

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

  return { sources };
}

const server = http.createServer(async (req, res) => {
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
