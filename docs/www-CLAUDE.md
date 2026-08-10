# Project tracking — tsutsumbi

Every direct child directory of this one is a project tracked in tsutsumbi.

If the `tsutsumbi` tools are not available in this session, ignore this whole
file, without mentioning it and without trying to call anything.

## At the start of a session

Call `resolve_project` with the absolute path of the working directory, as soon
as that directory is a direct child of this one. Do not ask for confirmation.
Anywhere else, do nothing until the user asks.

Read the active tickets that come back before creating anything. A ticket that
already covers the work gets updated, not duplicated.

## What a ticket is

A functional unit somebody would recognise three months later: "CSV export of
statements", "VAT wrong on partial credit notes".

Never a technical step. Not "refactor the repository", not "add the migration",
not "fix the failing test". That is how the work gets done, not the work.

The title states the expected result in one line, not the action. The description
is short: the need, the constraint, what is out of scope.

## While working

- `in_progress` when the work actually starts, not when the ticket is opened.
- `blocked`, with a comment explaining the cause, as soon as an external
  dependency stops progress. Never stay blocked silently: a mute blockage is the
  worst state to leave a ticket in.
- Comment on non-trivial design decisions: why this approach and not the other,
  what was ruled out and on what grounds. That is the comment with value in six
  months. No progress reports nobody will reread.

## On closing

Move to `done` and pick the resolution yourself, without waiting for approval:

- `fixed` — the result matches the intent
- `wont_do` — the decision was made not to do it
- `abandoned` — the work was left behind
- `duplicate` — another ticket covers it, name it in a comment
- `obsolete` — the need disappeared along the way

`wont_do` and `abandoned` always deserve a comment explaining the call.

Do not leave a ticket `in_progress` at the end of a session without a comment
saying where it stands.

## Attachments

The exception, not the reflex. Only when the information would otherwise be lost
for good: a capture of a transient state, the output of a tool that cannot be run
again. Never what is already under version control, never what a link would
cover, never what is reproducible — that gets described in a comment.
