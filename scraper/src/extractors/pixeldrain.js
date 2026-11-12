const { mediaType } = require('../lib/util');

async function extractPixeldrain(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'pixeldrain.com') return null;
  const id = parsed.pathname.match(/^\/(?:u|l)\/([^/]+)/)?.[1];
  if (!id) return null;
  const source = 'https://pixeldrain.com/api/file/' + encodeURIComponent(id);
  return {
    url: source,
    type: 'mp4',
    headers: {
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      Referer: url,
      Origin: 'https://pixeldrain.com',
      Connection: 'keep-alive',
    },
  };
}
module.exports = { extractPixeldrain };