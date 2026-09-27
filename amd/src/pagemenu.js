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
 * Context menu of page miniatures (right click or context menu key): chain pages, pin them to a
 * side, split double pages, blank pages, delete.
 *
 * Page elements carry data-pageid, data-linked (left/right), data-canlink, data-pinside, data-filler
 * and data-cansplit. Choosing an item opens actionurl&action=…&pageid=….
 *
 * @module     mod_buchbinder/pagemenu
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'core/notification'], function(Str, Notification) {

    var KEYS = ['menu_linknext', 'menu_unlink', 'menu_pinleft', 'menu_pinright', 'menu_unpin', 'blankbefore', 'blankafter',
        'deletepage', 'fillerpage', 'pagesmenu', 'confirmdeletepage', 'menu_split'];

    /**
     * Menu items for a page.
     *
     * @param {DOMStringMap} d data of the page element
     * @param {Object} str strings
     * @param {Object} options
     * @returns {Array} [action, label] or null for a separator
     */
    var itemsFor = function(d, str, options) {
        var items = [];
        if (d.linked) {
            items.push(['unlink', str.menu_unlink]);
        } else if (d.canlink && !d.filler) {
            items.push(['link', str.menu_linknext]);
        }
        if (!d.linked && !d.filler) {
            items.push(d.pinside === 'left' ? ['unpin', str.menu_unpin] : ['pinleft', str.menu_pinleft]);
            items.push(d.pinside === 'right' ? ['unpin', str.menu_unpin] : ['pinright', str.menu_pinright]);
        }
        if (d.cansplit) {
            items.push(['split', str.menu_split]);
        }
        items.push(null);
        if (options.blankpages) {
            items.push(['blankbefore', str.blankbefore], ['blankafter', str.blankafter], null);
        }
        items.push([options.deleteaction || 'deletepage', str.deletepage]);
        return items;
    };

    return {
        /**
         * Attach the menu.
         *
         * @param {HTMLElement} root
         * @param {Object} options selector (page elements), actionurl, blankpages (bool), deleteaction
         */
        init: function(root, options) {
            var requests = KEYS.map(function(key) {
                return {key: key, component: 'mod_buchbinder'};
            });
            Str.get_strings(requests).then(function(values) {
                var str = {};
                KEYS.forEach(function(key, i) {
                    str[key] = values[i];
                });
                var menu = null;
                var close = function() {
                    if (menu) {
                        menu.remove();
                        menu = null;
                    }
                };
                var open = function(page, x, y) {
                    close();
                    var d = page.dataset;
                    menu = document.createElement('div');
                    menu.className = 'bb-desk-contextmenu';
                    menu.setAttribute('role', 'menu');
                    menu.setAttribute('aria-label', str.pagesmenu);
                    if (d.filler) {
                        var note = document.createElement('div');
                        note.className = 'bb-desk-contextnote';
                        note.textContent = str.fillerpage;
                        menu.appendChild(note);
                    }
                    itemsFor(d, str, options).forEach(function(item) {
                        if (item === null) {
                            menu.appendChild(document.createElement('hr'));
                            return;
                        }
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.setAttribute('role', 'menuitem');
                        b.textContent = item[1];
                        b.addEventListener('click', function() {
                            // eslint-disable-next-line no-alert
                            if (item[0].indexOf('delete') === 0 && !window.confirm(str.confirmdeletepage)) {
                                return;
                            }
                            window.location.href = options.actionurl + '&action=' + item[0] + '&pageid=' + d.pageid;
                        });
                        menu.appendChild(b);
                    });
                    menu.addEventListener('keydown', function(e) {
                        var buttons = Array.prototype.slice.call(menu.querySelectorAll('button'));
                        var i = buttons.indexOf(document.activeElement);
                        if (e.key === 'Escape') {
                            close();
                            page.focus();
                        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            var n = buttons.length;
                            buttons[(i + (e.key === 'ArrowDown' ? 1 : n - 1)) % n].focus();
                        } else {
                            return;
                        }
                        e.preventDefault();
                    });
                    document.body.appendChild(menu);
                    var r = menu.getBoundingClientRect();
                    menu.style.left = Math.max(4, Math.min(x, window.innerWidth - r.width - 4)) + 'px';
                    menu.style.top = Math.max(4, Math.min(y, window.innerHeight - r.height - 4)) + 'px';
                    menu.querySelector('button').focus();
                };
                root.addEventListener('contextmenu', function(e) {
                    var container = e.target.closest(options.container || options.selector);
                    if (!container || !root.contains(container)) {
                        return;
                    }
                    var page = e.target.closest(options.selector) || container.querySelector(options.selector);
                    if (!page) {
                        return;
                    }
                    e.preventDefault();
                    var r = page.getBoundingClientRect();
                    open(page, e.clientX || r.right, e.clientY || r.top);
                });
                document.addEventListener('pointerdown', function(e) {
                    if (menu && !menu.contains(e.target)) {
                        close();
                    }
                });
                window.addEventListener('blur', close);
                return null;
            }).catch(Notification.exception);
        }
    };
});
