# Design brief — tsutsumbi interface

Written for a design agent. Goal: redo the entire visual design of the interface.
This document describes the product, the real data, the technical constraints
that are not negotiable, and what is expected as output.

The `current/` directory holds captures of the interface as it stands and its
stylesheet. They are a starting point to criticise, not a template to respect —
`project-1920.png` shows the main flaw, described in §3. `assets/` holds the mark
in SVG.

---

## 1. What tsutsumbi is

A Jira-like project tracking tool, with one particularity: **it is fed
automatically by agents** (Claude Code, Claude Design…) rather than filled in by
hand. The work is spread over some thirty projects and hundreds of conversation
sessions; the history of what was done, what is under way and what was dropped
dilutes itself. tsutsumbi is the long memory of that work.

Three principles govern the product, and must be readable in the interface:

1. **The agent is autonomous.** It creates projects, opens tickets, changes
   statuses, picks resolutions and closes tickets with no human approval. Nothing
   in the interface should look like an approval queue or a primary data-entry
   form.
2. **The human is above it.** They read, search, filter, comment, correct,
   reopen, archive. Their dominant gesture is **reading and spotting**, not
   creating. The interface is first of all an instrument for consulting.
3. **Nothing is lost.** An abandoned ticket stays readable, a status change
   leaves a trace. The tool does not erase, it archives. Nothing should suggest
   a deletion.

**The name.** *tsoutsoumbi* is a small insect from Mayotte. Insect → bug →
project tracking. The mark is that insect, in orange (`#DE8A2A`), supplied in
`assets/tsutsumbi-mark.svg`.

---

## 2. Who is looking, and when

A single human user: the developer steering the agents. They open tsutsumbi in
two situations, very different from one another:

- **"Where am I?"** — At the start or the end of a day. They sweep the project
  list, spot what moved, what is blocked. Fast reading, many rows, few clicks.
  This is the dominant use.
- **"What happened on this ticket?"** — Three months later, usually. They are
  looking for a design decision taken at the time. Slow, attentive reading of a
  timeline.

Working screen: **large monitor, 1920px and beyond**. That is the main context.
An occasional read on a laptop happens, on a phone almost never — but the
interface must not break.

---

## 3. What is wrong with what exists

To be addressed explicitly:

**The width is wasted.** Everything is locked inside a centred 68rem (~1090px)
column. On a 1920px screen, more than 40% of the surface is grey emptiness on
either side, while the ticket list truncates its columns and the timeline is
cramped. **The whole screen must be used.**

**The filters are a horizontal bar above the table.** They eat height, get
squeezed once there are more than four of them, and have nowhere to grow.

**The whole thing reads as "web page" rather than "tool".** Stacked cards,
generous breathing room, little density. For an instrument consulted several
times a day that displays lists, density is a quality, not a flaw.

---

## 4. Direction asked for

**Modern, project-management oriented.** The ambition is a tool that stands
comparison with Linear, Height or recent Jira, without imitating them.

**Full screen.** No centred column. Navigation, list and detail take the width
available. How is for the design to say: fixed side rail, full-width top bar, or
something else — that is an expected design decision, to be argued.

**Search on the right, results on the left.** This is the chosen pattern: on list
screens, results take the left two thirds and a "filters" card is anchored in the
right third, with the fields stacked vertically and an apply button. This
placement is asked for explicitly, it is not one lead among others.

**Deliberate density on lists.** A project can carry hundreds of tickets. Thirty
of them must be readable at a glance without scrolling, and a `blocked` must be
distinguishable from an `in progress` without reading the text.

---

## 5. The screens

### 5.1 Login

The only public screen. Fields: email address, password, "stay signed in". One
possible error: invalid credentials. The form is limited to 5 attempts per
quarter hour — a message says so when it happens.

### 5.2 Dashboard — project list

One row per project, **sorted by decreasing activity**. Real data available for
each row:

| Data | Example | Note |
|---|---|---|
| Key | `MAJI` | 1 to 10 characters, uppercase and digits |
| Name | `Maji` | up to 120 characters |
| Path | `/home/dev/projects/maji` | up to 500 characters, often long |
| Open tickets | `7` | integer, often 0 |
| Last activity | `27 Jul 2026, 17:35` | |
| Archived | boolean | hidden by default, can be shown |

Some thirty projects today. What is being looked for here: **spotting in one
second the projects that moved and the ones with blocked tickets.** The number of
open tickets alone does not say whether something is blocked — that information
is missing today and would be good to surface.

### 5.3 Project — ticket list

Results on the left, filters on the right.

Available filters: full-text search (title, description **and comment bodies**),
status, type, resolution, and an "include closed tickets" toggle. Closed tickets
are hidden by default.

Columns available per ticket:

| Data | Values |
|---|---|
| Key | `MAJI-42` |
| Title | up to 200 characters, often 40 to 80 |
| Type | `feature` `bug` `chore` `refactor` `docs` `infra` |
| Status | `todo` `in_progress` `blocked` `done` |
| Resolution | `fixed` `wont_do` `abandoned` `duplicate` `obsolete` — only when closed |
| Priority | `low` `normal` `high` |
| Opened by | an agent or a human, see §6 |
| Updated | date and time |

