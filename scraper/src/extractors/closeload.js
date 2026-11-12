const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['closeload.top'];

function parseCloseloadOperations(body) {
  const operations = [];
  const tokenRegex = /atob\s*\(|reverse\s*\(\s*\)|replace\s*\(\/\[a-zA-Z\]\/g[\s\S]*?%\s*26/g;
  for (const match of body.matchAll(tokenRegex)) {
    const token = match[0];
    if (/^atob/i.test(token)) operations.push({ type: 'atob' });
    else if (/^reverse/i.test(token)) operations.push({ type: 'reverse' });
    else {
      const offset = /\+\s*(\d+)\s*\)\s*%\s*26/.exec(token)?.[1];
      if (offset) operations.push({ type: 'rot', offset: Number(offset) });
    }
  }
  return operations;
}

function decodeCloseloadArray(parts, operations, accInit = 2, accAdd = 9) {
  let value = parts.join('').replace(/\\\//g, '/');
  let bytes = null;
  try {
    for (const operation of operations) {
      if (operation.type === 'atob') {
        bytes = Buffer.from(bytes ? bytes.toString('latin1') : value, 'base64');
        value = bytes.toString('latin1');
      } else if (operation.type === 'reverse') {
        value = [...(bytes ? bytes.toString('latin1') : value)].reverse().join('');
        bytes = null;
      } else if (operation.type === 'rot') {
        const input = bytes ? bytes.toString('latin1') : value;
        value = [...input].map(char => {
          const code = char.charCodeAt(0);
          if (code >= 97 && code <= 122) return String.fromCharCode(((code - 97 + operation.offset) % 26) + 97);
          if (code >= 65 && code <= 90) return String.fromCharCode(((code - 65 + operation.offset) % 26) + 65);
          return char;
        }).join('');
        bytes = null;
      }
    }
    const finalBytes = bytes || Buffer.from(value, 'latin1');
    let acc = accInit;
    const output = Buffer.alloc(finalBytes.length);
    for (let i = 0; i < finalBytes.length; i++) {
      const byte = finalBytes[i];
      acc = (acc + accAdd) % 256;
      output[i] = byte ^ acc;
      acc = (acc + byte) % 256;
    }
    const result = output.toString('latin1').trim();
    return result.startsWith('http') ? result : null;
  } catch {
    return null;
  }
}

async function extractCloseload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  let searchHtml = response.text;
  const evalRegex = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e/i;
  for (const match of response.text.matchAll(new RegExp(evalRegex.source, 'gi'))) {
    const chunk = response.text.slice(match.index, match.index + 5000);
    const unpacked = unpackPackedJs(chunk);
    if (unpacked) searchHtml += '\n' + unpacked;
  }
  const functionMatch = /function\s+(dc_[a-zA-Z0-9_]+)\(value_parts\)\s*\{([\s\S]*?return\s+unmix;?)\s*\}/i.exec(searchHtml);
  if (!functionMatch) return null;
  const functionName = functionMatch[1];
  const functionBody = functionMatch[2];
  const operations = parseCloseloadOperations(functionBody);
  let accInit = Number(/var\s+acc\s*=\s*(\d+)/i.exec(functionBody)?.[1] || 2);
  let accAdd = Number(/acc\s*=\s*\(\s*acc\s*\+\s*(\d+)\s*\)\s*%\s*256/i.exec(functionBody)?.[1] || 9);
  if (!Number.isFinite(accInit)) accInit = 2;
  if (!Number.isFinite(accAdd)) accAdd = 9;
  const callRegex = new RegExp(functionName + '\\(\\s*\\[\\s*((?:"[^"]+"\\s*,?\\s*)+)\\]\\s*\\)', 'g');
  for (const match of searchHtml.matchAll(callRegex)) {
    const parts = [...match[1].matchAll(/"([^"]+)"/g)].map(item => item[1]);
    const source = decodeCloseloadArray(parts, operations, accInit, accAdd);
    if (source) return { url: source, type: 'm3u8', headers: { Referer: parsed.origin + '/' } };
  }
  return null;
}

module.exports = { extractCloseload, parseCloseloadOperations, decodeCloseloadArray };