const test = require('node:test');
const assert = require('node:assert/strict');

const { findExtractor } = require('../src/extractors');
const { unpackPackedJs } = require('../src/lib/packed');
const { findVideoUrl } = require('../src/extractors/gupload');
const { decodeXorPayload } = require('../src/extractors/hxfile');
const { decodeEscapes } = require('../src/extractors/upzur');
const { createSource } = require('../src/extractors/vidply');
const { decryptFsvidSource } = require('../src/extractors/fsvid');
const { decodeVidGuardSignature } = require('../src/extractors/vidguard');
const { encryptTurbovio, decryptTurbovio, generateKey, generateIV } = require('../src/extractors/turbovio');
const { rc4, encodeVidplayId, decodeVidplayResult } = require('../src/extractors/vidplay');
const { veevDecode, buildVeevArray, decodeVeevUrl } = require('../src/extractors/veev');
const { decodeVidsrcNet, decodeVidsrcSubtitles } = require('../src/extractors/vidsrcnet');
const { findUpzoneSource } = require('../src/extractors/upzone');
const { decodeEncryptedEmbed } = require('../src/extractors/vidsrcto');

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
    ['https://vidguard.to/e/abc', 'vidguard'],
    ['https://vembed.net/e/abc', 'vidguard'],
    ['https://fsvid.lol/e/abc', 'fsvid'],
    ['https://turboembed.com/abc123', 'turbovio'],
    ['https://vidplay.site/e/abc123', 'vidplay'],
    ['https://mcloud.bz/e/abc123', 'vidplay'],
    ['https://vidplay.online/e/abc123', 'vidplay'],
    ['https://veev.to/e/abc123', 'veev'],
    ['https://kinoger.pw/d/abc123', 'veev'],
    ['https://poophq.com/e/abc123', 'veev'],
    ['https://moflix-stream.xyz/api/v1/videos/123/playback', 'moflix'],
    ['https://api.moviemaster.lat/video?tmdb=123&embed=abc', 'moviemaster'],
    ['https://vidsrc-embed.ru/embed/movie?tmdb=123', 'vidsrcnet'],
    ['https://vsembed.ru/embed/tv?tmdb=123', 'vidsrcnet'],
    ['https://vidlink.pro/movie/123', 'vidlink'],
    ['https://vidsrc.ru/movie/123', 'vidsrcru'],
    ['https://upzone.cc/e/abc', 'upzone'],
    ['https://upzone.to/e/abc', 'upzone'],
    ['https://vidsrc.to/embed/movie/123', 'vidsrcto'],
    ['https://drive.google.com/file/d/abc123/view', 'googledrive'],
    ['https://luluvdo.com/e/abc', 'luluvdo'],
    ['https://luluvid.com/e/abc', 'luluvdo'],
    ['https://ups2up.fun/e/abc', 'ustr'],
    ['https://up4stream.com/e/abc', 'ustr'],
    ['https://video.sibnet.ru/shell.php?videoid=123', 'videosibnet'],
    ['https://primesrc.me/api/v1/l?key=abc123', 'primesrc'],
    ['https://api.vidlove.cc/api/v1/source?id=123', 'vidlove'],
    ['https://maxstream.video/e/abc123', 'maxstream'],
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

test('FSVid reverses and XOR-decodes its encoded source', () => {
  const host = 'fsvid.lol';
  const fn = 'calc(1in + 56px); width:50%; kk = (0x3d + i * 89)';
  const plain = 'https://cdn.example/master.m3u8';
  let sum = 0;
  for (const char of host) sum = (sum + char.charCodeAt(0)) & 0xff;
  const bytes = Buffer.from(plain, 'latin1');
  const encodedBytes = Buffer.alloc(bytes.length);
  for (let i = 0; i < bytes.length; i++) encodedBytes[bytes.length - 1 - i] = bytes[i] ^ ((0x3d + i * 89 + sum + 76) & 0xff);
  assert.equal(decryptFsvidSource(fn, encodedBytes.toString('base64'), host), plain);
});

test('VidGuard rejects malformed signature data safely', () => {
  assert.equal(decodeVidGuardSignature('https://cdn.example/video.m3u8?sig=xyz'), null);
});

test('Turbovio AES encryption and decryption are symmetric', () => {
  const fixedTime = 1762900000000;
  const text = '{"stream":{"hls":"https://cdn.example/video.m3u8"}}';
  const encrypted = encryptTurbovio(text, 'https://turboembed.com', fixedTime);
  assert.equal(decryptTurbovio(encrypted, 'https://turboembed.com', fixedTime), text);
  assert.equal(generateKey(fixedTime).length, 32);
  assert.equal(generateIV('https://turboembed.com').length, 32);
});

test('Vidplay RC4 encoder and decoder preserve the identifier and payload', () => {
  const key = 'sample-secret-key';
  const id = 'abc123xyz';
  const encoded = encodeVidplayId(key, id);
  const decoded = rc4(key, Buffer.from(encoded.replace(/_/g, '/').replace(/-/g, '+'), 'base64')).toString('utf8');
  assert.equal(decoded, id);
  const payload = encodeVidplayId(key, '{"sources":[{"file":"https://cdn.example/video.m3u8"}]}');
  assert.equal(decodeVidplayResult(key, payload), '{"sources":[{"file":"https://cdn.example/video.m3u8"}]}');
});

test('Veev decoding helpers preserve plain characters and decode operation arrays', () => {
  assert.equal(veevDecode('AB'), 'AB');
  assert.deepEqual(buildVeevArray('2120'), [[2, 1]]);
  assert.equal(decodeVeevUrl('68747470733a2f2f63646e2e6578616d706c652f766964656f2e6d337538', [0]), 'https://cdn.example/video.m3u8');
});

test('Vidsrc.net supports simple decoder variants and subtitle parsing', () => {
  assert.equal(decodeVidsrcNet('NdonQLf1Tzyx7bMG', 'abcDEF'), 'DEFabc');
  assert.equal(decodeVidsrcNet('o2VSUnjnZl', 'def'), 'abc');
  assert.deepEqual(decodeVidsrcSubtitles('[French] /subs/fr.vtt,[English] /subs/en.vtt', 'https://player.example/e/1'), [
    { label: 'French', url: 'https://player.example/subs/fr.vtt' },
    { label: 'English', url: 'https://player.example/subs/en.vtt' },
  ]);
});

test('Upzone detects a direct HLS source in player markup', () => {
  assert.equal(findUpzoneSource('<script>player = {file: "https://cdn.example/movie.m3u8"};</script>', 'https://upzone.cc/e/1'), 'https://cdn.example/movie.m3u8');
});

test('Vidsrc.to decrypts its RC4 URL payload', () => {
  const key = 'test-key';
  const plain = 'https://vidplay.site/e/abc?token=1';
  const encrypted = rc4(key, plain).toString('base64').replace(/\//g, '_').replace(/\+/g, '-');
  assert.equal(decodeEncryptedEmbed(key, encrypted), plain);
});
