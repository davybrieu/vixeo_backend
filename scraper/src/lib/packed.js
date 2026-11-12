// Minimal Dean Edwards P.A.C.K.E.R. decoder used by several Streamflix hosts.
// It only decodes the packed payload; it never evaluates the remote script.
function decodeJsString(value, quote) {
  if (quote === '"') {
    try { return JSON.parse('"' + value.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"'); }
    catch { return value.replace(/\\(['"\\])/g, '$1'); }
  }
  return value.replace(/\\x([0-9a-f]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)))
    .replace(/\\u([0-9a-f]{4})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)))
    .replace(/\\(['"\\])/g, '$1');
}

function encodeBase(value, radix) {
  const alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  let number = value;
  let result = '';
  do {
    result = alphabet[number % radix] + result;
    number = Math.floor(number / radix);
  } while (number > 0);
  return result;
}

function unpackPackedJs(script) {
  const source = String(script || '');
  const match = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)\s*\{[\s\S]*?\}\s*\(\s*(['"])((?:\\.|(?!\1)[\s\S])*)\1\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(['"])((?:\\.|(?!\5)[\s\S])*)\5\.split\(\s*(['"])\|\7\s*\)/i.exec(source);
  if (!match) return null;

  const payload = decodeJsString(match[2], match[1]);
  const radix = Number(match[3]);
  const count = Number(match[4]);
  const dictionary = decodeJsString(match[6], match[5]).split('|');
  if (!Number.isInteger(radix) || radix < 2 || radix > 62 || !Number.isInteger(count) || count < 1) return null;

  return payload.replace(/\b\w+\b/g, token => {
    let index = 0;
    const alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for (const char of token) {
      const digit = alphabet.indexOf(char);
      if (digit < 0 || digit >= radix) return token;
      index = index * radix + digit;
    }
    return index < count && dictionary[index] ? dictionary[index] : token;
  });
}

module.exports = { unpackPackedJs };