import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { createTools } from '../lib/tools.js';

function recordingApi() {
  const calls = [];

  return {
    calls,
    get: async (path, query) => (calls.push({ method: 'GET', path, query }), { ok: true }),
    post: async (path, body) => (calls.push({ method: 'POST', path, body }), { ok: true }),
    patch: async (path, body) => (calls.push({ method: 'PATCH', path, body }), { ok: true }),
    postMultipart: async (path, form) => (calls.push({ method: 'POST', path, form }), { ok: true }),
  };
}

function tool(api, name) {
  const found = createTools(api).find((candidate) => candidate.name === name);

  assert.ok(found, `tool ${name} should exist`);

  return found;
}

describe('the tool surface', () => {
  it('exposes eight tools and no more', () => {
    const names = createTools(recordingApi()).map((candidate) => candidate.name);

    assert.deepEqual(names, [
      'resolve_project',
      'update_project',
      'list_tickets',
      'get_ticket',
      'create_ticket',
      'update_ticket',
      'add_comment',
      'attach_file',
    ]);
  });

  /**
   * The descriptions are the only place an agent reads the rules at the moment
   * it decides what to call, so they are part of the contract, not decoration.
   */
  it('carries its rules of use in the descriptions', () => {
    const tools = new Map(createTools(recordingApi()).map((candidate) => [candidate.name, candidate.description]));

    assert.match(tools.get('resolve_project'), /idempotent/i);
    assert.match(tools.get('resolve_project'), /before opening anything/i);
    assert.match(tools.get('update_project'), /resolve_project will not do any of this/i);
    assert.match(tools.get('update_project'), /stops resolving/i);
    assert.match(tools.get('get_ticket'), /before commenting/i);
    assert.match(tools.get('create_ticket'), /Never a technical step/i);
    assert.match(tools.get('update_ticket'), /note is required/i);
    assert.match(tools.get('update_ticket'), /without a confirmation|with no confirmation/i);
    assert.match(tools.get('attach_file'), /exception, not a habit/i);
  });

  it('every tool declares a strict input schema', () => {
    for (const candidate of createTools(recordingApi())) {
      assert.equal(candidate.inputSchema.type, 'object', `${candidate.name} schema`);
      assert.equal(candidate.inputSchema.additionalProperties, false, `${candidate.name} additionalProperties`);
    }
  });
});

describe('resolve_project', () => {
  it('sends a path already brought to its canonical form', async () => {
    const api = recordingApi();

    await tool(api, 'resolve_project').handler({ path: '/home/dev/projects/harusi/../maji/' });

    assert.deepEqual(api.calls[0], {
      method: 'POST',
      path: '/api/projects/resolve',
      body: { path: '/home/dev/projects/maji' },
    });
  });

  it('leaves the name out when none is given, so nothing is renamed', async () => {
    const api = recordingApi();

    await tool(api, 'resolve_project').handler({ path: '/home/dev/projects/maji' });

    assert.ok(!('name' in api.calls[0].body));
  });
});

describe('update_project', () => {
  it('addresses the project by key and sends only what changes', async () => {
    const api = recordingApi();

    await tool(api, 'update_project').handler({ project: 'MAJI', name: 'Maji ya Mayotte' });

    assert.deepEqual(api.calls[0], {
      method: 'PATCH',
      path: '/api/projects/MAJI',
      body: { name: 'Maji ya Mayotte' },
    });
  });

  /**
   * The same canonical form resolve_project sends, and for the same reason: a
   * moved directory spelled two ways must not end up as two projects.
   */
  it('brings a new path to its canonical form', async () => {
    const api = recordingApi();

    await tool(api, 'update_project').handler({ project: 'MAJI', path: '/home/dev/projects/harusi/../maji/' });

    assert.deepEqual(api.calls[0].body, { path: '/home/dev/projects/maji' });
  });

  it('turns an empty description into the null that clears it', async () => {
    const api = recordingApi();

    await tool(api, 'update_project').handler({ project: 'MAJI', description: '' });

    assert.deepEqual(api.calls[0].body, { description: null });
  });

  it('escapes the key rather than letting it shape the path', async () => {
    const api = recordingApi();

    await tool(api, 'update_project').handler({ project: '../tickets', name: 'x' });

    assert.equal(api.calls[0].path, '/api/projects/..%2Ftickets');
  });
});

describe('list_tickets', () => {
  it('maps its arguments onto the API filters', async () => {
    const api = recordingApi();

    await tool(api, 'list_tickets').handler({
      project: 'MAJI',
      status: ['todo', 'blocked'],
      query: 'export',
      limit: 10,
    });

    assert.deepEqual(api.calls[0].query, {
      project: 'MAJI',
      status: ['todo', 'blocked'],
      q: 'export',
      perPage: 10,
    });
  });
});

describe('get_ticket', () => {
  it('reads the ticket the key names', async () => {
    const api = recordingApi();

    await tool(api, 'get_ticket').handler({ key: 'MAJI-42' });

    assert.equal(api.calls[0].method, 'GET');
    assert.equal(api.calls[0].path, '/api/tickets/MAJI-42');
  });

  it('escapes the key rather than letting it shape the path', async () => {
    const api = recordingApi();

    await tool(api, 'get_ticket').handler({ key: '../projects' });

    assert.equal(api.calls[0].path, '/api/tickets/..%2Fprojects');
  });
});

describe('update_ticket', () => {
  it('leaves out what was not asked to change', async () => {
    const api = recordingApi();

    await tool(api, 'update_ticket').handler({ key: 'MAJI-42', status: 'in_progress' });

    assert.deepEqual(api.calls[0].body, { status: 'in_progress' });
  });

  /**
   * A tool call cannot express a JSON null distinctly from an absent key, so an
   * empty string is what clears a text field.
   */
  it('turns an empty description into the null that clears it', async () => {
    const api = recordingApi();

    await tool(api, 'update_ticket').handler({ key: 'MAJI-42', description: '' });

    assert.deepEqual(api.calls[0].body, { description: null });
  });

  it('passes a description through untouched', async () => {
    const api = recordingApi();

    await tool(api, 'update_ticket').handler({ key: 'MAJI-42', description: 'The need.' });

    assert.deepEqual(api.calls[0].body, { description: 'The need.' });
  });

  it('escapes a key rather than letting it shape the path', async () => {
    const api = recordingApi();

    await tool(api, 'update_ticket').handler({ key: '../projects', status: 'todo' });

    assert.equal(api.calls[0].path, '/api/tickets/..%2Fprojects');
  });
});

describe('attach_file', () => {
  it('refuses a path with no file behind it', async () => {
    await assert.rejects(
      () => tool(recordingApi(), 'attach_file').handler({
        key: 'MAJI-42',
        path: '/nowhere/at/all.png',
        reason: 'transient state',
      }),
      /No readable file/,
    );
  });
});
