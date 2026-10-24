# tsutsumbi

Agent-driven project tracking. The agent opens tickets, changes statuses and
closes them without human approval; the human reads, redirects, archives.

*tsoutsoumbi* is a small insect from Mayotte. Insect, bug, project tracking.

## Requirements

Docker (the application and its database run in containers, in development and
in production), Caddy on the host for TLS, and Node 20+ with Yarn 4 for the MCP
server. PHP, Composer and MariaDB on the machine are only needed to run the
tests and the quality gate outside the container.

## Local install

The development site runs under Docker, with the production image: FrankenPHP
and PHP, the checkout mounted over `/app`, environment `dev`, and a MariaDB 12.3
container. See `deploy/README.md` for the principle.

```bash
docker compose -f deploy/local/compose.yaml up -d --build
```

```bash
docker compose -f deploy/local/compose.yaml exec app composer install
```

```bash
deploy/local/load-database
```

`load-database` replaces the container's database with a copy of the Mac's
MariaDB one (named by `DATABASE_URL` in `.env.local`, which stays outside version
control, never committed). Without a database to copy, run the migrations in the
container instead:

```bash
docker compose -f deploy/local/compose.yaml exec app php bin/console doctrine:migrations:migrate
```

The site answers on `http://127.0.0.1:8100`. For `https://tsutsumbi.local.beeraw.yt`,
add a block to the Mac's Caddyfile: `tls internal` and
`reverse_proxy 127.0.0.1:8100`. The container database is published on
`127.0.0.1:3326`, which is where `bin/console` run on the Mac connects (`.env.dev`).

The tests keep their own database, `tsutsumbi_test`, on the MariaDB of the Mac
(`.env.test.local`, outside version control, as before):

```
DATABASE_URL="mysql://<user>:<password>@127.0.0.1:3306/tsutsumbi?serverVersion=12.3.2-MariaDB&charset=utf8mb4"
```

```bash
php bin/console doctrine:migrations:migrate --env=test
```

## Accounts and tokens

```bash
php bin/console app:user:create you@example.com --admin
```

```bash
php bin/console app:token:create --label="laptop / claude-code" --agent=claude-code
```

The full token is shown once and never again. Afterwards only the selector is
visible, which is enough to list and revoke:

```bash
php bin/console app:token:list
```

```bash
php bin/console app:token:revoke <selector>
```

`--scope` is repeatable to narrow a token down:
`--scope=tickets:write --scope=comments:write`. With no option, every scope is
granted. Creating and revoking also work from the web interface.

## MCP server

`mcp/` holds a separate, thin process: it translates six tool calls into HTTP
calls and does nothing else. No business logic, no database access — the rules
about statuses, resolutions and numbering all stay server-side.

```bash
yarn --cwd mcp install
```

Two environment variables, nothing more: `TSUTSUMBI_URL` and `TSUTSUMBI_TOKEN`.
The token appears neither in the repository, nor in the logs, nor in any error
message handed back to the model.

```bash
claude mcp add tsutsumbi --scope user --env TSUTSUMBI_URL=https://tsutsumbi.example.com --env TSUTSUMBI_TOKEN=tsu_… -- /path/to/tsutsumbi/mcp/launch.sh
```

The launcher rather than `node server.js` directly: Claude Code starts the server
without a login shell, so a node installed by nvm is not on the `PATH` — nvm puts
it there from a shell profile, and there is no profile here. The symptom is an
MCP server reporting itself disconnected with no further explanation, which is a
long way from the cause. `launch.sh` resolves node at startup, honouring nvm's
default alias, so a version bump breaks nothing.

For a local instance, add
`--env NODE_EXTRA_CA_CERTS=/opt/homebrew/var/lib/caddy/pki/authorities/local/root.crt`:
Caddy signs the host with its own authority, which Node does not know about. The
PKI of Homebrew's Caddy lives in `/opt/homebrew/var/lib/caddy`, **not** in
`~/Library/Application Support/Caddy` — the latter holds an older authority that
no longer signs anything. In production, with a real certificate, there is
nothing to do.

`--scope user` is not optional here: the default scope is the current directory,
whereas the instruction file below asks *every* project to call
`resolve_project`. Without it, the tools would only exist where the command was
typed and every other project would ignore the file in silence.

The command runs on the development machine, from any directory. The MCP server
is a local process: it has no business in production.

```bash
yarn --cwd mcp test
```

`yarn --cwd mcp test` also runs the server end to end over the protocol, against a stub of
the API: bearer authentication, a tool call, a rejected token, an unreachable API.

## The file that sets everything off

