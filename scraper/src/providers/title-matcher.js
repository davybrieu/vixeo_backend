/**
 * Strict title matching shared by scraper providers.
 * A fuzzy search result is never opened unless its normalized title matches
 * one of the titles sent by the API.
 */
function normalizeTitle(value = '') {
  return String(value)
    .replace(/\s*[-–—:]\s*(?:saison|season)\s*\d+.*$/i, '')
    .replace(/\b(?:19|20)\d{2}\b/g, ' ')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[’']/g, ' ')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
    .replace(/^(?:the|a|an|le|la|les|l|un|une)\s+/, '')
    .replace(/\s+/g, ' ');
}

function matchesTitle(candidate, { title, originalTitle, year } = {}) {
  const actual = normalizeTitle(candidate);
  if (!actual) return false;
  const expected = [...new Set([title, originalTitle].map(normalizeTitle).filter(Boolean))];
  if (!expected.includes(actual)) return false;
  const years = [...String(candidate || '').matchAll(/\b((?:19|20)\d{2})\b/g)]
    .map((match) => Number(match[1]));
  return !year || years.length === 0 || years.includes(Number(year));
}

module.exports = { normalizeTitle, matchesTitle };
