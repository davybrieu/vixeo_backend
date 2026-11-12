const { getText } = require('../lib/http');

const API_KEY = 'AIzaSyDVQw45DwoYh632gvsP5vPDqEKvb-Ywnb8';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';
const CLIENT_DETAILS = 'appVersion=5.0%20(Windows%20NT%2010.0%3B%20Win64%3B%20x64)%20AppleWebKit%2F537.36%20(KHTML%2C%20like%20Gecko)%20Chrome%2F142.0.0.0%20Safari%2F537.36&platform=Win32&userAgent=Mozilla%2F5.0%20(Windows%20NT%2010.0%3B%20Win64%3B%20x64)%20AppleWebKit%2F537.36%20(KHTML%2C%20like%20Gecko)%20Chrome%2F142.0.0.0%20Safari%2F537.36';

async function extractGoogleDrive(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['drive.google.com', 'www.drive.google.com'].includes(parsed.hostname)) return null;
  const fileId = /\/file\/d\/([^/]+)/i.exec(parsed.pathname)?.[1];
  if (!fileId) return null;
  const endpoint = new URL('/v1/drive/media/' + encodeURIComponent(fileId) + '/playback', 'https://content-workspacevideo-pa.googleapis.com');
  endpoint.search = new URLSearchParams({ key: API_KEY, unique: 'gc999' });
  const response = await getText(endpoint.href, {
    Accept: '*/*',
    'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control': 'no-cache',
    Origin: 'https://drive.google.com',
    Pragma: 'no-cache',
    Priority: 'u=1, i',
    Referer: 'https://drive.google.com/',
    'sec-ch-ua': '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
    'sec-ch-ua-mobile': '?0',
    'sec-ch-ua-platform': '"Windows"',
    'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'cross-site',
    'User-Agent': USER_AGENT,
    'X-Browser-Channel': 'stable',
    'X-Browser-Copyright': 'Copyright 2025 Google LLC. All Rights reserved.',
    'X-Browser-Validation': 'Aj9fzfu+SaGLBY9Oqr3S7RokOtM=',
    'X-Browser-Year': '2025',
    'X-ClientDetails': CLIENT_DETAILS,
    'X-Goog-Encode-Response-If-Executable': 'base64',
    'X-Javascript-User-Agent': 'google-api-javascript-client/1.1.0',
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/x-www-form-urlencoded',
  });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const manifest = json?.mediaStreamingData?.hlsManifestUrl;
  return typeof manifest === 'string' && manifest ? {
    url: manifest,
    type: 'm3u8',
    headers: {
      Accept: '*/*',
      'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
      'Cache-Control': 'no-cache',
      Connection: 'keep-alive',
      Origin: 'https://youtube.googleapis.com',
      Pragma: 'no-cache',
      Referer: 'https://youtube.googleapis.com/',
      'Sec-Fetch-Dest': 'empty',
      'Sec-Fetch-Mode': 'cors',
      'Sec-Fetch-Site': 'cross-site',
      'User-Agent': USER_AGENT,
      'X-Browser-Channel': 'stable',
      'X-Browser-Copyright': 'Copyright 2025 Google LLC. All Rights reserved.',
      'X-Browser-Validation': 'Aj9fzfu+SaGLBY9Oqr3S7RokOtM=',
      'X-Browser-Year': '2025',
      'sec-ch-ua': '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
      'sec-ch-ua-mobile': '?0',
      'sec-ch-ua-platform': '"Windows"',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  } : null;
}
module.exports = { extractGoogleDrive };