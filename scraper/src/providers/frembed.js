const BASE = 'https://frembed.casa';

async function searchFrembed({ type, tmdb_id, season, episode }) {
  if (!tmdb_id) return [];

  const sources = [];
  try {
    let url;
    if (type === 'movie') {
      url = `${BASE}/api/films?id=${tmdb_id}&idType=tmdb`;
    } else if (type === 'tv' && season && episode) {
      url = `${BASE}/api/series?id=${tmdb_id}&sa=${season}&epi=${episode}&idType=tmdb`;
    } else {
      return [];
    }

    const res = await fetch(url, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept': 'application/json',
        'Referer': BASE + '/',
      },
    });

    if (!res.ok) return [];
    const data = await res.json();

    const add = (link, lang) => {
      if (typeof link === 'string' && link.startsWith('http')) {
        sources.push({
          provider: 'frembed',
          name: `Frembed (${lang})`,
          url: link,
        });
      }
    };

    // New format with links array
    if (Array.isArray(data.links)) {
      for (const l of data.links) {
        if (l?.url) {
          const lang = (l.lang || 'vf').toUpperCase();
          add(l.url, lang);
        }
      }
    }

    // Legacy format
    const langs = [
      ['link1', 'VF'], ['link2', 'VF'], ['link3', 'VF'], ['link4', 'VF'], ['link5', 'VF'], ['link6', 'VF'], ['link7', 'VF'],
      ['link1vostfr', 'VOSTFR'], ['link2vostfr', 'VOSTFR'], ['link3vostfr', 'VOSTFR'], ['link4vostfr', 'VOSTFR'],
      ['link5vostfr', 'VOSTFR'], ['link6vostfr', 'VOSTFR'], ['link7vostfr', 'VOSTFR'],
      ['link1vo', 'VO'], ['link2vo', 'VO'], ['link3vo', 'VO'], ['link4vo', 'VO'], ['link5vo', 'VO'], ['link6vo', 'VO'], ['link7vo', 'VO'],
    ];
    for (const [key, lang] of langs) {
      add(data[key], lang);
    }
  } catch (e) {
    console.error('Frembed error:', e.message);
  }

  return sources;
}

module.exports = { searchFrembed };
