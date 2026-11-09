// Thin HTTP wrapper around the global fetch (Node 18+) with a few helpers the
// extractors need: sensible headers, redirect following, cookies and timeouts.

const { DEFAULT_USER_AGENT } = require('./util');

async function request(url, options = {}) {
  const {
    method = 'GET',
    headers = {},
    body = undefined,
    timeout = 20000,
    redirect = 'follow',
  } = options;

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  try {
    const res = await fetch(url, {
      method,
      headers: {
        'User-Agent': DEFAULT_USER_AGENT,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'fr-FR,fr;q=0.9,en;q=0.8',
        ...headers,
      },
      body,
      redirect,
      signal: controller.signal,
    });

    const text = await res.text();
    const setCookies = typeof res.headers.getSetCookie === 'function' ? res.headers.getSetCookie() : [];

    return {
      ok: res.ok,
      status: res.status,
      url: res.url || url,
      headers: res.headers,
      setCookies,
      cookie: setCookies.map((c) => c.split(';')[0]).join('; '),
      text,
    };
  } finally {
    clearTimeout(timer);
  }
}

function getText(url, headers = {}, timeout = 20000) {
  return request(url, { headers, timeout });
}

function postForm(url, form, headers = {}, timeout = 20000) {
  const body = new URLSearchParams(form).toString();
  return request(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...headers },
    body,
    timeout,
  });
}

function postJson(url, json, headers = {}, timeout = 20000) {
  return request(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...headers },
    body: JSON.stringify(json),
    timeout,
  });
}

module.exports = { request, getText, postForm, postJson };
