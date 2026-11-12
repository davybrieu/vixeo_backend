const test = require('node:test');
const assert = require('node:assert/strict');

const { findExtractor } = require('../src/extractors');
const { unpackPackedJs } = require('../src/lib/packed');
const { findVideoUrl } = require('../src/extractors/gupload');
const { decodeXorPayload } = require('../src/extractors/hxfile');
const { decodeEscapes } = require('../src/extractors/upzur');
const { createSource } = require('../src/extractors/vidply');

test('registers the additional Streamflix extractor batch', () => {
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
    ['https://www.yourupload.com/watch/abc', 'yourupload'],
    ['https://yucache.net/embed/abc', 'yourupload'],
    ['https://player.zilla-networks.com/abc', 'zilla'],
    ['https://vtbe.to/e/abc', 'vtube'],
    ['https://vtube.to/e/abc', 'vtube'],
    ['https://videoapp.zip/e/movie/123', 'videoapp'],
    ['https://watch.gxplayer.xyz/e/abc', 'gxplayer'],
    ['https://hexload.com/embed-abc.html', 'hexload'],
    ['https://my.mail.ru/video/embed/123456', 'mailru'],
    ['https://ok.ru/video/123456', 'okru'],
    ['https://myfilestorage.xyz/movie.mp4', 'myfilestorage'],
    ['https://moviesapi.club/movie/123', 'moviesapi'],
    ['https://strmup.to/v/abc123', 'streamup'],
    ['https://upzur.com/e/abc', 'upzur'],
    ['https://vidflix.club/api/movie/123', 'vidflix'],
    ['https://www.2embed.cc/embed/123', 'twoembed'],
    ['https://deltabit.co/abc123', 'deltabit'],
    ['https://lamovie.link/e/abc', 'lamovie'],
    ['https://vimeos.net/e/abc', 'lamovie'],
    ['https://loadx.ws/e/abc', 'loadx'],
    ['https://einschalten.in/api/movies/123/watch', 'einschalten'],
    ['https://u.pcloud.link/publink/show?code=abc', 'pcloud'],
    ['https://ridoo.net/e/abc', 'ridoo'],
    ['https://sharecloudy.com/e/abc', 'sharecloudy'],
    ['https://streamix.so/v/abc', 'streamix'],
    ['https://stmix.io/v/abc', 'streamix'],
    ['https://vidply.com/e/abc', 'vidply'],
    ['https://vidnest.io/e/abc', 'vidnest'],
    ['https://vidnest.live/e/abc', 'vidnest'],
    ['https://turbovid.me/abc', 'turbovid'],
    ['https://www.dailymotion.com/video/x8abcde', 'dailymotion'],
    ['https://geo.dailymotion.com/player/xtv3w.html?video=x8abcde', 'dailymotion'],
    ['https://www.amazon.com/clouddrive/shares/abc123', 'amazondrive'],
  ];
  for (const [url, name] of cases) assert.equal(findExtractor(url)?.name, name, url);
});

test('extracts a direct Gupload videoUrl config', () => {
  assert.equal(findVideoUrl('<script>const config = {"videoUrl":"https://cdn.example/video.m3u8"};</script>'), 'https://cdn.example/video.m3u8');
});

test('decodes a basic Dean Edwards packed payload', () => {
  const packed = "eval(function(p,a,c,k,e,d){return p}('0 1 2',3,3,'file|:|https://cdn.example/video.m3u8'.split('|'),0,{}))";
  assert.equal(unpackPackedJs(packed), 'file : https://cdn.example/video.m3u8');
});

test('decodes the Gupload legacy XOR payload', () => {
  const key = 'key';
  const plain = '{"videoUrl":"https://cdn.example/video.mp4"}';
  const bytes = Buffer.from([...plain].map((char, index) => char.charCodeAt(0) ^ key.charCodeAt(index % key.length)));
  const encoded = '~' + bytes.toString('base64');
  assert.equal(decodeXorPayload(encoded.split('~')[1], key), plain);
});
test('decodes UpZur hexadecimal and unicode escapes', () => { assert.equal(decodeEscapes('https:\\x2f\\x2fcdn.example\\u002fvideo.m3u8'), 'https://cdn.example/video.m3u8'); });

test('VidPly appends a token to the pass_md5 prefix', () => { const url = createSource('https://cdn.example/video~', 'token'); assert.match(url, /^https:\/\/cdn\.example\/video~[A-Za-z0-9]{10}\?token=token&expiry=\d+$/); });
