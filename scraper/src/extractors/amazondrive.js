const { getText } = require('../lib/http');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

async function extractAmazonDrive(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['amazon.com', 'www.amazon.com'].includes(parsed.hostname)) return null;
  const shareId = /\/shares\/([^/]+)/i.exec(parsed.pathname)?.[1];
  if (!shareId) return null;
  const headers = {
    Accept: 'application/json, text/plain, */*',
    'Accept-Language': 'en-US,en;q=0.9',
    'User-Agent': USER_AGENT,
    Referer: 'https://www.amazon.com/clouddrive',
  };
  const shareUrl = new URL('/drive/v1/shares/' + encodeURIComponent(shareId), 'https://www.amazon.com');
  shareUrl.search = new URLSearchParams({ resourceVersion: 'V2', ContentType: 'JSON', asset: 'ALL' });
  const share = await getText(shareUrl.href, headers);
  if (!share.ok) return null;
  let shareJson;
  try { shareJson = JSON.parse(share.text); } catch { return null; }
  const nodeId = shareJson?.nodeInfo?.id;
  if (!nodeId) return null;
  const childrenUrl = new URL('/drive/v1/nodes/' + encodeURIComponent(nodeId) + '/children', 'https://www.amazon.com');
  childrenUrl.search = new URLSearchParams({
    resourceVersion: 'V2', ContentType: 'JSON', limit: '200',
    sort: '["kind DESC", "modifiedDate DESC"]', asset: 'ALL', tempLink: 'true', shareId,
  });
  const children = await getText(childrenUrl.href, headers);
  if (!children.ok) return null;
  let childrenJson;
  try { childrenJson = JSON.parse(children.text); } catch { return null; }
  const tempLink = childrenJson?.data?.find(item => typeof item?.tempLink === 'string')?.tempLink;
  return tempLink ? { url: tempLink } : null;
}
module.exports = { extractAmazonDrive };