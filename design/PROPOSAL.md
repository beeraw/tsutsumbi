# Rendering revision — legibility, selects, mobile first

> **Shipped.** The mockups this proposal was written against are gone; what it
> describes now lives in `public/css/app.css` and `public/js/app.js`, which are
> the authority. The document is kept for the reasoning: what was measured, what
> was ruled out, and why.

Proposal made against the interface in service (the rail, the sheet pulled up
from the bottom, the theme toggle), not against the captures in `current/` which
predate the rail rework. Nothing in the visual direction is called into question:
the navy palette, the rare orange, the 0.2rem radius, the spacing scale and the
structure of the screens are unchanged.

## 1. The scale goes up a notch

The base moves from 13.5 to 15px and **the floor from 10.5 to 12px**. It is the
floor that matters: the three sizes under 12px served only information that is
read fast and from a distance — the date, the path, the column heading — that is
to say exactly the ones that cannot be guessed when they are blurry. Nothing goes
below 12 any more, and 12 is only used in letter-spaced capitals, the one
treatment that holds at that size.

| | Before | After |
|---|---|---|
| Base | 13.5px | **15px** |
| Floor | 10.5px | **12px** |
| List row | 34px | **38px** |
| Rows read at a glance on a 1080p | ≈ 30 | **≈ 26** |

Four rows lost, knowingly paid for. **The spacing does not move**: it is the text
that grows, not the layout that loosens — an interface gaining 11% of body size
and 30% of whitespace would have lost its density for good.

The fixed columns of the grids grow by the same proportion. They are declared in
`rem` and the text they carry was not: leaving them as they were would have
turned a change of scale into a truncation bug.

## 2. The select, on mouse only

The `<select>` stays in the document and stays the field being submitted. On a
mouse, a home-grown menu takes its place; on a finger, or without JavaScript, the
native one shows.

**The criterion is the pointer, not the width** — `(hover: hover) and
(pointer: fine)`. A 1024px tablet is a finger, so is a 1440px touch laptop, and
the system wheel there is better than anything we would write: full height,
inertia, one thumb. That same criterion sets the height of everything that gets
pressed: 44px on a finger, 34px on a mouse.

What the home-grown menu brings, and it is its only justification: **the option
carries the status badge**, in the colours of the rows it filters. The native
menu shows "Blocked" as text while the list shows a red badge, and the reader
does the translation every time. It is not there because native menus would be
ugly.

What the component implements: `role="listbox"`,
`aria-activedescendant`, arrows, Home/End, type-ahead to jump to an option, Esc,
click outside, flipping upwards when there is no room below. It writes into the
`<select>` and fires `change`, so the filter auto-submission and the
status/resolution coupling already in place keep working untouched.

Three of the product's `<select>` move to the dropdown on the filter side, plus
those of the forms (type, priority, status, resolution, token creation): one
component, not two neighbouring fields of different natures.

## 3. Mobile first, and a breakpoint that is no longer a width

The sheet is rewritten in `min-width`: the rules without a query are the phone,
each breakpoint adds what a width allows, none takes back what another gave.

| Breakpoint | Subject |
|---|---|
| — (base) | Drawer, tab bar, cards, filter sheet, full width |
| 700px | **Navigation**: the drawer becomes a rail folded onto its icons |
| 1200px | **The aside**: the rail unfolds, the ticket gets its metadata column back |
| 1400px | **Results**: the filter sheet becomes a right-hand column |

**The lists are not in that table**, and it is the least obvious design point of
the proposal. Whether a row can carry six columns is a question about the width
of the list, not that of the window: the same 1440px screen gives it 1088px when
the filters are a sheet and 766px when they are a column. A rule written on the
viewport cannot see that — and it is exactly why **the sheet in service already
truncates its last column at 1440px**: 798px of columns in 774px of room,
measured. The update date is cut off today, in production.

The lists are therefore `@container` queries on `.grid`, at two measured
thresholds:

- **below 43rem** — the card;
- **43rem** — the row, each list giving up the cell it can best afford to lose:
  tickets keep the actor as a badge alone (the colour and the shape are what the
  eye reads anyway, the name costs 8.5rem), the dashboard drops the path, the
  tokens drop the agent slug that their label spells out in full;
- **57rem** — the whole row.

Checked at 800, 1000, 1200, 1440 and 1920px: no overflow left, and the
right-hand column no longer cuts anything.

**The filter threshold moves from 1200 to 1400px.** At 1200, the filter card took
a third of the screen and left 608px to the list — less than the card list it was
replacing. The sheet costs one row above the results and gives back the whole
width: 932px at 1200px, enough to hold all six columns. The column earns its
place from 1400 on. And it is worth a fixed 20rem rather than a third: a filter
form has a width it wants, and on a 2560px screen a third would be a third of
nothing.

## What was ruled out

**A fluid size (`clamp()`).** The body size would have followed the width of the
window. But here the window says nothing about the reading distance: the same
1440px is a laptop at 50cm or a monitor at a metre. A fixed scale and an honest
floor are worth more than an interpolation that looks clever.

**A 16px base.** Costed, not mocked up: the row falls to 42px, around 22 rows on
a 1080p, and the fixed columns claim another 7%. Too expensive for an instrument
whose dominant gesture is scanning.

**Replacing the native one everywhere.** A home-grown menu on mobile means taking
on inertial scrolling, the virtual keyboard, haptic feedback and the dismiss
gesture — and doing it worse, on the screen where the tool is least used.

**Aligning the lists on one more viewport breakpoint.** The brief asked for "one
rule, not five breakpoints". The container query adds zero at the window level:
it is the list that looks at itself.

## What it implied in the code

- `public/css/app.css` — replaced wholesale by the sheet written for the mockup.
- `public/js/app.js` — the dropdown component added to it as one block.
- The templates — a single, mechanical change: every `<select>` wraps in
  `<div class="select">…</div>`. For the fields rendered by Symfony, that is done
  once in `templates/form/theme.html.twig`.
- The options that carry a badge take a `data-badge` attribute: in
  `project/show.html.twig` for the status, and in the status-change form.
  Everywhere else, an option stays text.
- The column headings have no class on their cells; the column that disappears at
  the narrow threshold is therefore designated by its rank (`:nth-child(3)`).
  That is flagged in a comment at the exact spot — if a column is ever inserted,
  that is where it shows.

## To be settled

- **The actor name below 57rem.** I kept the badge alone. The other option is to
  keep the name and shave the title, which then falls to 157px: unreadable. But
  it is a trade-off, not an obvious call.
- **The filter threshold at 1400px.** On a 1440 screen, that means having the
  filter column *and* the list in compact mode. At 1366px (a common laptop), it
  is the sheet. Preferring the column from 1200 on, accepting a 608px list, is
  one number to change.
- **The four-entry tab bar** in the mockup contains "System", which only exists
  in the prototype. The product has three.
