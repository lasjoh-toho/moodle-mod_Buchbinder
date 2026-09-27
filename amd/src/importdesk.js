// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Import desk: select the pages to take into the document, hide passages of continuous sources and
 * measure their text with Pretext so the layout flow knows the exact number of pages.
 *
 * @module     mod_buchbinder/importdesk
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str', 'mod_buchbinder/pagemenu', 'mod_buchbinder/pretext'],
function(Ajax, Notification, Str, PageMenu, Pretext) {

    /** @var {number} Width of the reference page in px; text frames use 1.85% of it as body text size. */
    var PAGE_WIDTH = 1000;

    /** @var {string} Version of the measuring method, part of the measure key. */
    var METHOD = 'pretext1';

    var STRINGS = ['measuring', 'resetting', 'passagesonpage', 'passages', 'passageadopted'];

    // Text measuring.

    /**
     * Collapse white space like CSS (white-space: normal) and remember the source position of every character.
     *
     * Positions count code points, like the layout flow on the server.
     *
     * @param {string} raw
     * @returns {Object} text and map (index in text => code point index in raw)
     */
    var normalize = function(raw) {
        var text = '';
        var map = [];
        var space = true;
        var cp = 0;
        for (var ch of raw) {
            if (/^[ \t\n\r\f]$/.test(ch)) {
                if (!space) {
                    text += ' ';
                    map.push(cp);
                    space = true;
                }
            } else {
                text += ch;
                for (var u = 0; u < ch.length; u++) {
                    map.push(cp);
                }
                space = false;
            }
            cp++;
        }
        if (text.endsWith(' ')) {
            text = text.slice(0, -1);
            map.pop();
        }
        return {text: text, map: map, length: cp};
    };

    /**
     * Metrics of an element inside a text frame, read from the style sheet.
     *
     * @param {HTMLElement} frame probe frame
     * @param {string} tag
     * @returns {Object}
     */
    var metrics = function(frame, tag) {
        var el = document.createElement(tag);
        el.textContent = 'x';
        frame.appendChild(el);
        var cs = window.getComputedStyle(el);
        var size = parseFloat(cs.fontSize);
        var lh = parseFloat(cs.lineHeight);
        var result = {
            font: cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily,
            family: cs.fontFamily,
            lh: isNaN(lh) ? size * 1.2 : lh,
            mb: parseFloat(cs.marginBottom) || 0,
            indent: (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.marginLeft) || 0)
        };
        el.remove();
        return result;
    };

    /**
     * Probe the text frame styles of a page style.
     *
     * @param {string} classes classes of the frame
     * @param {number} scale font scale
     * @returns {Object} tag => metrics
     */
    var probe = function(classes, scale) {
        var sheet = document.createElement('div');
        sheet.className = 'bb-sheet';
        sheet.style.cssText = 'position:absolute;left:-10000px;top:0;visibility:hidden;width:' + PAGE_WIDTH + 'px';
        var frame = document.createElement('div');
        frame.className = 'bb-textframe ' + classes;
        frame.style.setProperty('--bb-scale', scale);
        sheet.appendChild(frame);
        document.body.appendChild(sheet);
        var result = {};
        ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol'].forEach(function(tag) {
            result[tag] = metrics(frame, tag);
        });
        sheet.remove();
        return result;
    };

    /**
     * Lay out plain text and report the line ends as positions in the raw text.
     *
     * @param {string} raw text as in the html (white space not collapsed)
     * @param {string} font canvas font
     * @param {number} width px
     * @param {number} lh line height px
     * @returns {Object} count (lines) and ends (code point position of each line end in raw)
     */
    var layoutText = function(raw, font, width, lh) {
        var n = normalize(raw);
        if (n.text === '') {
            return {count: 0, ends: []};
        }
        var lines = Pretext.layoutWithLines(Pretext.prepareWithSegments(n.text, font), width, lh).lines;
        var starts = [0];
        for (var i = 1; i < lines.length; i++) {
            var head = lines[i].text.replace(/^ +/, '').slice(0, 16);
            var idx = head ? n.text.indexOf(head, starts[i - 1] + 1) : -1;
            starts.push(idx > starts[i - 1] ? idx : starts[i - 1] + lines[i - 1].text.length);
        }
        var ends = [];
        for (var k = 0; k < lines.length; k++) {
            // The line ends before the white space that separates it from the next line.
            ends.push(k + 1 < lines.length ? n.map[Math.min(starts[k + 1], n.map.length - 1)] - 1 : n.length);
        }
        return {count: lines.length, ends: ends};
    };

    /**
     * Measure a passage like the text frames of the page style show it.
     *
     * @param {Object} passage
     * @param {Object} styles probed metrics (body, note)
     * @param {Object} geometry textwidth, notewidth, pageheight
     * @returns {Object|null} measure for flow::set_measure(), heights relative to the page height
     */
    var measurePassage = function(passage, styles, geometry) {
        var box = document.createElement('div');
        box.innerHTML = passage.html;
        var node = box.firstElementChild;
        if (!node) {
            return null;
        }
        var tag = passage.tag;
        var m = styles.body[tag];
        var ph = geometry.pageheight;
        var result = null;
        if (tag === 'p' || /^h[1-6]$/.test(tag)) {
            // Lines separated by <br> are laid out separately.
            var lines = [];
            var base = 0;
            node.innerHTML.split(/<br\s*\/?>/i).forEach(function(part) {
                var seg = document.createElement('div');
                seg.innerHTML = part;
                var raw = seg.textContent;
                var laid = layoutText(raw, m.font, geometry.textwidth, m.lh);
                laid.ends.forEach(function(end) {
                    lines.push(base + end);
                });
                base += Array.from(raw).length;
            });
            if (!lines.length) {
                return null;
            }
            result = {h: (lines.length * m.lh + m.mb) / ph, lh: m.lh / ph, blh: styles.body.p.lh / ph, lines: lines};
        } else if (tag === 'ul' || tag === 'ol' || tag === 'blockquote') {
            var count = 0;
            var width = geometry.textwidth - m.indent;
            var items = tag === 'blockquote' ? node.querySelectorAll('p') : node.querySelectorAll('li');
            var itemfont = tag === 'blockquote' ? m.font : styles.body.p.font;
            (items.length ? Array.prototype.slice.call(items) : [node]).forEach(function(item) {
                var clone = item.cloneNode(true);
                clone.querySelectorAll('ul, ol').forEach(function(nested) {
                    nested.remove();
                });
                count += Math.max(1, layoutText(clone.textContent, itemfont, width, styles.body.p.lh).count);
            });
            var gaps = tag === 'blockquote' ? Math.max(0, items.length - 1) * styles.body.p.mb : 0;
            result = {h: (count * styles.body.p.lh + gaps + m.mb) / ph};
        } else {
            // Tables and preformatted text are estimated on the server.
            return null;
        }
        if (passage.notes && passage.notes.length) {
            var nm = styles.note.p;
            result.notes = passage.notes.map(function(html) {
                var nb = document.createElement('div');
                nb.innerHTML = html;
                var paragraphs = nb.querySelectorAll('p');
                var total = 0;
                (paragraphs.length ? Array.prototype.slice.call(paragraphs) : [nb]).forEach(function(p) {
                    total += Math.max(1, layoutText(p.textContent, nm.font, geometry.notewidth, nm.lh).count) * nm.lh + nm.mb;
                });
                return total / ph;
            });
        }
        return result;
    };

    /**
     * Short hash of a string.
     *
     * @param {string} value
     * @returns {string}
     */
    var hash = function(value) {
        var h = 5381;
        for (var i = 0; i < value.length; i++) {
            h = (h * 33 + value.charCodeAt(i)) % 4294967296;
        }
        return h.toString(16);
    };

    // The desk.

    var ImportDesk = function(root) {
        this.root = root;
        this.config = JSON.parse(root.dataset.config);
        this.str = {};
        this.panel = root.querySelector('.bb-import-passages');
        this.timer = null;
    };

    ImportDesk.prototype.start = function() {
        var self = this;
        return Str.get_strings(STRINGS.map(function(key) {
            return {key: key, component: 'mod_buchbinder'};
        })).then(function(values) {
            STRINGS.forEach(function(key, i) {
                self.str[key] = values[i];
            });
            self.bind();
            PageMenu.init(self.root, {selector: '.bb-import-page[data-pageid]', actionurl: self.config.actionurl});
            self.measureIfNeeded();
            return null;
        }).catch(Notification.exception);
    };

    /**
     * Metrics of the current page style.
     *
     * @returns {Object}
     */
    ImportDesk.prototype.styles = function() {
        var tufte = this.config.pagestyle === 'tufte';
        var m = this.config.metrics;
        return {
            body: probe(tufte ? 'bb-style-tufte' : '', 1),
            note: probe('bb-style-sidenote', m.notescale),
            geometry: {
                textwidth: m.textwidth[this.config.pagestyle] * PAGE_WIDTH,
                notewidth: m.notewidth * PAGE_WIDTH,
                pageheight: PAGE_WIDTH * m.aspect
            }
        };
    };

    /**
     * Key of fonts and settings: the source is measured again when it changes.
     *
     * @param {Object} styles
     * @returns {string}
     */
    ImportDesk.prototype.measureKey = function(styles) {
        return METHOD + hash([styles.body.p.font, styles.body.h1.font, styles.note.p.font, this.config.pagestyle,
            JSON.stringify(this.config.metrics)].join('|'));
    };

    /**
     * Measure all visible passages.
     *
     * @param {Object} styles
     * @param {number[]} hidden
     * @returns {Object} index => measure
     */
    ImportDesk.prototype.measure = function(styles, hidden) {
        var result = {};
        this.config.passages.forEach(function(passage) {
            if (passage.type !== 'text' || passage.adopted || hidden.indexOf(passage.index) !== -1) {
                return;
            }
            var m = measurePassage(passage, styles, styles.geometry);
            if (m) {
                result[passage.index] = m;
            }
        });
        return result;
    };

    /**
     * Measure the source with Pretext if it was set with estimates or other fonts.
     */
    ImportDesk.prototype.measureIfNeeded = function() {
        if (!this.config.flowed || !this.config.pagecount) {
            return;
        }
        var styles = this.styles();
        var key = this.measureKey(styles);
        if (key === this.config.measurekey) {
            return;
        }
        this.setSource(null, styles, key, this.str.measuring);
    };

    /**
     * Set the source again on the server and reload.
     *
     * @param {number[]|null} hidden new hidden passages, null: unchanged
     * @param {Object} styles
     * @param {string} key
     * @param {string} status
     */
    ImportDesk.prototype.setSource = function(hidden, styles, key, status) {
        var self = this;
        var current = hidden || this.config.passages.filter(function(p) {
            return p.hidden;
        }).map(function(p) {
            return p.index;
        });
        var badge = this.root.querySelector('[data-region="measure"]');
        if (badge) {
            badge.textContent = status;
            badge.className = 'badge badge-info bg-info';
        }
        this.root.classList.add('bb-busy');
        Ajax.call([{
            methodname: 'mod_buchbinder_set_source',
            args: {
                cmid: this.config.cmid,
                sourceid: this.config.sourceid,
                hidden: hidden ? JSON.stringify(hidden) : '',
                measure: JSON.stringify(this.measure(styles, current)),
                measurekey: key
            }
        }])[0].then(function() {
            window.location.reload();
            return null;
        }).catch(function(e) {
            self.root.classList.remove('bb-busy');
            Notification.exception(e);
        });
    };

    // Selection of the pages to take over.

    ImportDesk.prototype.checkboxes = function() {
        return Array.prototype.slice.call(this.root.querySelectorAll('[data-region="select"]'));
    };

    /**
     * Write the selected pages as a compact range ("1-3, 5").
     */
    ImportDesk.prototype.selectionToRange = function() {
        var numbers = this.checkboxes().filter(function(c) {
            return c.checked;
        }).map(function(c) {
            return parseInt(c.value, 10);
        }).sort(function(a, b) {
            return a - b;
        });
        var parts = [];
        for (var i = 0; i < numbers.length; i++) {
            var start = numbers[i];
            while (i + 1 < numbers.length && numbers[i + 1] === numbers[i] + 1) {
                i++;
            }
            parts.push(start === numbers[i] ? String(start) : start + '-' + numbers[i]);
        }
        var range = this.root.querySelector('[data-region="range"]');
        if (range) {
            range.value = parts.join(', ');
        }
        this.markSelection();
    };

    /**
     * Tick the pages of the typed range.
     */
    ImportDesk.prototype.rangeToSelection = function() {
        var range = this.root.querySelector('[data-region="range"]');
        var total = this.checkboxes().length;
        var selected = {};
        range.value.split(',').forEach(function(part) {
            var m = part.trim().match(/^(\d+)?\s*(-)?\s*(\d+)?$/);
            if (!m || (!m[1] && !m[3])) {
                return;
            }
            var from = m[1] ? parseInt(m[1], 10) : 1;
            var to = from;
            if (m[2]) {
                to = m[3] ? parseInt(m[3], 10) : total;
            }
            for (var n = Math.max(1, from); n <= Math.min(total, to); n++) {
                selected[n] = true;
            }
        });
        this.checkboxes().forEach(function(c) {
            c.checked = !!selected[c.value];
        });
        this.markSelection();
    };

    ImportDesk.prototype.markSelection = function() {
        this.checkboxes().forEach(function(c) {
            c.closest('.bb-import-page').classList.toggle('selected', c.checked);
        });
    };

    // Passages.

    /**
     * Show the passages of the source, those on a page highlighted.
     *
     * @param {HTMLElement|null} page
     */
    ImportDesk.prototype.showPassages = function(page) {
        var self = this;
        var list = this.panel.querySelector('[data-region="passages"]');
        var onpage = page ? (this.config.flowmap[page.dataset.pageid] || []) : [];
        var title = this.str.passages;
        if (page) {
            title = this.str.passagesonpage.replace('{$a}', page.dataset.number);
        }
        this.panel.querySelector('[data-region="passagestitle"]').textContent = title;
        list.replaceChildren();
        var first = null;
        this.config.passages.forEach(function(p) {
            var li = document.createElement('li');
            li.className = 'bb-passage' + (onpage.indexOf(p.index) !== -1 ? ' onpage' : '') + (p.hidden ? ' hidden-passage' : '');
            var label = document.createElement('label');
            var box = document.createElement('input');
            box.type = 'checkbox';
            box.checked = !p.hidden;
            box.disabled = p.adopted;
            box.dataset.index = p.index;
            label.appendChild(box);
            var kind = document.createElement('span');
            kind.className = 'bb-passage-kind';
            kind.textContent = p.label;
            label.appendChild(kind);
            var text = document.createElement('span');
            text.className = 'bb-passage-text';
            text.textContent = p.adopted ? p.preview + ' (' + self.str.passageadopted + ')' : p.preview;
            label.appendChild(text);
            li.appendChild(label);
            list.appendChild(li);
            if (!first && onpage.indexOf(p.index) !== -1) {
                first = li;
            }
            box.addEventListener('change', function() {
                p.hidden = !box.checked;
                li.classList.toggle('hidden-passage', p.hidden);
                self.scheduleReset();
            });
        });
        this.panel.hidden = false;
        if (first) {
            first.scrollIntoView({block: 'center'});
        }
        var focus = first ? first.querySelector('input') : list.querySelector('input');
        if (focus) {
            focus.focus();
        }
    };

    /**
     * Set the source again shortly after the last change of the passages.
     */
    ImportDesk.prototype.scheduleReset = function() {
        var self = this;
        clearTimeout(this.timer);
        this.timer = setTimeout(function() {
            var hidden = self.config.passages.filter(function(p) {
                return p.hidden;
            }).map(function(p) {
                return p.index;
            });
            var styles = self.styles();
            self.setSource(hidden, styles, self.measureKey(styles), self.str.resetting);
        }, 900);
    };

    // Events.

    ImportDesk.prototype.bind = function() {
        var self = this;
        var root = this.root;
        var dialog = root.querySelector('.bb-import-dialog');
        var opener = null;
        var openDialog = function(button) {
            opener = button;
            dialog.hidden = false;
            root.querySelectorAll('[data-action="open-import"]').forEach(function(b) {
                b.setAttribute('aria-expanded', 'true');
            });
            var field = dialog.querySelector('input:not([type="hidden"]), select, button');
            if (field) {
                field.focus();
            }
        };
        var closeDialog = function() {
            dialog.hidden = true;
            root.querySelectorAll('[data-action="open-import"]').forEach(function(b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (opener) {
                opener.focus();
            }
        };
        root.querySelectorAll('[data-action="open-import"]').forEach(function(button) {
            button.addEventListener('click', function() {
                openDialog(button);
            });
        });
        root.querySelectorAll('[data-action="close-import"]').forEach(function(button) {
            button.addEventListener('click', closeDialog);
        });
        dialog.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDialog();
            }
        });
        dialog.addEventListener('click', function(e) {
            if (e.target === dialog) {
                closeDialog();
            }
        });
        root.querySelectorAll('[data-action="autosubmit"]').forEach(function(field) {
            field.addEventListener('change', function() {
                field.form.submit();
            });
        });
        root.querySelectorAll('[data-action="confirm"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                // eslint-disable-next-line no-alert
                if (!window.confirm(link.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });

        // Selection.
        var range = root.querySelector('[data-region="range"]');
        if (range) {
            range.addEventListener('input', function() {
                self.rangeToSelection();
            });
            this.markSelection();
        }
        this.checkboxes().forEach(function(c) {
            c.addEventListener('change', function() {
                self.selectionToRange();
            });
        });
        var all = root.querySelector('[data-action="select-all"]');
        if (all) {
            all.addEventListener('click', function() {
                self.checkboxes().forEach(function(c) {
                    c.checked = true;
                });
                self.selectionToRange();
            });
        }
        var none = root.querySelector('[data-action="select-none"]');
        if (none) {
            none.addEventListener('click', function() {
                self.checkboxes().forEach(function(c) {
                    c.checked = false;
                });
                self.selectionToRange();
            });
        }

        // A click on a page shows its passages (continuous sources) or selects it.
        var activate = function(page) {
            if (self.config.flowed) {
                root.querySelectorAll('.bb-import-page.active').forEach(function(p) {
                    p.classList.remove('active');
                });
                page.classList.add('active');
                self.showPassages(page);
            } else {
                var box = page.querySelector('[data-region="select"]');
                box.checked = !box.checked;
                self.selectionToRange();
            }
        };
        root.querySelectorAll('.bb-import-page[data-pageid]').forEach(function(page) {
            page.addEventListener('click', function(e) {
                if (e.target.closest('.bb-import-select')) {
                    return;
                }
                activate(page);
            });
            page.addEventListener('keydown', function(e) {
                if ((e.key === 'Enter' || e.key === ' ') && e.target === page) {
                    e.preventDefault();
                    activate(page);
                }
            });
        });
        var passages = root.querySelector('[data-action="open-passages"]');
        if (passages) {
            passages.addEventListener('click', function() {
                self.showPassages(null);
            });
        }
        var closePassages = root.querySelector('[data-action="close-passages"]');
        if (closePassages) {
            closePassages.addEventListener('click', function() {
                self.panel.hidden = true;
            });
        }
        this.panel.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                self.panel.hidden = true;
            }
        });
    };

    return {
        /**
         * Start the import desk.
         *
         * @param {string} selector
         */
        init: function(selector) {
            var root = document.querySelector(selector);
            if (root) {
                new ImportDesk(root).start();
            }
        },
        // For tests.
        normalize: normalize
    };
});