`docs/www-CLAUDE.md` belongs in the `CLAUDE.md` of the directory holding your
projects — `~/www` in these examples. Without it the tool exists but stays empty:
no agent has any reason to call `resolve_project`.

```bash
cp docs/www-CLAUDE.md ~/www/CLAUDE.md
```

Mind its reach: that file is inherited by **every** subdirectory, so by every
project. It opens with an escape clause — if the tsutsumbi tools are not
available, the agent ignores it silently — so that it stays inert until the MCP
server is declared, rather than failing a call at the start of every session.

## Quality

```bash
composer qa
```

Chains PHP-CS-Fixer in check mode, PHPStan level 8 and PHPUnit. The three
commands also exist separately: `composer cs`, `composer stan`, `composer test`.
`composer cs:fix` applies the style corrections.

Nothing runs this suite for you: the only workflow of this repository delivers
`main`, it does not test. The quality gate is local, and it is to be passed before
pushing. In the development container: `docker compose -f deploy/local/compose.yaml exec app composer qa`.

## Interface

Three screens, plus token management.

**Dashboard** — projects sorted by decreasing activity, with their number of open
tickets. Archived ones stay out of the way until asked for.

**Project** — the ticket list, filterable by status, type and resolution, with
full-text search. Closed tickets are hidden by default: this is the live view of
the specification, not a deletion. "Edit project" opens the name, the description,
the path and the key. The last two are the project's identity: a path changed
without the directory having moved has the next session start create a second
project, and a key changed rewrites the identifier of every ticket the project
carries — `MAJI-42` becomes `MJ-42`, and what was written in a commit or a
message stops resolving. The form says so rather than refusing.

Column headings sort the list, except "Opened by": actors are a single table for
two shapes — a human carries an email address, an agent a label — so there is no
single field to order on. Status and type sort in the declaration order of their
enum, which is the order of the lifecycle, rather than the alphabetical order of
the stored value. Below 43rem the list becomes cards and the heading disappears:
sorting is an affordance of the wide list, and remains reachable through the URL
(`?sort=title&dir=asc`).

**Ticket** — the description in markdown, the timeline that merges comments and
audit log, and the edit and status-change panels. Reopening is not a separate
action: it is a status change that leaves `done`, and the domain clears the
resolution.

Self-hosted Nunito typography, navy palette, a `border-radius` of 0.2rem, vanilla
JavaScript with no build step.

With no build step there is no manifest to name the files being served:
`App\Asset\ContentHashVersionStrategy` appends a fingerprint of the file contents
to every URL rendered by `asset()` (`/css/app.css?v=…`). One byte changes, the
URL changes and the browser downloads again; nothing changes, the URL holds and
the cache stays valid. That is what removes the need to clear a cache by hand
after every release.

`design/BRIEF.md` is the visual redesign brief.

## Interface language

The interface ships in English, French, Spanish, German, Portuguese and Italian.
The language is a configuration choice, not a user-facing switch: set `APP_LOCALE`
in `.env.local`.

```
APP_LOCALE=es
```

Dates, times and numbers follow the same locale. Anything an agent reads — the
API, the MCP tool descriptions, console output — stays in English regardless.

## API

JSON responses, errors in `application/problem+json` (RFC 7807), authentication
through `Authorization: Bearer tsu_...`.

| Method | Route | Required scope |
|---|---|---|
| `POST` | `/api/projects/resolve` | `projects:write` |
| `GET` | `/api/projects` | — |
| `GET` | `/api/projects/{key}` | — |
| `PATCH` | `/api/projects/{key}` | `projects:write` |
| `POST` | `/api/projects/{key}/tickets` | `tickets:write` |
| `GET` | `/api/tickets` | — |
| `GET` | `/api/tickets/{key}` | — |
| `PATCH` | `/api/tickets/{key}` | `tickets:write` |
| `POST` | `/api/tickets/{key}/comments` | `comments:write` |
| `POST` | `/api/tickets/{key}/attachments` | `attachments:write` |

`GET /api/tickets` accepts `project`, `status` (repeatable through `status[]=` or
comma-separated), `type`, `resolution`, `q`, `updatedSince`, `page` and `perPage`.
With no status filter, closed tickets are included: they stay readable and
searchable. It is the interface that hides them by default, not the API.

`PATCH` tells an absent key from a key set to `null`: `{"description": null}`
clears the description, omitting the key leaves it alone.

`PATCH /api/projects/{key}` is what `resolve` deliberately refuses to be. A name
handed to `resolve` is used at creation and ignored afterwards, because an agent
replays that call at the start of every session and a resolve that wrote would
undo a rename made from the interface. Changing a project is therefore asked for
by key, on purpose. The `path` and the `key` are both unique, and a value another
project holds answers `409` rather than being quietly worked around.

