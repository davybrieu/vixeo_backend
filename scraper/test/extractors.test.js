const test = require('node:test');
const assert = require('node:assert/strict');
const { findExtractor, extractorNames, supportedDomains } = require('../src/extractors');

test('extractor registry exposes the expected Streamflix-compatible extractors', () => {
  for (const name of ['voe', 'vidhide', 'streamwish', 'rpmvid']) {
    assert.ok(extractorNames.includes(name), `missing extractor: ${name}`);
  }
});

test('Voe recognizes the two aliases added by Streamflix Reborn v1.7.231', () => {
  assert.equal(findExtractor('https://rebeccapracticeloss.com/e/example')?.name, 'voe');
  assert.equal(findExtractor('https://johnbeyondnation.com/e/example')?.name, 'voe');
});

test('existing Streamflix aliases remain registered', () => {
  assert.equal(findExtractor('https://hanerix.com/e/example')?.name, 'streamwish');
  assert.equal(findExtractor('https://morencius.com/e/example')?.name, 'vidhide');
});

test('Rpmvid mirror patterns match only the supported host families', () => {
  assert.equal(findExtractor('https://series.flemmix.upns.live/embed/123')?.name, 'rpmvid');
  assert.equal(findExtractor('https://video.example.org/embed/123'), null);
});

test('invalid URLs and lookalike host suffixes do not match an extractor', () => {
  assert.equal(findExtractor('not a url'), null);
  assert.equal(findExtractor('https://notvoe.sx.example.org/e/123'), null);
});

test('supported domains are exported as non-empty host names', () => {
  assert.ok(supportedDomains.length > 0);
  assert.ok(supportedDomains.every((domain) => typeof domain === 'string' && domain.length > 0));
});

test('Streamflix Goodstream and VidMoLy extractors are registered', () => {
  assert.equal(findExtractor('https://goodstream.one/e/example')?.name, 'goodstream');
  assert.equal(findExtractor('https://vidmoly.me/embed/example')?.name, 'vidmoly');
  assert.equal(findExtractor('https://vidmoly.org/embed/example')?.name, 'vidmoly');
  assert.equal(findExtractor('https://vidmoly.to/embed/example')?.name, 'vidmoly');
});
