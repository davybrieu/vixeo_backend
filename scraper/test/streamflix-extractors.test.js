const test = require('node:test');
const assert = require('node:assert/strict');

const { findExtractor } = require('../src/extractors');
const { unpackPackedJs } = require('../src/lib/packed');
const { findVideoUrl } = require('../src/extractors/gupload');
const { decodeXorPayload } = require('../src/extractors/hxfile');

test('registers the first additional Streamflix extractor batch', () => {
  const cases = [
    ['https://bigwarp.cc/e/abc', 'bigwarp'],
    ['https://bigwarp.io/e/abc', 'bigwarp'],
    ['https://dropload.tv/e/abc', 'dropload'],
    ['https://dr0pstream.com/e/abc', 'dropload'],
    ['https://fastream.to/e/abc', 'fastream'],
    ['https://gupload.xyz/e/abc', 'gupload'],
    ['https://hxfile.co/embed-abc.html', 'hxfile'],
    ['https://xshotcok.com/embed-abc.html', 'hxfile'],
    ['https://oneupload.net/e/abc', 'oneupload'],
    ['https://tipfly.xyz/e/abc', 'oneupload'],
    ['https://pixeldrain.com/u/abc', 'pixeldrain'],
    ['https://savefiles.com/e/abc', 'savefiles'],
    ['https://streamhls.to/e/abc', 'savefiles'],
    ['https://streamhub.to/d/abc', 'streamhub'],
  ];
  for (const [url, name] of cases) assert.equal(findExtractor(url)?.name, name, url);
});

test('extracts a direct Gupload videoUrl config', () => {
  assert.equal(findVideoUrl('<script>const config = {"videoUrl":"https://cdn.example/video.m3u8"};</script>'), 'https://cdn.example/video.m3u8');
});

test('decodes a basic Dean Edwards packed payload', () => {
  const packed = 'eval(function(p,a,c,k,e,d){e=function(c){return c.toString(a)};while(c--){if(k[c])p=p.replace(new RegExp("\\\\b"+e(c)+"\\\\b","g"),k[c])}return p}(\\'0 1 2\\',3,3,\\'file|:|https://cdn.example/video.m3u8\\'.split(\\'|\\'),0,{}))';
  assert.equal(unpackPackedJs(packed), 'file : https://cdn.example/video.m3u8');
});

test('decodes the Gupload legacy XOR payload', () => {
  const key = 'key';
  const plain = '{"videoUrl":"https://cdn.example/video.mp4"}';
  const bytes = Buffer.from([...plain].map((char, index) => char.charCodeAt(0) ^ key.charCodeAt(index % key.length)));
  const encoded = '~' + bytes.toString('base64');
  assert.equal(decodeXorPayload(encoded.split('~')[1], key), plain);
});