## Attachments

The exception, not the reflex. Strict allowlist, type guessed from the bytes
rather than from the request, mandatory reason — which is recorded as a comment on
the ticket. Images become WebP at quality 60, capped at 1600px on the longest
side and stripped of their metadata; text and logs are gzipped. 2 MB per
attachment after compression, 20 MB per ticket.

Files live in `var/attachments/`, outside the web root, under a server-generated
name. The original name is kept for display only.

## Production

Debian server, Caddy on the host, and two containers: the application
(FrankenPHP, PHP 8.5) and its MariaDB 12.3. The host's Caddy keeps the TLS and
forwards to `127.0.0.1:8100`. The procedure to move a server from PHP-FPM to the
containers, the layout under `/srv/tsutsumbi-production` and the rollback are in
`deploy/README.md`.

`.env.local` stays in the checkout, outside version control, with `APP_ENV=prod`,
`APP_SECRET`, `DEFAULT_URI`, `API_RATE_LIMIT` and `APP_LOCALE`. Its `DATABASE_URL`
is overridden by the container's own.

One thing not to miss in the Caddy site block: tsutsumbi sets its own
`Strict-Transport-Security`. The one Caddy adds by convention must therefore be
prefixed with a `?`, which only sets it when absent — otherwise the response goes
out with the same header twice. `deploy/production/Caddyfile.production` has it.

### Deploying

A push on `main` runs the GitHub workflow `CD main`, which calls
`deploy/server/tsutsumbi-deploy` on the server. The script refuses to start
unless `.env.local` exists, reads `APP_ENV=prod` and sets `APP_LOCALE` (`.env`
ships a default of `en`, so a missing one would quietly flip the interface to
English). It then moves the checkout to the commit, rebuilds the system image
only if `Dockerfile` or `docker/` changed, runs `composer install` only if the
lock changed, applies the migrations, clears the cache, restarts the container
and waits for `/healthz`.

To release a given commit, or to go back, run the workflow by hand from the
Actions tab and give the commit.

A rollback brings back the code, not the schema: if the migration being undone
altered the database, an earlier backup is needed, and the script does not take
one.

## Contributing, and licence

This is a personal project, published because the shape of it may be useful to
somebody else, not because it is looking for co-maintainers. Issues describing a
bug you actually hit are welcome. Pull requests are read, but a large one that
was not discussed first will probably be declined on scope alone — say what you
have in mind in an issue before writing it. Nothing checks a pull request
automatically, so run `composer qa` and say that you did.

Security issues go through the private channel described in
[SECURITY.md](SECURITY.md), not through a public issue.

The code is under the MIT licence, in [LICENSE](LICENSE). The two bundled
typefaces carry their own SIL Open Font Licence, kept next to them in
`public/fonts/`.

## Deliberate deviations from the specification

**Purely lexical path normalisation.** The specification asked for symbolic links
to be resolved. tsutsumbi does not necessarily run on the machine hosting the
project, and a path that resolved differently tomorrow would create a second
project for the same directory — exactly what `resolve` must never do. Link
resolution is done by the MCP server, which does live next to the files.

**`TicketEvent.oldValue` / `newValue` as `TEXT`.** The specification said
`string`. Rewriting a description is a legitimately audited change, and truncating
it would lose precisely what the log exists to keep.

**`hash_equals` on the selector, not on the verifier.** The specification asked
for `hash_equals` after `password_verify`; applied to a bcrypt hash that amounts
to checking twice. The selector, on the other hand, is stored in clear, and
comparing it in PHP guards against a collation insensitive to case or whitespace
returning a neighbouring row.

**A single opening in the CSP.** `style-src-attr 'unsafe-inline'` is needed
because an agent's colour is data, set on the element as a custom property.
`<style>` blocks, remote stylesheets and any inline script all stay refused.

**Classic CSRF rather than stateless.** Symfony 7.2 offers session-less CSRF
protection, which replaces a placeholder from a cookie with an inline script. The
human side is already on a session, so it brings nothing here — and the inline
script would force `'unsafe-inline'` into a CSP whose whole point is to do
without it.

**The deployment script is in the repository, after all.** The specification put
it under version control; the first version of the project kept it on the server
alone, because it names the host's layout. Moving to containers made it part of
the delivery (the workflow, the compose file and the script change together), so
`deploy/` now carries it. The cost is that a public repository describes paths,
ports and service names of the host; no secret, address or credential is in it.
