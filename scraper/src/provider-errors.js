function getProviderErrorEvent(error) {
  const message = String(error?.message || error || '');
  const name = String(error?.name || '');
  const code = String(error?.code || '');

  const isTimeout = name === 'TimeoutError'
    || name === 'AbortError'
    || code === 'ETIMEDOUT'
    || code === 'ABORT_ERR'
    || /timed?\s*out|timeout|operation was aborted|aborted due to/i.test(message);

  return isTimeout ? 'PROVIDER_TIMEOUT' : 'PROVIDER_SEARCH_FAILED';
}

module.exports = { getProviderErrorEvent };
