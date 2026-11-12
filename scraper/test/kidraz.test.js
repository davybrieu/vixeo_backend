const test = require('node:test');
const assert = require('node:assert/strict');

const { normalizeTitle, matchesTitle } = require('../src/providers/title-matcher');
const { parseKidrazFilms, getApiContext } = require('../src/providers/kidraz');

test('title matching rejects unrelated fuzzy search results', () => {
  assert.equal(matchesTitle('L’Odyssée (2026)', { title: "L'Odyssée", originalTitle: 'The Odyssey', year: 2026 }), true);
  assert.equal(matchesTitle('Resident Evil (2026)', { title: "L'Odyssée", originalTitle: 'The Odyssey', year: 2026 }), false);
  assert.equal(normalizeTitle('L’Odyssée (2026)'), 'odyssee');
});

test('Kidraz only keeps exact requested film titles and compatible years', () => {
  const films = [
    { id: '1', title: "L'Odyssée (2026)", link: '/film/odyssee', poster: '/poster.jpg' },
    { id: '2', title: 'Resident Evil (2026)', link: '/film/resident-evil' },
    { id: '3', title: "L'Odyssée (2024)", link: '/film/odyssee-old' },
  ];
  const results = parseKidrazFilms(films, { title: "L'Odyssée", originalTitle: 'The Odyssey', year: 2026 });
  assert.deepEqual(results.map((film) => film.id), ['1']);
});

test('Kidraz derives the API folder and project from the dynamic provider path', () => {
  assert.deepEqual(getApiContext('https://www.kidraz.com/saby1jy/home/kidraz/'), {
    origin: 'https://www.kidraz.com',
    apiPath: '/saby1jy',
    folder: 'saby1jy',
    pr: 'kidraz',
  });
});
