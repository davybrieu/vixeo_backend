// Dean Edwards "p.a.c.k.e.r." unpacker (the eval(function(p,a,c,k,e,d){...})
// obfuscation used by streamwish/vidhide/streamruby/supervideo/... pages).
// Port of the `pf1` class from ONYX.

// Tokens are base-N with the most significant digit first, so the token is
// reversed before accumulating (least significant digit at index 0), matching
// the packer's own encoder `(c<a?'':e(parseInt(c/a)))+...`.
function baseNToInt(str, base, chars) {
  const reversed = str.split('').reverse().join('');
  let value = 0;
  for (let i = 0; i < reversed.length; i++) {
    const idx = chars.indexOf(reversed[i]);
    if (idx === -1) continue;
    value += idx * Math.pow(base, i);
  }
  return value;
}

function alphabetFor(base) {
  const alphanum = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  if (base > 36 && base < 62) return alphanum.slice(0, base);
  if (base >= 63 && base < 95) {
    return " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~".slice(0, base);
  }
  if (base === 95) {
    return " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~";
  }
  return alphanum;
}

// Returns the de-obfuscated script, or null when the input is not packed.
function unpack(source) {
  try {
    const header = /\}\s*\('(.*)',\s*(.*?),\s*(\d+),\s*'(.*?)'\.split\('\|'\)/s.exec(source);
    if (!header) return null;

    const payload = header[1].replace(/\\'/g, "'");
    const base = parseInt(header[2], 10) || 36;
    const count = parseInt(header[3], 10) || 0;
    const words = header[4].split('|');
    if (words.length !== count) return null;

    const alphabet = alphabetFor(base);
    const intToWord = (token) => {
      const idx = baseNToInt(token, base, alphabet);
      return idx >= 0 && idx < words.length ? words[idx] : null;
    };

    return payload.replace(/\b\w+\b/g, (word) => {
      const replacement = intToWord(word);
      return replacement && replacement.length ? replacement : word;
    });
  } catch (e) {
    return null;
  }
}

// Some players wrap the packer inside `eval(function(p,a,c,k,e,d){...})`.
function findPacked(html) {
  const m = /(eval\(function\(p,a,c,k,e,[rd]\).*?<\/script>)/s.exec(html);
  return m ? m[1] : null;
}

module.exports = { unpack, findPacked };
