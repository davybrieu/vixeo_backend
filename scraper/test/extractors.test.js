const test = require('node:test');
const assert = require('node:assert/strict');

const { normalizeApiVoirFilmItems } = require('../src/extractors/api-voirfilm');
const { parseOnRegardeOuScript } = require('../src/extractors/onregardeou');

test('ApiVoirFilm expands relative server URLs and ignores invalid entries', () => {
  const servers = normalizeApiVoirFilmItems([
    { name: 'VO', url: '/embed/one' },
    { name: 'Broken', url: 'javascript:alert(1)' },
    { name: 'VF', url: 'https://player.example/two' },
  ], 'https://api.voirfilm.cam/movie/1', 'FR ');
  assert.deepEqual(servers, [
    { name: 'FR VO', url: 'https://api.voirfilm.cam/embed/one' },
    { name: 'FR VF', url: 'https://player.example/two' },
  ]);
});

test('OnRegardeOu parses videoData server arrays and normalizes escaped URLs', () => {
  const script = 'const videoData={"servers":[{"name":"VF","url":"https:\\/\\/player.example\\/vf"},{"url":"/embed/vostfr"}]};';
  const servers = parseOnRegardeOuScript(script, 'https://onregardeou.site/watch/1', 'FR ');
  assert.deepEqual(servers, [
    { name: 'FR VF', url: 'https://player.example/vf' },
    { name: 'FR Server 2', url: 'https://onregardeou.site/embed/vostfr' },
  ]);
});

test('OnRegardeOu returns no servers for malformed or absent videoData', () => {
  assert.deepEqual(parseOnRegardeOuScript('window.player = true', 'https://onregardeou.site'), []);
  assert.deepEqual(parseOnRegardeOuScript('const videoData={bad};', 'https://onregardeou.site'), []);
});
