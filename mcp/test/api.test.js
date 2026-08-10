import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { ApiError, TsutsumbiApi, redact } from '../lib/api.js';

const TOKEN = 'tsu_abcdefghijkl_' + 'a'.repeat(43);

function stubFetch(response, calls = []) {
  return async (url, init) => {
    calls.push({ url, init });

    return {
      ok: response.status >= 200 && response.status < 300,
      status: response.status,
      text: async () => response.body ?? '',
    };
  };
}

describe('TsutsumbiApi', () => {
  it('refuses to start without a base url', () => {
    assert.throws(() => new TsutsumbiApi({ baseUrl: '', token: TOKEN }), /TSUTSUMBI_URL/);
  });

  it('refuses to start without a token', () => {
    assert.throws(() => new TsutsumbiApi({ baseUrl: 'https://tsutsumbi', token: '' }), /TSUTSUMBI_TOKEN/);
  });

  it('sends the token as a bearer credential', async () => {
    const calls = [];
    const api = new TsutsumbiApi({
      baseUrl: 'https://tsutsumbi',
      token: TOKEN,
      fetch: stubFetch({ status: 200, body: '{"ok":true}' }, calls),
    });

    await api.get('/api/projects');

    assert.equal(calls[0].url, 'https://tsutsumbi/api/projects');
    assert.equal(calls[0].init.headers.Authorization, `Bearer ${TOKEN}`);
  });

  it('drops a trailing slash on the base url rather than doubling it', async () => {
    const calls = [];
    const api = new TsutsumbiApi({
      baseUrl: 'https://tsutsumbi/',
      token: TOKEN,
      fetch: stubFetch({ status: 200, body: '{}' }, calls),
    });

    await api.get('/api/projects');

    assert.equal(calls[0].url, 'https://tsutsumbi/api/projects');
  });

  it('repeats an array parameter the way the API reads it', async () => {
    const calls = [];
    const api = new TsutsumbiApi({
      baseUrl: 'https://tsutsumbi',
      token: TOKEN,
      fetch: stubFetch({ status: 200, body: '{}' }, calls),
    });

    await api.get('/api/tickets', { project: 'MAJI', status: ['todo', 'blocked'], q: '' });

    assert.equal(calls[0].url, 'https://tsutsumbi/api/tickets?project=MAJI&status%5B%5D=todo&status%5B%5D=blocked');
  });

  it('turns a validation problem into something actionable', async () => {
    const api = new TsutsumbiApi({
      baseUrl: 'https://tsutsumbi',
      token: TOKEN,
      fetch: stubFetch({
        status: 422,
        body: JSON.stringify({
          title: 'The submitted data is invalid',
          errors: { title: ['The title is required.'] },
        }),
      }),
    });

    await assert.rejects(
      () => api.post('/api/projects/MAJI/tickets', {}),
      (error) => {
        assert.ok(error instanceof ApiError);
        assert.equal(error.status, 422);
        assert.match(error.message, /title: The title is required\./);

        return true;
      },
    );
  });

  it('reports an unreachable server without swallowing the reason', async () => {
    const api = new TsutsumbiApi({
      baseUrl: 'https://tsutsumbi',
      token: TOKEN,
      fetch: async () => {
        throw new Error('connect ECONNREFUSED');
      },
    });

    await assert.rejects(() => api.get('/api/projects'), /unreachable.*ECONNREFUSED/s);
  });

  /**
   * The one secret this process holds must not travel back out, whatever a
   * server or a network stack decides to put in an error message.
   */
  it('never lets a token through in an error message', async () => {
    const api = new TsutsumbiApi({
      baseUrl: 'https://tsutsumbi',
      token: TOKEN,
      fetch: async () => {
        throw new Error(`request to https://tsutsumbi failed with ${TOKEN}`);
      },
    });

    await assert.rejects(
      () => api.get('/api/projects'),
      (error) => {
        assert.ok(!error.message.includes(TOKEN));
        assert.match(error.message, /tsu_\[redacted\]/);

        return true;
      },
    );
  });

  it('redacts a token wherever it appears', () => {
    assert.equal(redact(`before ${TOKEN} after`), 'before tsu_[redacted] after');
  });
});
