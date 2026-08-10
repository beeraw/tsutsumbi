# Security policy

## Reporting a vulnerability

Please report security issues privately, through GitHub's
[private vulnerability reporting](https://github.com/beeraw/tsutsumbi/security/advisories/new)
rather than as a public issue.

Include what you need to make the problem reproducible: the request, the state
it was made against, and what came back. A proof of concept is welcome; a
scanner report pasted whole, without a reading of it, is usually not.

This is a personal project maintained by one person. Expect a first answer
within a week, and no service-level promise beyond that. There is no bounty.

## What is in scope

The application, the API, and the MCP server in `mcp/`. In particular:
anything that lets a request read or write across the boundary of the API token
that carries it, anything that turns an attachment into code execution, and
anything that reveals a token in clear beyond the one moment it is shown at
creation.

## What is not

Findings that depend on an operator's own deployment — a misconfigured web
server, a database reachable from the internet, a secret committed to a fork —
belong to that deployment, not here. Reports whose entire content is a missing
hardening header, a version banner, or an outdated dependency with no path to
exploitation in this code will be read but usually closed without action.

## Known and accepted

The MCP SDK depends on `@hono/node-server`, which carries a moderate advisory
for a path traversal in `serve-static` on Windows. This server speaks over stdio
and serves no static file, so the vulnerable path is never reached. It will be
resolved by a dependency bump when one exists upstream.
