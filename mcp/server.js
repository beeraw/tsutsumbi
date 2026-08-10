#!/usr/bin/env node
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';

import { ApiError, TsutsumbiApi, redact } from './lib/api.js';
import { createTools } from './lib/tools.js';

const NAME = 'tsutsumbi';
const VERSION = '1.0.0';

export function createServer(api) {
  const tools = createTools(api);
  const byName = new Map(tools.map((tool) => [tool.name, tool]));

  const server = new Server(
    { name: NAME, version: VERSION },
    { capabilities: { tools: {} } },
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: tools.map(({ name, description, inputSchema }) => ({ name, description, inputSchema })),
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const tool = byName.get(request.params.name);

    if (tool === undefined) {
      return failure(`Unknown tool "${request.params.name}".`);
    }

    try {
      const result = await tool.handler(request.params.arguments ?? {});

      return {
        content: [{ type: 'text', text: JSON.stringify(result, null, 2) }],
      };
    } catch (error) {
      // A refusal from tsutsumbi is information the agent can act on — a missing
      // note on a blocked ticket, a resolution on an open one — so it comes back
      // as a tool error rather than killing the call.
      return failure(error instanceof ApiError ? error.message : redact(error?.message ?? 'Unknown error.'));
    }
  });

  return server;
}

function failure(message) {
  return { isError: true, content: [{ type: 'text', text: message }] };
}

async function main() {
  let api;

  try {
    api = new TsutsumbiApi({
      baseUrl: process.env.TSUTSUMBI_URL,
      token: process.env.TSUTSUMBI_TOKEN,
    });
  } catch (error) {
    // Never echo the environment back: the message says which variable is
    // missing, never what any of them contain.
    process.stderr.write(`tsutsumbi-mcp cannot start: ${redact(error.message)}\n`);
    process.exit(1);
  }

  await createServer(api).connect(new StdioServerTransport());
}

// Only when run as a program; importing this file for a test must not open stdio.
if (process.argv[1] && import.meta.url === `file://${process.argv[1]}`) {
  main().catch((error) => {
    process.stderr.write(`tsutsumbi-mcp stopped: ${redact(error?.message ?? error)}\n`);
    process.exit(1);
  });
}
