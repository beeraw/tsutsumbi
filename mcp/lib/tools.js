import { basename } from 'node:path';
import { readFile, stat } from 'node:fs/promises';

import { resolveProjectPath } from './paths.js';

const TICKET_TYPES = ['feature', 'bug', 'chore', 'refactor', 'docs', 'infra'];
const TICKET_STATUSES = ['todo', 'in_progress', 'blocked', 'done'];
const TICKET_RESOLUTIONS = ['fixed', 'wont_do', 'abandoned', 'duplicate', 'obsolete'];
const TICKET_PRIORITIES = ['low', 'normal', 'high'];

/**
 * The eight tools, and no more.
 *
 * The descriptions are where the rules of use live. An agent reads them at the
 * moment it decides what to call, which is the only moment they can change what
 * it does — a rule written anywhere else is a rule read too late.
 */
export function createTools(api) {
  return [
    {
      name: 'resolve_project',
      description: [
        'Find or create the tracked project for a working directory, and get back the tickets still open on it.',
        '',
        'Call this once at the start of a working session, before anything else, with the absolute path of the',
        'current working directory. It is idempotent: the same path always yields the same project, and a project',
        'is only created the first time. Symlinks are resolved here, so pass the path as you have it.',
        '',
        'Read the returned activeTickets before opening anything. A ticket that already covers the work should be',
        'updated, not duplicated.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          path: {
            type: 'string',
            description: 'Absolute path of the project directory.',
          },
          name: {
            type: 'string',
            description:
              'Readable project name, used only when the project is created. It never renames an existing one.',
          },
        },
        required: ['path'],
        additionalProperties: false,
      },
      handler: async ({ path, name }) =>
        api.post('/api/projects/resolve', {
          path: resolveProjectPath(path),
          ...(name ? { name } : {}),
        }),
    },

    {
      name: 'update_project',
      description: [
        'Change a project: its name, its description, its path, or its key.',
        '',
        'resolve_project will not do any of this, by design — an agent replays it at the start of every session,',
        'and a resolve that wrote would undo a rename made from the interface. So a change is asked for here, by',
        'project key, and only ever on purpose.',
        '',
        'The path is what resolve_project matches on. Change it when the directory itself has moved, otherwise the',
        'next session start creates a second project for the same work.',
        '',
        'The key is the prefix of every ticket identifier the project carries: renaming it turns MAJI-42 into',
        'MJ-42, and the old form stops resolving — including in commits, messages and links already written',
        'elsewhere. Worth doing when the key is plainly wrong, not to tidy it up.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          project: { type: 'string', description: 'Key of the project to change, for example MAJI.' },
          name: { type: 'string', maxLength: 120, description: 'Readable project name.' },
          description: {
            type: 'string',
            description: 'Markdown. What the project is for. Send an empty string to clear it.',
          },
          path: {
            type: 'string',
            description: 'New absolute path of the project directory. Only when the directory has moved.',
          },
          key: {
            type: 'string',
            description:
              'New project key: up to ten letters and digits. Rewrites the identifier of every ticket in the project.',
          },
        },
        required: ['project'],
        additionalProperties: false,
      },
      handler: async ({ project, description, path, ...rest }) =>
        api.patch(`/api/projects/${encodeURIComponent(project)}`, {
          ...rest,
          // A moved directory is given as the agent has it, so it goes through
          // the same symlink resolution as resolve_project: two spellings of one
          // directory must not end up as two projects.
          ...(path === undefined ? {} : { path: resolveProjectPath(path) }),
          // An empty string is how a text field is cleared from a tool call,
          // where a JSON null cannot be told from an absent key.
          ...(description === undefined ? {} : { description: description === '' ? null : description }),
        }),
    },

    {
      name: 'list_tickets',
      description: [
        'List tickets, optionally narrowed by project, status or a full-text query.',
        '',
        'Closed tickets are included unless a status filter says otherwise: nothing is deleted here, and what was',
        'decided six months ago is often the answer to what is being asked now. The search covers titles,',
        'descriptions and comment bodies.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          project: {
            type: 'string',
            description: 'Project key, for example MAJI. Omit to search across every project.',
          },
          status: {
            type: 'array',
            items: { type: 'string', enum: TICKET_STATUSES },
            description: 'Keep only these statuses. Omit to include closed tickets as well.',
          },
          query: {
            type: 'string',
            description: 'Full-text search over titles, descriptions and comments.',
          },
          limit: {
            type: 'integer',
            minimum: 1,
            maximum: 100,
            description: 'How many tickets to return. Defaults to 25.',
          },
        },
        additionalProperties: false,
      },
      handler: async ({ project, status, query, limit }) =>
        api.get('/api/tickets', {
          project,
          status,
          q: query,
          perPage: limit,
        }),
    },

    {
      name: 'get_ticket',
      description: [
        'Read one ticket in full: its description, every comment, the history of its changes and the files',
        'attached to it.',
        '',
        'list_tickets finds a ticket, this one reads it — a search result carries the title and the status, never',
        'the text. Call it before commenting on a ticket or moving it along: what was asked in a comment, and what',
        'was already ruled out, is only visible here. A comment nobody read is an instruction missed.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          key: { type: 'string', description: 'Ticket key, for example MAJI-42.' },
        },
        required: ['key'],
        additionalProperties: false,
      },
      handler: async ({ key }) => api.get(`/api/tickets/${encodeURIComponent(key)}`),
    },

    {
      name: 'create_ticket',
      description: [
        'Open a ticket for a piece of functional work.',
        '',
        'A ticket is a feature or a fix somebody would recognise three months later — "CSV export of statements",',
        '"VAT wrong on partial credit notes". Never a technical step: not "refactor the repository", not "add the',
        'migration", not "fix the failing test". Those are how the work gets done, not what the work is.',
        '',
        'Check the active tickets from resolve_project first. If one already covers this, update it instead.',
        '',
        'The title states the expected outcome in one line, not the action. The description is short: the need, the',
        'constraint, and what is out of scope.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          project: { type: 'string', description: 'Project key, for example MAJI.' },
          title: {
            type: 'string',
            maxLength: 200,
            description: 'One line describing the expected result, not the action taken.',
          },
          description: {
            type: 'string',
            description: 'Markdown. The need, the constraint, what is out of scope.',
          },
          type: { type: 'string', enum: TICKET_TYPES },
          priority: { type: 'string', enum: TICKET_PRIORITIES, description: 'Defaults to normal.' },
        },
        required: ['project', 'title', 'type'],
        additionalProperties: false,
      },
      handler: async ({ project, title, description, type, priority }) =>
        api.post(`/api/projects/${encodeURIComponent(project)}/tickets`, {
          title,
          type,
          ...(description ? { description } : {}),
          ...(priority ? { priority } : {}),
        }),
    },

    {
      name: 'update_ticket',
      description: [
        'Move a ticket along, or close it.',
        '',
        'Go to in_progress when the work actually starts. Go to blocked the moment an external dependency stops',
        'progress, with a note saying what — a note is required there, because a silent blockage is the worst state',
        'a ticket can be left in.',
        '',
        'Closing is yours to decide, with no confirmation from anyone: set status to done and choose the resolution.',
        'fixed when the result matches the intent, wont_do when the decision was made not to do it, abandoned when',
        'the work was left behind, duplicate when another ticket covers it, obsolete when the need disappeared along',
        'the way. wont_do and abandoned always deserve a note explaining the call, and duplicate should name the',
        'other ticket.',
        '',
        'A resolution belongs to a closed ticket and to no other status. Sending a status that is not done clears it,',
        'which is how a ticket is reopened.',
        '',
        'Do not leave a ticket in_progress at the end of a session without a note saying where it stands.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          key: { type: 'string', description: 'Ticket key, for example MAJI-42.' },
          status: { type: 'string', enum: TICKET_STATUSES },
          resolution: {
            type: 'string',
            enum: TICKET_RESOLUTIONS,
            description: 'Only with status done. Required there.',
          },
          title: { type: 'string', maxLength: 200 },
          description: {
            type: 'string',
            description: 'Replaces the description. Send an empty string to clear it.',
          },
          priority: { type: 'string', enum: TICKET_PRIORITIES },
          note: {
            type: 'string',
            description:
              'Added as a comment in the same transaction as the change. Required to move to blocked.',
          },
        },
        required: ['key'],
        additionalProperties: false,
      },
      handler: async ({ key, description, ...rest }) =>
        api.patch(`/api/tickets/${encodeURIComponent(key)}`, {
          ...rest,
          // An empty string is how a text field is cleared from a tool call,
          // where a JSON null cannot be told from an absent key.
          ...(description === undefined ? {} : { description: description === '' ? null : description }),
        }),
    },

    {
      name: 'add_comment',
      description: [
        'Add a note to a ticket.',
        '',
        'Worth doing when a non-trivial design decision is taken: why this approach and not the other one, what was',
        'ruled out and on what grounds. That is the comment with value in six months. Not worth doing for progress',
        'reports nobody will reread.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          key: { type: 'string', description: 'Ticket key, for example MAJI-42.' },
          body: { type: 'string', description: 'Markdown.' },
        },
        required: ['key', 'body'],
        additionalProperties: false,
      },
      handler: async ({ key, body }) =>
        api.post(`/api/tickets/${encodeURIComponent(key)}/comments`, { body }),
    },

    {
      name: 'attach_file',
      description: [
        'Attach a file to a ticket. An exception, not a habit.',
        '',
        'Only justified when the information would otherwise be lost for good: a capture of a transient state, the',
        'output of a tool that cannot be run again. Never attach what is already in the repository, what a link',
        'would cover, or anything reproducible — describe it in a comment instead.',
        '',
        'Images are converted and resized, text and logs are compressed, and the result must stay under 2 MB.',
        'The reason is recorded alongside the file.',
      ].join('\n'),
      inputSchema: {
        type: 'object',
        properties: {
          key: { type: 'string', description: 'Ticket key, for example MAJI-42.' },
          path: { type: 'string', description: 'Absolute path of the file to attach.' },
          reason: {
            type: 'string',
            description: 'Why this would be lost otherwise. Recorded with the attachment.',
          },
        },
        required: ['key', 'path', 'reason'],
        additionalProperties: false,
      },
      handler: async ({ key, path, reason }) => {
        const stats = await stat(path).catch(() => {
          throw new Error(`No readable file at "${path}".`);
        });

        if (!stats.isFile()) {
          throw new Error(`"${path}" is not a file.`);
        }

        const form = new FormData();
        form.append('file', new Blob([await readFile(path)]), basename(path));
        form.append('reason', reason);

        return api.postMultipart(`/api/tickets/${encodeURIComponent(key)}/attachments`, form);
      },
    },
  ];
}
