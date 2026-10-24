import assert from 'node:assert/strict';
import { createServer as createHttpServer } from 'node:http';
import { after, before, describe, it } from 'node:test';

import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';

import { TsutsumbiApi } from '../lib/api.js';
import { createServer } from '../server.js';

const GOOD_TOKEN = 'tsu_AbCdEfGhIjKl_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG';
const BAD_TOKEN = 'tsu_ZzYyXxWwVvUu_ZzYyXxWwVvUuTtSsRrQqPpOoNnMmLlKkJjIiHhGgFFE';

/**
 * A stand-in for the tsutsumbi HTTP API: it checks the bearer token the way the
 * real one does and answers in application/problem+json when it is wrong, so the
 * whole path is exercised — MCP request, tool, HTTP call, MCP response — without
 * a database.
 */
function startStub() {
  const requests = [];
  const http = createHttpServer((request, response) => {
    requests.push({ method: request.method, url: request.url, authorization: request.headers.authorization });

    if (request.headers.authorization !== `Bearer ${GOOD_TOKEN}`) {
      response.writeHead(401, { 'Content-Type': 'application/problem+json' });
      response.end(JSON.stringify({ title: 'Unauthorized', detail: 'The token is invalid.', status: 401 }));

      return;
    }

    response.writeHead(200, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({ tickets: [{ key: 'TSU-1', title: 'First ticket' }], total: 1 }));
  });

  return new Promise((resolve) => {
    http.listen(0, '127.0.0.1', () => {
      resolve({ http, requests, url: `http://127.0.0.1:${http.address().port}` });
    });
  });
}

async function connect(baseUrl, token) {
  const server = createServer(new TsutsumbiApi({ baseUrl, token }));
  const client = new Client({ name: 'test-client', version: '0.0.0' });
  const [clientSide, serverSide] = InMemoryTransport.createLinkedPair();

  await Promise.all([server.connect(serverSide), client.connect(clientSide)]);

  return client;
}

describe('the MCP server, end to end', () => {
  let stub;

  before(async () => {
    stub = await startStub();
  });

  after(() => new Promise((resolve) => stub.http.close(resolve)));

  it('lists its tools over the protocol', async () => {
    const client = await connect(stub.url, GOOD_TOKEN);
    const { tools } = await client.listTools();

    assert.equal(tools.length, 8);
    assert.ok(tools.some((tool) => tool.name === 'list_tickets'));
  });

  it('authenticates a tool call with the bearer token and returns the API payload', async () => {
    const client = await connect(stub.url, GOOD_TOKEN);
    stub.requests.length = 0;

    const result = await client.callTool({ name: 'list_tickets', arguments: { project: 'TSU' } });

    assert.notEqual(result.isError, true);
    assert.equal(stub.requests.length, 1);
    assert.equal(stub.requests[0].authorization, `Bearer ${GOOD_TOKEN}`);
    assert.match(stub.requests[0].url, /^\/api\/tickets\?.*project=TSU/);
    assert.match(result.content[0].text, /First ticket/);
  });

  it('turns a rejected token into a tool error that does not leak the token', async () => {
    const client = await connect(stub.url, BAD_TOKEN);

    const result = await client.callTool({ name: 'list_tickets', arguments: {} });

    assert.equal(result.isError, true);
    assert.match(result.content[0].text, /Unauthorized/);
    assert.ok(!result.content[0].text.includes(BAD_TOKEN));
  });

  it('reports an unreachable API as a tool error, not a crash', async () => {
    const client = await connect('http://127.0.0.1:1', GOOD_TOKEN);

    const result = await client.callTool({ name: 'list_tickets', arguments: {} });

    assert.equal(result.isError, true);
    assert.match(result.content[0].text, /unreachable/);
  });

  it('answers an unknown tool with a tool error', async () => {
    const client = await connect(stub.url, GOOD_TOKEN);

    const result = await client.callTool({ name: 'drop_database', arguments: {} });

    assert.equal(result.isError, true);
    assert.match(result.content[0].text, /Unknown tool/);
  });
});
