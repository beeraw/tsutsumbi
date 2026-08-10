/*
 * Vanilla, no framework, no build step. Three small conveniences and nothing
 * more: every rule this file touches is also enforced on the server, so the
 * interface stays correct with scripting off.
 */
(function () {
    'use strict';

    /* A destructive action asks first. */
    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        var trigger = target.closest('[data-confirm]');

        if (trigger && !window.confirm(trigger.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    /* Changing a filter applies it, without hunting for the button. The button
       stays in the markup for anyone without scripting.

       Not below 1400px: down there the form lives in a sheet, and reloading on
       every change would close it under the finger between two choices. That is
       what its own apply button is for. The width is the one the stylesheet
       uses to give the filters a column of their own — the two have to agree,
       or the sheet submits itself shut. */
    var roomy = window.matchMedia('(min-width: 87.5rem)');

    document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
        form.querySelectorAll('select, input[type="checkbox"]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (roomy.matches) {
                    form.requestSubmit();
                }
            });
        });
    });

    /* Escape closes the sheet, like every other overlay here. Tapping outside
       it, or the close, is the scrim and the labels — no script needed. */
    var filtersSwitch = document.getElementById('filters-switch');

    if (filtersSwitch) {
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && filtersSwitch.checked) {
                filtersSwitch.checked = false;
            }
        });
    }

    var app = document.querySelector('.app');

    /* The fold is applied where the reader is looking, and the preference is
       stored behind their back. The form is left intact: with scripting off it
       posts on its own and the server rebuilds the page in the new state, which
       is the same result by a slower road. */
    var railForm = document.querySelector('[data-rail-form]');

    if (app && railForm) {
        railForm.addEventListener('submit', function (event) {
            var value = event.submitter ? event.submitter.value : null;

            if (value !== 'narrow' && value !== 'wide') {
                return;
            }

            event.preventDefault();

            app.classList.toggle('rail-narrow', value === 'narrow');
            app.classList.toggle('rail-wide', value === 'wide');

            var body = new FormData(railForm);
            body.set('value', value);

            /* Nothing to read in the answer, and nothing to do about a failure
               beyond letting the next page load settle it. */
            window.fetch(railForm.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(function () {});
        });
    }

    /* The theme is swapped here rather than fetched again: the whole palette is
       one attribute on the root, and reloading a page to change an attribute is
       a lot of machinery for a class swap. The form is left posting for anyone
       without scripting, as everywhere else. */
    var themeForm = document.querySelector('[data-theme-form]');

    if (themeForm) {
        var still = window.matchMedia('(prefers-reduced-motion: reduce)');

        var applyTheme = function (value) {
            document.documentElement.setAttribute('data-theme', value);
        };

        /* A circle opening from the button that was pressed, over a snapshot of
           the page in its former theme. Without the API — or with motion turned
           down — the same swap simply happens at once. */
        var sweepFrom = function (button, value) {
            if (still.matches || !document.startViewTransition) {
                applyTheme(value);

                return;
            }

            var box = button.getBoundingClientRect();
            var x = box.left + box.width / 2;
            var y = box.top + box.height / 2;
            var radius = Math.hypot(
                Math.max(x, window.innerWidth - x),
                Math.max(y, window.innerHeight - y),
            );

            document.startViewTransition(function () {
                applyTheme(value);
            }).ready.then(function () {
                document.documentElement.animate({
                    clipPath: [
                        'circle(0px at ' + x + 'px ' + y + 'px)',
                        'circle(' + Math.ceil(radius) + 'px at ' + x + 'px ' + y + 'px)',
                    ],
                }, {
                    duration: 520,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    pseudoElement: '::view-transition-new(root)',
                });
            }).catch(function () {});
        };

        themeForm.addEventListener('submit', function (event) {
            var button = event.submitter;
            var value = button ? button.value : null;

            if (value !== 'light' && value !== 'dark') {
                return;
            }

            event.preventDefault();
            themeForm.classList.add('theme-swapping');

            window.setTimeout(function () {
                sweepFrom(button, value);
                themeForm.classList.remove('theme-swapping');
            }, still.matches ? 0 : 180);

            /* A submit button is not part of a form's data unless it is the one
               that was pressed, and this form is never actually submitted. */
            var stored = new FormData(themeForm);
            stored.set('value', value);

            window.fetch(themeForm.action, {
                method: 'POST',
                body: stored,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(function () {});
        });
    }

    /* The phone drawer. The bar below reaches both destinations without any of
       this; what the drawer adds is the rail's own content — the pinned
       projects and the session — which has nowhere else to go on a phone. */
    var drawerOpener = document.querySelector('[data-drawer-open]');

    if (app && drawerOpener) {
        var setDrawer = function (open) {
            app.classList.toggle('drawer-open', open);
            document.body.classList.toggle('is-locked', open);
            drawerOpener.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                var first = app.querySelector('.drawer-close');

                if (first) {
                    first.focus();
                }
            } else {
                drawerOpener.focus();
            }
        };

        drawerOpener.addEventListener('click', function () {
            setDrawer(!app.classList.contains('drawer-open'));
        });

        document.querySelectorAll('[data-drawer-close]').forEach(function (control) {
            control.addEventListener('click', function () {
                setDrawer(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && app.classList.contains('drawer-open')) {
                setDrawer(false);
            }
        });

        /* Following a link inside the drawer leaves the page anyway; closing it
           first spares the reader a panel sliding away under a new screen. */
        app.querySelectorAll('.rail a').forEach(function (link) {
            link.addEventListener('click', function () {
                setDrawer(false);
            });
        });
    }

    /* A resolution only means something on a closed ticket, so the field only
       shows for a status that carries one. The domain refuses the inconsistent
       pairing either way; this just spares a round trip. */
    var resolutionWrapper = document.querySelector('[data-resolution-field]');

    if (resolutionWrapper) {
        var statusField = document.querySelector('select[name$="[status]"]');

        if (statusField) {
            var sync = function () {
                resolutionWrapper.hidden = statusField.value !== 'done';
            };

            statusField.addEventListener('change', sync);
            sync();
        }
    }
})();

/* ------------------------------------------------------------------ dropdown */

/*
 * The one control that is replaced rather than styled, and only on a machine
 * with a mouse.
 *
 * The contract, in three lines:
 *
 *   - The <select> stays in the DOM and stays the field that is submitted. This
 *     writes into it and fires `change`, so the filter auto-submit and the
 *     resolution/status pairing above keep working untouched.
 *   - It is built on every machine; a media query in the stylesheet decides
 *     whether it or the native menu is on screen. Plugging in a mouse is then a
 *     repaint, not a rebuild.
 *   - With scripting off nothing here runs, and the native menu is the whole
 *     interface. Nothing below is load-bearing.
 *
 * The option carries what the native menu cannot: the status chip, in the same
 * colours as the rows being filtered. That is the entire reason it exists.
 */
(function () {
    'use strict';

    var CHECK = 'M5 12l5 5l10 -10';
    var CARET = 'M6 9l6 6l6 -6';
    var counter = 0;

    function svg(path, size) {
        var node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

        node.setAttribute('width', size);
        node.setAttribute('height', size);
        node.setAttribute('viewBox', '0 0 24 24');
        node.setAttribute('fill', 'none');
        node.setAttribute('stroke', 'currentColor');
        node.setAttribute('stroke-width', '2');
        node.setAttribute('stroke-linecap', 'round');
        node.setAttribute('stroke-linejoin', 'round');
        node.setAttribute('aria-hidden', 'true');

        var shape = document.createElementNS('http://www.w3.org/2000/svg', 'path');

        shape.setAttribute('d', path);
        node.appendChild(shape);

        return node;
    }

    /* The label of an option, plus its chip when it has one. Used twice: in the
       list, and on the button that shows what is chosen. */
    function paint(target, option) {
        target.textContent = '';

        var badge = option.getAttribute('data-badge');

        if (badge) {
            var chip = document.createElement('span');

            chip.className = 'badge badge-' + badge;
            chip.textContent = option.textContent.trim();
            target.appendChild(chip);

            return;
        }

        var text = document.createElement('span');

        text.className = 'dropdown-label';
        text.textContent = option.textContent.trim();
        target.appendChild(text);
    }

    function enhance(select) {
        /* Multiple selects and grouped options are two different controls; this
           one only replaces the plain single-choice menu. */
        if (select.multiple || select.querySelector('optgroup')) {
            return;
        }

        var shell = select.closest('.select');

        if (!shell || shell.classList.contains('has-dropdown')) {
            return;
        }

        var id = select.id || 'dropdown-' + (counter += 1);
        var options = Array.prototype.slice.call(select.options);
        var active = select.selectedIndex < 0 ? 0 : select.selectedIndex;

        var button = document.createElement('button');
        var value = document.createElement('span');
        var popup = document.createElement('div');

        button.type = 'button';
        button.className = 'dropdown-button';
        button.id = id + '-button';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', id + '-popup');
        button.disabled = select.disabled;

        value.className = 'dropdown-value';
        value.id = id + '-value';
        button.appendChild(value);
        button.appendChild(svg(CARET, 15)).classList.add('dropdown-caret');

        popup.className = 'dropdown-popup';
        popup.id = id + '-popup';
        popup.setAttribute('role', 'listbox');
        popup.tabIndex = -1;

        /* The button says what is chosen; the field's own label says what is
           being chosen. Both are read out, in that order. */
        var label = document.querySelector('label[for="' + CSS.escape(select.id) + '"]');

        if (label) {
            label.id = label.id || id + '-label';
            popup.setAttribute('aria-labelledby', label.id);
            button.setAttribute('aria-labelledby', label.id + ' ' + value.id);

            /* The label points at a select that may be off screen. A click on it
               reaches whichever of the two is actually showing. */
            label.addEventListener('click', function (event) {
                if (window.getComputedStyle(button).display !== 'none') {
                    event.preventDefault();
                    button.focus();
                }
            });
        }

        var rows = options.map(function (option, index) {
            var row = document.createElement('div');

            row.className = 'dropdown-option';
            row.id = id + '-option-' + index;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', index === select.selectedIndex ? 'true' : 'false');

            var check = document.createElement('span');

            check.className = 'dropdown-check';
            check.appendChild(svg(CHECK, 13));
            row.appendChild(check);

            var body = document.createElement('span');

            body.className = 'dropdown-value';
            paint(body, option);
            row.appendChild(body);

            popup.appendChild(row);

            return row;
        });

        paint(value, options[active] || document.createElement('option'));

        shell.appendChild(button);
        shell.appendChild(popup);
        shell.classList.add('has-dropdown');

        function isOpen() {
            return shell.classList.contains('is-open');
        }

        function highlight(index) {
            active = Math.max(0, Math.min(index, rows.length - 1));

            rows.forEach(function (row, i) {
                row.classList.toggle('is-active', i === active);
            });

            popup.setAttribute('aria-activedescendant', rows[active].id);
            rows[active].scrollIntoView({ block: 'nearest' });
        }

        function open() {
            if (button.disabled) {
                return;
            }

            shell.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');

            /* Opened near the bottom of the window the list would push the page
               under the reader. Measured once it is laid out, flipped above the
               field when there is no room below. */
            var box = button.getBoundingClientRect();
            var room = window.innerHeight - box.bottom;

            popup.classList.toggle('is-above', room < popup.offsetHeight + 16 && box.top > room);

            highlight(select.selectedIndex < 0 ? 0 : select.selectedIndex);
        }

        function close(focus) {
            shell.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            popup.removeAttribute('aria-activedescendant');

            if (focus) {
                button.focus();
            }
        }

        function choose(index) {
            select.selectedIndex = index;

            rows.forEach(function (row, i) {
                row.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });

            paint(value, options[index]);
            close(true);

            /* What the rest of the interface listens to. The auto-submit on the
               filter form and the resolution field both hang off this. */
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        button.addEventListener('click', function () {
            if (isOpen()) {
                close(true);
            } else {
                open();
            }
        });

        /* One highlight, and the mouse moves the same one the keyboard does —
           two competing highlights is the classic way to make a menu feel
           broken. */
        rows.forEach(function (row, index) {
            row.addEventListener('mousemove', function () {
                if (index !== active) {
                    highlight(index);
                }
            });

            row.addEventListener('click', function () {
                choose(index);
            });
        });

        var typed = '';
        var typedAt = 0;

        /* The list never takes the focus — the button keeps it and points at the
           active row through aria-activedescendant — so every key is read here,
           and a closed menu only listens for the four that open it. */
        button.addEventListener('keydown', function (event) {
            if (!isOpen()) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp'
                    || event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open();
                }

                return;
            }

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    highlight(active + 1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    highlight(active - 1);
                    break;
                case 'Home':
                    event.preventDefault();
                    highlight(0);
                    break;
                case 'End':
                    event.preventDefault();
                    highlight(rows.length - 1);
                    break;
                case 'Enter':
                case ' ':
                    event.preventDefault();
                    choose(active);
                    break;
                case 'Escape':
                    event.preventDefault();
                    close(true);
                    break;
                case 'Tab':
                    close(false);
                    break;
                default:
                    /* Typing jumps, as it does in a native menu. */
                    if (event.key.length !== 1) {
                        return;
                    }

                    var now = event.timeStamp;

                    typed = now - typedAt > 700 ? event.key : typed + event.key;
                    typedAt = now;

                    var match = options.findIndex(function (option) {
                        return option.textContent.trim().toLowerCase().indexOf(typed.toLowerCase()) === 0;
                    });

                    if (match >= 0) {
                        highlight(match);
                    }
            }
        });

        document.addEventListener('click', function (event) {
            if (isOpen() && !shell.contains(event.target)) {
                close(false);
            }
        });

        /* Something else may disable the field — the resolution filter is dead
           until closed tickets are in the list — or change its value. The button
           follows rather than holding its own copy of the truth. */
        var observer = new MutationObserver(function () {
            button.disabled = select.disabled;

            if (button.disabled && isOpen()) {
                close(false);
            }
        });

        observer.observe(select, { attributes: true, attributeFilter: ['disabled'] });

        select.addEventListener('change', function () {
            if (select.selectedIndex >= 0 && options[select.selectedIndex]) {
                paint(value, options[select.selectedIndex]);

                rows.forEach(function (row, i) {
                    row.setAttribute('aria-selected', i === select.selectedIndex ? 'true' : 'false');
                });
            }
        });
    }

    document.querySelectorAll('.select > select').forEach(enhance);
})();

/* ---------------------------------------------------------------- copy key */

/*
 * A ticket key is read to be retyped elsewhere, and retyping is where the digit
 * gets dropped. One press puts it on the clipboard instead.
 *
 * Built here rather than in the template, for the same reason as the dropdown:
 * a clipboard is not something the server can promise. Without one — an
 * insecure origin, an engine without the API — nothing is upgraded and the key
 * stays the text it was, which is a worse affordance but never a broken one.
 */
(function () {
    'use strict';

    if (!navigator.clipboard) {
        return;
    }

    var COPY = [
        'M8 8m0 2a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2z',
        'M16 8v-2a2 2 0 0 0 -2 -2h-8a2 2 0 0 0 -2 2v8a2 2 0 0 0 2 2h2',
    ];
    var DONE = ['M5 12l5 5l10 -10'];

    function svg(paths) {
        var node = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

        node.setAttribute('width', '14');
        node.setAttribute('height', '14');
        node.setAttribute('viewBox', '0 0 24 24');
        node.setAttribute('fill', 'none');
        node.setAttribute('stroke', 'currentColor');
        node.setAttribute('stroke-width', '1.75');
        node.setAttribute('stroke-linecap', 'round');
        node.setAttribute('stroke-linejoin', 'round');
        node.setAttribute('aria-hidden', 'true');

        paths.forEach(function (d) {
            var shape = document.createElementNS('http://www.w3.org/2000/svg', 'path');

            shape.setAttribute('d', d);
            node.appendChild(shape);
        });

        return node;
    }

    document.querySelectorAll('[data-copy]').forEach(function (source) {
        var text = source.getAttribute('data-copy');
        var label = source.getAttribute('data-copy-label') || text;
        var done = source.getAttribute('data-copy-done') || '';

        var button = document.createElement('button');

        button.type = 'button';
        button.className = source.className + ' is-copyable';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.appendChild(document.createTextNode(text));

        var mark = document.createElement('span');

        mark.className = 'copy-mark';
        mark.appendChild(svg(COPY));
        button.appendChild(mark);

        /* The visual feedback is a swapped icon, which a screen reader has no
           way of noticing. This is the same confirmation, said out loud. */
        var spoken = document.createElement('span');

        spoken.className = 'visually-hidden';
        spoken.setAttribute('role', 'status');

        source.replaceWith(button);
        button.after(spoken);

        var revert = null;

        button.addEventListener('click', function () {
            navigator.clipboard.writeText(text).then(function () {
                mark.textContent = '';
                mark.appendChild(svg(DONE));
                button.classList.add('is-copied');
                spoken.textContent = done;

                window.clearTimeout(revert);
                revert = window.setTimeout(function () {
                    mark.textContent = '';
                    mark.appendChild(svg(COPY));
                    button.classList.remove('is-copied');
                    spoken.textContent = '';
                }, 1800);
            }).catch(function () {
                /* Refused permission, or a page that lost the focus mid-press.
                   The key is still on screen and still selectable. */
            });
        });
    });
})();
