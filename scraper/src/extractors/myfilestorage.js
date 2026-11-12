const { request } = require('../lib/http');

async function extractMyFileStorage(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'myfilestorage.xyz') return null;
  const referer = 'https://bflix.gs/';
  const check = async candidate => {
    const response = await request(candidate, { method: 'HEAD', headers: { Referer: referer }, timeout: 15000 });
    return response.ok;
  };
  let source = url;
  if (!(await check(source))) {
    const fallback = url.replace(/\.mp4(?=($|\?))/, '-1.mp4');
    if (fallback === url || !(await check(fallback))) return null;
    source = fallback;
  }
  return { url: source, type: 'mp4', headers: { Referer: referer } };
}
module.exports = { extractMyFileStorage };