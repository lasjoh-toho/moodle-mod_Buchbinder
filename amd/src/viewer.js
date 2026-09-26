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
 * Reader: audio hotspots, text to speech, glossary cards, solution reveal,
 * text view (reflow) and column zoom anchoring on small screens.
 *
 * @module     mod_buchbinder/viewer
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {

    var SMALL = window.matchMedia('(max-width: 767.98px)');

    var Viewer = function(root, cmid) {
        this.root = root;
        this.cmid = cmid;
        this.audio = null;
        this.audiobutton = null;
        this.popover = null;
        this.glossarycache = {};
    };

    Viewer.prototype.bind = function() {
        var self = this;
        this.root.addEventListener('click', function(e) {
            var target = e.target.closest('[data-action]');
            if (!target || !self.root.contains(target)) {
                if (self.popover && !e.target.closest('.bb-popover')) {
                    self.closePopover();
                }
                return;
            }
            switch (target.dataset.action) {
                case 'audio':
                    self.play(target);
                    break;
                case 'glossary':
                    self.glossary(target);
                    break;
                case 'reveal':
                    var revealed = target.classList.toggle('revealed');
                    target.setAttribute('aria-pressed', revealed ? 'true' : 'false');
                    break;
                case 'toggle-reflow':
                    self.toggleReflow(target);
                    break;
                case 'toggle-hotspots':
                    var on = self.root.classList.toggle('bb-show-hotspots');
                    target.setAttribute('aria-pressed', on ? 'true' : 'false');
                    break;
                case 'column':
                    self.zoomColumn(target);
                    break;
                default:
                    return;
            }
            e.preventDefault();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                self.closePopover();
                self.resetZoom();
            }
        });
        SMALL.addEventListener('change', function() {
            self.resetZoom();
        });
        this.root.classList.toggle('bb-has-columns', !!this.root.querySelector('.bb-column'));
    };

    // Audio hotspots and text to speech.

    Viewer.prototype.play = function(button) {
        var same = this.audiobutton === button;
        this.stop();
        if (same) {
            return;
        }
        if (button.dataset.tts) {
            if (!window.speechSynthesis) {
                return;
            }
            var u = new SpeechSynthesisUtterance(button.dataset.tts);
            u.lang = button.dataset.lang || document.documentElement.lang;
            u.onend = this.stop.bind(this);
            window.speechSynthesis.speak(u);
        } else if (button.dataset.src) {
            this.audio = new Audio(button.dataset.src);
            this.audio.addEventListener('ended', this.stop.bind(this));
            this.audio.play().catch(Notification.exception);
        } else {
            return;
        }
        this.audiobutton = button;
        button.classList.add('playing');
    };

    Viewer.prototype.stop = function() {
        if (this.audio) {
            this.audio.pause();
            this.audio = null;
        }
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        if (this.audiobutton) {
            this.audiobutton.classList.remove('playing');
            this.audiobutton = null;
        }
    };

    // Glossary cards.

    Viewer.prototype.glossary = function(button) {
        var self = this;
        if (this.popover && this.popover.button === button) {
            this.closePopover();
            return;
        }
        this.closePopover();
        var id = button.dataset.overlayid;
        var load = this.glossarycache[id] || Ajax.call([{
            methodname: 'mod_buchbinder_get_glossary_entry',
            args: {cmid: this.cmid, overlayid: parseInt(id, 10)}
        }])[0];
        this.glossarycache[id] = load;
        Promise.all([load, Str.get_string('glossarynotfound', 'mod_buchbinder'),
                Str.get_string('glossaryopen', 'mod_buchbinder')]).then(function(results) {
            var entry = results[0];
            var card = document.createElement('div');
            card.className = 'bb-popover card shadow';
            card.setAttribute('role', 'dialog');
            card.setAttribute('aria-label', entry.concept);
            var body = document.createElement('div');
            body.className = 'card-body p-2';
            var h = document.createElement('h6');
            h.className = 'card-title mb-1';
            h.textContent = entry.concept;
            body.appendChild(h);
            var def = document.createElement('div');
            def.className = 'small';
            if (entry.found) {
                // Definition is formatted and cleaned by format_text() on the server.
                def.innerHTML = entry.definition;
            } else {
                def.textContent = results[1];
            }
            body.appendChild(def);
            if (entry.url) {
                var a = document.createElement('a');
                a.href = entry.url;
                a.target = '_blank';
                a.className = 'small';
                a.textContent = results[2];
                body.appendChild(a);
            }
            card.appendChild(body);
            var page = button.closest('.bb-page');
            page.appendChild(card);
            var br = button.getBoundingClientRect();
            var pr = page.getBoundingClientRect();
            var left = Math.max(0, Math.min(pr.width - card.offsetWidth, br.left - pr.left));
            card.style.left = left + 'px';
            card.style.top = (br.bottom - pr.top + 6) + 'px';
            card.button = button;
            button.setAttribute('aria-expanded', 'true');
            self.popover = card;
            return null;
        }).catch(Notification.exception);
    };

    Viewer.prototype.closePopover = function() {
        if (this.popover) {
            this.popover.button.setAttribute('aria-expanded', 'false');
            this.popover.remove();
            this.popover = null;
        }
    };

    // Text view (smartphone reflow).

    Viewer.prototype.toggleReflow = function(button) {
        var on = this.root.classList.toggle('bb-reflow-mode');
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        this.root.querySelectorAll('.bb-page').forEach(function(page) {
            var reflow = page.querySelector('.bb-reflow');
            var hasblocks = reflow && reflow.children.length > 0;
            if (reflow) {
                reflow.hidden = !(on && hasblocks);
            }
            var wrap = page.querySelector('.bb-zoomwrap');
            wrap.hidden = on && hasblocks;
        });
        this.resetZoom();
    };

    // Column zoom anchoring: snap a column to the full screen width.

    Viewer.prototype.zoomColumn = function(column) {
        if (!SMALL.matches) {
            return;
        }
        var sheet = column.closest('.bb-sheet');
        if (sheet.classList.contains('bb-zoomed') && sheet.dataset.column === column.dataset.column) {
            this.resetZoom();
            return;
        }
        this.resetZoom();
        var x = parseFloat(column.style.left) / 100;
        var w = parseFloat(column.style.width) / 100;
        sheet.style.width = (100 / w) + '%';
        sheet.style.marginLeft = (-x / w * 100) + '%';
        sheet.classList.add('bb-zoomed');
        sheet.dataset.column = column.dataset.column;
        column.scrollIntoView({block: 'start', behavior: 'smooth'});
    };

    Viewer.prototype.resetZoom = function() {
        this.root.querySelectorAll('.bb-sheet.bb-zoomed').forEach(function(sheet) {
            sheet.style.width = '';
            sheet.style.marginLeft = '';
            sheet.classList.remove('bb-zoomed');
            delete sheet.dataset.column;
        });
    };

    return {
        /**
         * Initialise the reader.
         *
         * @param {string} selector
         * @param {number} cmid
         */
        init: function(selector, cmid) {
            var root = document.querySelector(selector);
            if (root) {
                new Viewer(root, cmid).bind();
            }
        }
    };
});
