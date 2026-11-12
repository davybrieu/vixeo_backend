function normalizePublicKey(url) {
  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    return null;
  }

  if (parsed.protocol !== 'https:') return null;
  if (!['yadi.sk', 'disk.yandex.ru', 'disk.yandex.com'].includes(parsed.hostname.toLowerCase())) {
    return null;
  }

  parsed.hostname = 'yadi.sk';
  return parsed.href;
}

async function extractYandexDisk(url, fetchImpl = fetch) {
  const publicKey = normalizePublicKey(url);
  if (!publicKey) throw new Error('Yandex Disk: unsupported public URL');

  const endpoint = new URL('https://cloud-api.yandex.net/v1/disk/public/resources');
  endpoint.searchParams.set('public_key', publicKey);
  endpoint.searchParams.set('fields', 'file,name,mime_type,size,type');

  const response = await fetchImpl(endpoint.href, {
    headers: { Accept: 'application/json' },
    signal: AbortSignal.timeout(15000),
  });

  if (!response.ok) {
    throw new Error(`Yandex Disk: HTTP ${response.status}`);
  }

  const data = await response.json();
  if (data?.error) throw new Error(`Yandex Disk: ${data.error_description || data.error}`);
  if (data?.type !== 'file') {
    throw new Error(`Yandex Disk: shared resource is not a file (type=${data?.type || 'unknown'})`);
  }

  let fileUrl;
  try {
    fileUrl = new URL(data.file);
  } catch {
    throw new Error('Yandex Disk: no valid file URL in API response');
  }
  if (!['http:', 'https:'].includes(fileUrl.protocol)) {
    throw new Error('Yandex Disk: unsupported file URL protocol');
  }

  const isMp4 = String(data.mime_type || '').toLowerCase().includes('mp4')
    || fileUrl.pathname.toLowerCase().endsWith('.mp4');
  return { url: fileUrl.href, type: isMp4 ? 'mp4' : 'm3u8' };
}

module.exports = { extractYandexDisk, normalizePublicKey };