Screen header: project name, key, absolute path, and the archive action.

Pagination: 25 per page.

### 5.4 Ticket — the detail

The slow-reading screen. It holds:

- **Header**: key, title, and the status + resolution pair.
- **Description** as rendered markdown: paragraphs, lists, `code`, code blocks,
  quotes, bold, links.
- **Timeline**: this is the centrepiece. It merges two natures of entry into a
  single chronology:
  - the **comments**, markdown written by an agent or a human;
  - the **changes**, from the audit log: `Status: todo → in progress`,
    `Resolution: empty → fixed`, `Title: … → …`. Several fields changed together
    form a single entry. A description rewrite is signalled as such without
    showing the text.
- **Metadata**: type, priority, opened by, opened on, started on, closed on.
- **Attachments**, when there are any — rare by design. Name, type, size, date.
- **Actions**: edit the ticket (title, description, type, priority), change the
  status (status, resolution, comment), add a comment.

One constraint of the model that must show: **a resolution only exists on a
closed ticket.** Moving to `blocked` requires a comment explaining the cause.
Reopening a ticket is not a separate action: it is a status change that leaves
`done`, and the resolution disappears on its own.

### 5.5 API tokens

An administration screen, rarely visited. List of tokens (selector, label, agent,
scopes, last use, state) and a creation form.

One particular moment to handle: **on creation, the token in clear is shown once
and will never be shown again.** That moment deserves visual treatment that says
"copy this now" unambiguously.

---

## 6. Telling actors apart

A product requirement: **the interface visually distinguishes human actions from
agent actions, and distinguishes agents from one another.**

Every agent carries a slug (`claude-code`, `claude-design`, `claude-review`, and
others appear on their own), a label and a colour assigned automatically from a
palette of eight. A human is identified by their email.

The distinction must not rest on colour alone: it has to hold in black and white
and for an eye that does not tell hues apart. The current solution — a disc for
the agent, a diamond for the human — is a lead, not an obligation.

---

## 7. Technical constraints, non-negotiable

These are not preferences: they are already in place and the design has to work
with them.

**Server-side Twig, vanilla JavaScript, no build step.** No React, no Tailwind,
no Sass, no bundler. One hand-written CSS sheet, served as is. Modern CSS is
welcome: custom properties, grid, `color-mix()`, `:has()`.

**Strict Content-Security-Policy.** No inline script, no `<style>` block, no
remote resource — no Google font, no CDN, no external image. Everything is served
from the same origin. Only `style` attributes are tolerated, to set an actor's
colour.

**Nunito typography**, already self-hosted as a variable font (400 to 700), latin
and latin extended. Changing typeface means self-hosting it too; say so if it is
proposed.

**Navy palette, rare orange.** Navy `#1b2a4a` for the chrome. The orange
`#DE8A2A` is the brand colour: today it serves only the logo and the focus ring.
That stance — a single saturated colour, spent sparingly — is to be kept or
challenged explicitly.

**A `border-radius` of 0.2rem.** A value imposed by the specification.

**The interface must work without JavaScript.** JS only brings comfort:
submitting a filter on change, confirming before a destructive action, hiding the
resolution field when the status does not carry one. Every rule is applied
server-side as well.

**No hard-coded visible string.** Every string a user can read goes through the
translation system; the existing labels live in `translations/`.

---

## 8. States to cover

A design that only handles the nominal case is useless. To be planned for:

- **Empty**: no project tracked, no ticket, no comment, no token. The initial
  emptiness deserves a sentence explaining what is going to fill it.
- **Overfull**: a project with 400 tickets, a timeline with 60 entries, a 200
  character path, a 200 character title.
- **Form errors**: invalid field, and the particular case of a domain refusal —
  moving to blocked without a comment, closing without a resolution.
- **Flash messages**: success after an action, login error.
- **Archived and closed**: present but set back, never absent.

---

## 9. Expected deliverables

1. **The four main screens mocked up** at full width (1920px and 1440px):
   dashboard, project, ticket, tokens. Plus login.
2. **The behaviour below 1200px**: what the filter column becomes, what the table
   becomes. One rule, not five breakpoints.
3. **A system** rather than a set of screens: typographic scale, spacing scale,
   colour set with the values, treatment of statuses and types, treatment of
   actors, states of a field, states of a button.
4. **The CSS**, or enough to write it unambiguously. It will replace
   `current/app.css`, whose class structure can serve as a base or be thrown
   away.
5. **What was ruled out and why.** The non-obvious decisions matter as much as
   the result.

---

## 10. Open questions, on which an opinion is expected

- Navigation: permanent side rail or top bar? The product has only two top-level
  destinations (projects, tokens) — is a rail justified, or a luxury?
- Should the dashboard stay a table, or become something else once thirty
  projects are tracked and twenty of them are dormant?
- How to surface that a project has **blocked** tickets, and not merely open
  ones?
- The timeline mixes entries of two very different natures (long text /
  one-line change). Should they be treated as two distinct visual objects, or
  unified?
- Does a dark mode make sense here? It is not asked for, it is not excluded.
