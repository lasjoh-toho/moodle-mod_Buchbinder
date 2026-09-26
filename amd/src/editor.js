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
 * In-place canvas editor: draw, move and resize overlays, colour matched type-over,
 * audio recording, glossary links, reflow blocks and column detection.
 *
 * @module     mod_buchbinder/editor
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {

    var STRINGS = [
        'overlay_textbox', 'overlay_mask', 'overlay_audio', 'overlay_glossary', 'overlay_reflow', 'overlay_column',
        'text', 'fontsize', 'textcolor', 'bgcolor', 'matchbackground', 'bold', 'align', 'align_left', 'align_center',
        'align_right', 'maskstyle', 'mask_white', 'mask_black', 'revealable', 'label', 'audiosource', 'audio_file',
        'audio_tts', 'record', 'stoprecording', 'uploadaudio', 'ttstext', 'ttslang', 'preview', 'glossary', 'term',
        'reflowrole', 'role_p', 'role_h2', 'role_h3', 'role_li', 'columnhelp', 'save', 'deleteoverlay', 'saved',
        'nocolumnsfound', 'columnsfound', 'audiosaved', 'errorfiletoolarge', 'selectoverlay', 'clipcreated',
        'overlay_textframe', 'overlay_imageframe', 'fontscale', 'textcolumns', 'frameborder', 'nobackground', 'uploadimage',
        'imagefit', 'fit_contain', 'fit_cover', 'alttext', 'caption', 'edittext', 'italic', 'role_h2', 'role_h3',
        'paragraph', 'bulletlist', 'numberedlist', 'overset', 'imagesaved', 'confirmdeletepage', 'newframetext'
    ];

    var Editor = function(root) {
        this.root = root;
        this.config = JSON.parse(root.dataset.config);
        this.stages = {};
        root.querySelectorAll('.bb-editor-stage').forEach(function(stage) {
            this.stages[stage.dataset.pageid] = stage;
        }.bind(this));
        this.pages = {};
        (this.config.pages || []).forEach(function(page) {
            this.pages[page.pageid] = page;
        }.bind(this));
        this.currentPage = Object.keys(this.stages)[0];
        this.panel = root.querySelector('.bb-editor-panel');
        this.editing = null;
        this.tool = 'select';
        this.overlays = {};
        this.selected = null;
        this.recorder = null;
        this.str = {};
    };

    Editor.prototype.start = function() {
        var self = this;
        var requests = STRINGS.map(function(key) {
            return {key: key, component: 'mod_buchbinder'};
        });
        return Str.get_strings(requests).then(function(values) {
            STRINGS.forEach(function(key, i) {
                self.str[key] = values[i];
            });
            self.config.overlays.forEach(function(o) {
                self.addElement(o);
            });
            self.bindEvents();
            return null;
        }).catch(Notification.exception);
    };

    // Geometry helpers.

    Editor.prototype.relPoint = function(e, stage, noclamp) {
        var r = stage.getBoundingClientRect();
        var x = (e.clientX - r.left) / r.width;
        var y = (e.clientY - r.top) / r.height;
        return noclamp ? {x: x, y: y} : {x: Math.max(0, Math.min(1, x)), y: Math.max(0, Math.min(1, y))};
    };

    Editor.prototype.imageOf = function(pageid) {
        var stage = this.stages[pageid];
        return stage ? stage.querySelector('img.bb-image') : null;
    };

    /**
     * Snap a value to the guides (type area, page edges and centre) of a page.
     *
     * @param {number} value
     * @param {string} axis x or y
     * @param {string} pageid
     * @returns {number|null} snapped value or null
     */
    Editor.prototype.snap = function(value, axis, pageid) {
        var page = this.pages[pageid];
        var targets = [0, 1];
        if (axis === 'x') {
            targets.push(0.5);
        }
        if (page && page.guides) {
            var g = page.guides;
            if (axis === 'x') {
                targets.push(g[0], g[0] + g[2]);
            } else {
                targets.push(g[1], g[1] + g[3]);
            }
        }
        var best = null;
        targets.forEach(function(t) {
            if (Math.abs(t - value) < 0.008 && (best === null || Math.abs(t - value) < Math.abs(best - value))) {
                best = t;
            }
        });
        return best;
    };

    Editor.prototype.place = function(el, o) {
        el.style.left = (o.x * 100) + '%';
        el.style.top = (o.y * 100) + '%';
        el.style.width = (o.w * 100) + '%';
        el.style.height = (o.h * 100) + '%';
    };

    // Overlay elements.

    Editor.prototype.addElement = function(o) {
        var el = document.createElement('div');
        el.className = 'bb-edit-overlay bb-type-' + o.type;
        el.tabIndex = 0;
        el.dataset.id = o.id;
        var handle = document.createElement('span');
        handle.className = 'bb-resize';
        el.appendChild(handle);
        var label = document.createElement('span');
        label.className = 'bb-edit-label';
        el.appendChild(label);
        if (o.type === 'textframe' || o.type === 'imageframe') {
            var content = document.createElement('div');
            content.className = 'bb-frame-content' + (o.type === 'textframe' ? ' bb-textframe' : '');
            el.insertBefore(content, handle);
        }
        o.pageid = String(o.pageid || this.currentPage);
        (this.stages[o.pageid] || this.stages[this.currentPage]).appendChild(el);
        o.el = el;
        this.overlays[o.id] = o;
        this.refresh(o);
        return el;
    };

    /**
     * Renderers of the overlay types in the editor.
     */
    var RENDER = {
        textbox: function(o, s, label) {
            label.textContent = s.text || '';
            o.el.style.background = s.bgcolor || '#ffffff';
            o.el.style.color = s.color || '#000000';
            o.el.style.setProperty('--bb-fs', s.fontsize || 16);
            o.el.style.fontWeight = s.bold ? 'bold' : 'normal';
            o.el.style.textAlign = s.align || 'left';
        },
        mask: function(o, s, label) {
            label.textContent = s.label || '';
            o.el.classList.toggle('bb-mask-black', s.style === 'black');
        },
        audio: function(o, s, label) {
            var detail = s.mode === 'tts' ? s.ttstext : s.filename;
            label.textContent = '♪ ' + (s.label || detail || '');
        },
        glossary: function(o, s, label) {
            label.textContent = s.term || '';
        },
        reflow: function(o, s, label) {
            label.textContent = '¶ ' + (s.text || '').substring(0, 60);
        },
        column: function(o, s, label) {
            label.textContent = this.str.overlay_column;
        },
        textframe: function(o, s) {
            var content = o.el.querySelector('.bb-frame-content');
            if (this.editing !== o) {
                // The html was cleaned on the server (purify_html) when it was saved.
                content.innerHTML = s.html || '';
            }
            o.el.style.setProperty('--bb-scale', s.fontscale || 1);
            o.el.style.background = s.bgcolor || '';
            o.el.style.columnCount = s.columns > 1 ? s.columns : '';
            o.el.classList.toggle('bb-frame-border', !!s.border);
            this.checkOverset(o);
        },
        imageframe: function(o, s, label) {
            var box = o.el.querySelector('.bb-frame-content');
            box.replaceChildren();
            if (!o.imageurl) {
                label.textContent = this.str.uploadimage;
                return;
            }
            var img = document.createElement('img');
            img.src = o.imageurl;
            img.alt = s.alt || '';
            img.draggable = false;
            img.className = s.fit === 'cover' ? 'bb-cover' : '';
            box.appendChild(img);
        }
    };

    Editor.prototype.refresh = function(o) {
        this.place(o.el, o);
        o.el.style.background = '';
        o.el.style.color = '';
        o.el.style.removeProperty('--bb-fs');
        if (RENDER[o.type]) {
            RENDER[o.type].call(this, o, o.settings || {}, o.el.querySelector('.bb-edit-label'));
        }
    };

    Editor.prototype.checkOverset = function(o) {
        var content = o.el.querySelector('.bb-frame-content');
        if (content) {
            o.el.classList.toggle('bb-overset', content.scrollHeight > o.el.clientHeight + 2);
            o.el.title = o.el.classList.contains('bb-overset') ? this.str.overset : '';
        }
    };

    Editor.prototype.select = function(o) {
        if (this.selected && this.selected.el) {
            this.selected.el.classList.remove('selected');
        }
        this.selected = o;
        if (o) {
            o.el.classList.add('selected');
        }
        this.renderPanel();
    };

    // Persistence.

    Editor.prototype.save = function(o) {
        var self = this;
        return Ajax.call([{
            methodname: 'mod_buchbinder_save_overlay',
            args: {
                cmid: this.config.cmid,
                pageid: parseInt(o.pageid, 10),
                overlayid: o.id > 0 ? o.id : 0,
                overlaytype: o.type,
                x: o.x, y: o.y, w: o.w, h: o.h,
                settings: JSON.stringify(o.settings || {})
            }
        }])[0].then(function(result) {
            if (!(o.id > 0)) {
                delete self.overlays[o.id];
                o.id = result.id;
                o.el.dataset.id = o.id;
                self.overlays[o.id] = o;
            }
            o.settings = JSON.parse(result.settings);
            self.refresh(o);
            return o;
        });
    };

    Editor.prototype.remove = function(o) {
        var self = this;
        return Ajax.call([{
            methodname: 'mod_buchbinder_delete_overlay',
            args: {cmid: this.config.cmid, overlayid: o.id}
        }])[0].then(function() {
            o.el.remove();
            delete self.overlays[o.id];
            self.select(null);
            return null;
        }).catch(Notification.exception);
    };

    // Colour matched type-over: estimate the paper colour on the border of the box.

    Editor.prototype.sampleBackground = function(o) {
        var image = this.imageOf(o.pageid);
        if (!image || !image.naturalWidth) {
            return '#ffffff';
        }
        var iw = image.naturalWidth;
        var ih = image.naturalHeight;
        var canvas = document.createElement('canvas');
        canvas.width = iw;
        canvas.height = ih;
        var ctx = canvas.getContext('2d', {willReadFrequently: true});
        ctx.drawImage(image, 0, 0);
        var x0 = Math.floor(o.x * iw);
        var y0 = Math.floor(o.y * ih);
        var x1 = Math.min(iw - 1, Math.ceil((o.x + o.w) * iw));
        var y1 = Math.min(ih - 1, Math.ceil((o.y + o.h) * ih));
        var samples = [];
        var push = function(x, y) {
            var d = ctx.getImageData(x, y, 1, 1).data;
            samples.push([d[0], d[1], d[2], 0.299 * d[0] + 0.587 * d[1] + 0.114 * d[2]]);
        };
        var stepx = Math.max(1, Math.floor((x1 - x0) / 60));
        var stepy = Math.max(1, Math.floor((y1 - y0) / 30));
        for (var x = x0; x <= x1; x += stepx) {
            push(x, y0);
            push(x, y1);
        }
        for (var y = y0; y <= y1; y += stepy) {
            push(x0, y);
            push(x1, y);
        }
        // Text strokes are dark: average the brighter half = paper.
        samples.sort(function(a, b) {
            return b[3] - a[3];
        });
        var n = Math.max(1, Math.floor(samples.length / 2));
        var sum = [0, 0, 0];
        for (var i = 0; i < n; i++) {
            sum[0] += samples[i][0];
            sum[1] += samples[i][1];
            sum[2] += samples[i][2];
        }
        return '#' + sum.map(function(v) {
            return ('0' + Math.round(v / n).toString(16)).slice(-2);
        }).join('');
    };

    // Column detection: vertical whitespace between blocks of ink.

    Editor.prototype.detectColumns = function() {
        var self = this;
        var pageid = this.currentPage;
        var image = this.imageOf(pageid);
        if (!image || !image.naturalWidth) {
            Notification.addNotification({message: this.str.nocolumnsfound, type: 'info'});
            return;
        }
        var w = 400;
        var h = Math.round(w * image.naturalHeight / image.naturalWidth);
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d', {willReadFrequently: true});
        ctx.drawImage(image, 0, 0, w, h);
        var data = ctx.getImageData(0, 0, w, h).data;
        var ink = new Array(w).fill(0);
        var rowink = new Array(h).fill(0);
        for (var y = Math.floor(h * 0.05); y < h * 0.95; y++) {
            for (var x = 0; x < w; x++) {
                var p = (y * w + x) * 4;
                if (0.299 * data[p] + 0.587 * data[p + 1] + 0.114 * data[p + 2] < 128) {
                    ink[x]++;
                    rowink[y]++;
                }
            }
        }
        var threshold = h * 0.004;
        var regions = [];
        var start = null;
        for (var cx = 0; cx <= w; cx++) {
            var has = cx < w && ink[cx] > threshold;
            if (has && start === null) {
                start = cx;
            } else if (!has && start !== null) {
                regions.push([start, cx]);
                start = null;
            }
        }
        // Merge regions separated by narrow gaps (word spaces), keep real gutters (> 2%).
        var merged = [];
        regions.forEach(function(r) {
            var last = merged[merged.length - 1];
            if (last && r[0] - last[1] < w * 0.02) {
                last[1] = r[1];
            } else {
                merged.push(r.slice());
            }
        });
        merged = merged.filter(function(r) {
            return r[1] - r[0] > w * 0.1;
        });
        if (merged.length < 2) {
            Notification.addNotification({message: this.str.nocolumnsfound, type: 'info'});
            return;
        }
        var top = rowink.findIndex(function(v) {
            return v > 0;
        });
        var bottom = h - 1 - rowink.slice().reverse().findIndex(function(v) {
            return v > 0;
        });
        var promises = merged.map(function(r) {
            var o = {id: -Date.now() - r[0], type: 'column', settings: {}, pageid: pageid,
                x: Math.max(0, (r[0] - 2) / w), y: Math.max(0, (top - 4) / h),
                w: Math.min(1, (r[1] - r[0] + 4) / w), h: Math.min(1, (bottom - top + 8) / h)};
            self.addElement(o);
            return self.save(o);
        });
        Promise.all(promises).then(function() {
            Notification.addNotification({message: self.str.columnsfound.replace('{$a}', merged.length), type: 'success'});
            return null;
        }).catch(Notification.exception);
    };

    // Pointer interaction: draw, move, resize.

    Editor.prototype.bindEvents = function() {
        var self = this;
        var drag = null;
        var lastdown = {o: null, time: 0};

        this.root.querySelectorAll('[data-tool]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                self.stopEditing();
                self.tool = btn.dataset.tool;
                self.root.querySelectorAll('[data-tool]').forEach(function(b) {
                    b.classList.toggle('active', b === btn);
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
                Object.values(self.stages).forEach(function(stage) {
                    stage.classList.toggle('bb-drawing', self.tool !== 'select');
                });
            });
        });
        var detect = this.root.querySelector('[data-action="detect-columns"]');
        if (detect) {
            detect.addEventListener('click', function() {
                self.detectColumns();
            });
        }
        var zoom = this.root.querySelector('[data-action="zoom"]');
        if (zoom) {
            zoom.addEventListener('input', function() {
                self.root.style.setProperty('--bb-desk-zoom', zoom.value / 100);
            });
        }
        this.root.querySelectorAll('[data-action="confirm-delete"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                // eslint-disable-next-line no-alert
                if (!window.confirm(self.str.confirmdeletepage)) {
                    e.preventDefault();
                }
            });
        });

        Object.values(this.stages).forEach(function(stage) {
            stage.addEventListener('pointerdown', function(e) {
                if (e.button !== 0 || e.target.closest('[contenteditable="true"]')) {
                    return;
                }
                self.currentPage = stage.dataset.pageid;
                var target = e.target.closest('.bb-edit-overlay');
                var p = self.relPoint(e, stage);
                if (target && self.tool === 'select') {
                    var o = self.overlays[target.dataset.id];
                    // Second click on the same text frame: edit the text (pointer capture swallows dblclick).
                    var now = Date.now();
                    if (o.type === 'textframe' && lastdown.o === o && now - lastdown.time < 450) {
                        lastdown = {o: null, time: 0};
                        self.startEditing(o);
                        e.preventDefault();
                        return;
                    }
                    lastdown = {o: o, time: now};
                    if (self.editing === o) {
                        return;
                    }
                    self.stopEditing();
                    self.select(o);
                    drag = {mode: e.target.classList.contains('bb-resize') ? 'resize' : 'move', o: o, start: p,
                        stage: stage, orig: {x: o.x, y: o.y, w: o.w, h: o.h}, moved: false};
                } else if (self.tool !== 'select') {
                    self.stopEditing();
                    var n = {id: -Date.now(), type: self.tool, pageid: stage.dataset.pageid, x: p.x, y: p.y, w: 0, h: 0,
                        settings: self.defaults(self.tool)};
                    self.addElement(n);
                    drag = {mode: 'draw', o: n, start: p, stage: stage, moved: false};
                } else {
                    self.stopEditing();
                    self.select(null);
                    return;
                }
                stage.setPointerCapture(e.pointerId);
                e.preventDefault();
            });

            stage.addEventListener('pointermove', function(e) {
                if (!drag || drag.stage !== stage) {
                    return;
                }
                var o = drag.o;
                var p = self.relPoint(e, stage, drag.mode === 'move');
                var dx = p.x - drag.start.x;
                var dy = p.y - drag.start.y;
                drag.moved = drag.moved || Math.abs(dx) + Math.abs(dy) > 0.003;
                var snapped;
                if (drag.mode === 'draw') {
                    o.x = Math.min(p.x, drag.start.x);
                    o.y = Math.min(p.y, drag.start.y);
                    o.w = Math.abs(dx);
                    o.h = Math.abs(dy);
                } else if (drag.mode === 'move') {
                    // Frames may be dragged across the fold onto the other page of the double page.
                    o.x = drag.orig.x + dx;
                    o.y = Math.max(0, Math.min(1 - o.h, drag.orig.y + dy));
                    snapped = self.snap(o.x, 'x', o.pageid);
                    if (snapped !== null) {
                        o.x = snapped;
                    } else if ((snapped = self.snap(o.x + o.w, 'x', o.pageid)) !== null) {
                        o.x = snapped - o.w;
                    }
                    if ((snapped = self.snap(o.y, 'y', o.pageid)) !== null) {
                        o.y = snapped;
                    } else if ((snapped = self.snap(o.y + o.h, 'y', o.pageid)) !== null) {
                        o.y = snapped - o.h;
                    }
                } else {
                    o.w = Math.max(0.01, Math.min(1 - o.x, drag.orig.w + dx));
                    o.h = Math.max(0.01, Math.min(1 - o.y, drag.orig.h + dy));
                    if ((snapped = self.snap(o.x + o.w, 'x', o.pageid)) !== null) {
                        o.w = snapped - o.x;
                    }
                    if ((snapped = self.snap(o.y + o.h, 'y', o.pageid)) !== null) {
                        o.h = snapped - o.y;
                    }
                }
                self.place(o.el, o);
                if (o.type === 'textframe') {
                    self.checkOverset(o);
                }
            });

            stage.addEventListener('pointerup', function(e) {
                end(e);
            });
            stage.addEventListener('pointercancel', function(e) {
                end(e);
            });

            stage.addEventListener('dblclick', function(e) {
                var target = e.target.closest('.bb-type-textframe');
                if (target && self.tool === 'select') {
                    self.startEditing(self.overlays[target.dataset.id]);
                }
            });
        });

        /**
         * Move a frame to the page under its centre (other half of the double page).
         *
         * @param {Object} o
         */
        var rehome = function(o) {
            var r = o.el.getBoundingClientRect();
            var cx = r.left + r.width / 2;
            var cy = r.top + r.height / 2;
            var target = null;
            Object.values(self.stages).forEach(function(stage) {
                var sr = stage.getBoundingClientRect();
                if (cx >= sr.left && cx <= sr.right && cy >= sr.top && cy <= sr.bottom) {
                    target = stage;
                }
            });
            if (target && target.dataset.pageid !== String(o.pageid)) {
                var tr = target.getBoundingClientRect();
                o.x = (r.left - tr.left) / tr.width;
                o.y = (r.top - tr.top) / tr.height;
                o.pageid = target.dataset.pageid;
                target.appendChild(o.el);
                self.currentPage = o.pageid;
            }
            o.x = Math.max(0, Math.min(1 - o.w, o.x));
            o.y = Math.max(0, Math.min(1 - o.h, o.y));
            self.place(o.el, o);
        };

        var end = function() {
            if (!drag) {
                return;
            }
            var d = drag;
            drag = null;
            if (d.mode === 'draw') {
                if (d.o.w < 0.01 || d.o.h < 0.005 || d.o.type === 'clip') {
                    d.o.el.remove();
                    delete self.overlays[d.o.id];
                    if (d.o.type === 'clip' && d.o.w >= 0.01 && d.o.h >= 0.005) {
                        self.createClip(d.o);
                    }
                    return;
                }
                if (d.o.type === 'textbox') {
                    d.o.settings.bgcolor = self.sampleBackground(d.o);
                }
                self.save(d.o).then(function(o) {
                    self.select(o);
                    if (o.type === 'textframe') {
                        self.startEditing(o);
                    }
                    return null;
                }).catch(Notification.exception);
            } else if (d.moved) {
                if (d.mode === 'move') {
                    rehome(d.o);
                }
                self.save(d.o).catch(Notification.exception);
            }
        };

        this.root.addEventListener('keydown', function(e) {
            if (!self.selected || e.target.closest('input, textarea, select, [contenteditable="true"]')) {
                return;
            }
            var o = self.selected;
            var step = e.shiftKey ? 0.01 : 0.002;
            var moves = {ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step]};
            if (moves[e.key]) {
                o.x = Math.max(0, Math.min(1 - o.w, o.x + moves[e.key][0]));
                o.y = Math.max(0, Math.min(1 - o.h, o.y + moves[e.key][1]));
                self.place(o.el, o);
                clearTimeout(self.keyTimer);
                self.keyTimer = setTimeout(function() {
                    self.save(o).catch(Notification.exception);
                }, 400);
                e.preventDefault();
            } else if (e.key === 'Delete') {
                self.remove(o);
                e.preventDefault();
            }
        });
        this.root.addEventListener('focusin', function(e) {
            var target = e.target.closest('.bb-edit-overlay');
            if (target && !self.editing && self.overlays[target.dataset.id] !== self.selected) {
                self.select(self.overlays[target.dataset.id]);
            }
        });
    };

    // Clips: cut a region out of the page for composed pages.

    Editor.prototype.createClip = function(o) {
        var self = this;
        Ajax.call([{
            methodname: 'mod_buchbinder_create_clip',
            args: {cmid: this.config.cmid, pageid: parseInt(o.pageid, 10), x: o.x, y: o.y, w: o.w, h: o.h}
        }])[0].then(function(result) {
            Notification.addNotification({
                message: self.str.clipcreated.replace('{$a}', result.markdown),
                type: 'success'
            });
            return null;
        }).catch(Notification.exception);
    };

    Editor.prototype.defaults = function(type) {
        switch (type) {
            case 'textframe':
                return {html: '<p>' + this.str.newframetext + '</p>', fontscale: 1, bgcolor: '', border: false, columns: 1};
            case 'imageframe':
                return {fit: 'contain', alt: '', caption: ''};
            case 'textbox':
                return {text: '', fontsize: 16, color: '#000000', bgcolor: '#ffffff', bold: false, align: 'left'};
            case 'mask':
                return {style: 'white', revealable: true, label: ''};
            case 'audio':
                return {mode: 'file', label: '', ttstext: '', ttslang: document.documentElement.lang || 'de-DE'};
            case 'glossary':
                return {glossaryid: this.config.glossaries.length ? this.config.glossaries[0].id : 0, term: ''};
            case 'reflow':
                return {text: '', role: 'p'};
            default:
                return {};
        }
    };

    // Text frames: editing in place.

    Editor.prototype.startEditing = function(o) {
        if (!o || o.type !== 'textframe' || this.editing === o) {
            return;
        }
        this.stopEditing();
        var self = this;
        var content = o.el.querySelector('.bb-frame-content');
        this.select(o);
        this.editing = o;
        o.el.classList.add('bb-editing');
        content.contentEditable = 'true';
        content.focus();
        content.addEventListener('input', this.onInput = function() {
            self.checkOverset(o);
        });
    };

    Editor.prototype.stopEditing = function() {
        var o = this.editing;
        if (!o) {
            return;
        }
        var content = o.el.querySelector('.bb-frame-content');
        content.contentEditable = 'false';
        content.removeEventListener('input', this.onInput);
        o.el.classList.remove('bb-editing');
        this.editing = null;
        if (content.innerHTML !== o.settings.html) {
            o.settings.html = content.innerHTML;
            this.save(o).catch(Notification.exception);
        }
    };

    Editor.prototype.format = function(command, value) {
        if (this.editing) {
            this.editing.el.querySelector('.bb-frame-content').focus();
            document.execCommand(command, false, value);
            this.checkOverset(this.editing);
        }
    };

    Editor.prototype.uploadFrameImage = function(o, file) {
        var self = this;
        if (this.config.maxbytes > 0 && file.size > this.config.maxbytes) {
            Notification.alert('', this.str.errorfiletoolarge);
            return;
        }
        var reader = new FileReader();
        reader.onload = function() {
            Ajax.call([{
                methodname: 'mod_buchbinder_save_frame_image',
                args: {cmid: self.config.cmid, overlayid: o.id, filename: file.name,
                    content: String(reader.result).split(',')[1] || ''}
            }])[0].then(function(result) {
                o.imageurl = result.url;
                o.settings = JSON.parse(result.settings);
                o.el.querySelector('.bb-edit-label').textContent = '';
                self.refresh(o);
                Notification.addNotification({message: self.str.imagesaved, type: 'success'});
                return null;
            }).catch(Notification.exception);
        };
        reader.readAsDataURL(file);
    };

    Editor.prototype.renderFramePanel = function(body, o, read) {
        var self = this;
        var str = this.str;
        var s = o.settings;
        if (o.type === 'textframe') {
            var tools = document.createElement('div');
            tools.className = 'bb-format-tools mb-2';
            [['bold', 'B', null], ['italic', 'I', null], ['formatBlock', str.role_h2, 'h2'], ['formatBlock', str.role_h3, 'h3'],
                ['formatBlock', str.paragraph, 'p'], ['insertUnorderedList', '•', null],
                ['insertOrderedList', '1.', null]].forEach(function(t) {
                var b = self.button(tools, t[1], 'btn-outline-secondary', function() {
                    self.startEditing(o);
                    self.format(t[0], t[2]);
                });
                var titles = {bold: str.bold, italic: str.italic};
                b.title = titles[t[0]] || t[1];
                b.addEventListener('mousedown', function(e) {
                    // Keep the text selection in the frame.
                    e.preventDefault();
                });
            });
            body.appendChild(tools);
            this.button(body, str.edittext, 'btn-outline-primary', function() {
                self.startEditing(o);
            });
            var scale = this.field(body, str.fontscale, this.input('number', s.fontscale));
            scale.min = 0.5;
            scale.max = 3;
            scale.step = 0.05;
            var cols = this.field(body, str.textcolumns, this.selectInput([[1, '1'], [2, '2'], [3, '3']], s.columns));
            var nobg = this.field(body, str.nobackground, this.input('checkbox', !s.bgcolor));
            var bg = this.field(body, str.bgcolor, this.input('color', s.bgcolor || '#ffffff'));
            var border = this.field(body, str.frameborder, this.input('checkbox', s.border));
            read.push(function() {
                self.stopEditing();
                s.fontscale = parseFloat(scale.value) || 1;
                s.columns = parseInt(cols.value, 10) || 1;
                s.bgcolor = nobg.checked ? '' : bg.value;
                s.border = border.checked;
            });
        } else {
            var upload = this.input('file');
            upload.accept = 'image/*';
            this.field(body, str.uploadimage, upload);
            upload.addEventListener('change', function() {
                if (upload.files.length) {
                    self.uploadFrameImage(o, upload.files[0]);
                }
            });
            var fit = this.field(body, str.imagefit, this.selectInput([['contain', str.fit_contain],
                ['cover', str.fit_cover]], s.fit));
            var alt = this.field(body, str.alttext, this.input('text', s.alt));
            var caption = this.field(body, str.caption, this.input('text', s.caption));
            read.push(function() {
                s.fit = fit.value;
                s.alt = alt.value;
                s.caption = caption.value;
            });
        }
    };

    // Properties panel.

    Editor.prototype.field = function(body, label, input) {
        var id = 'bb-f-' + Math.random().toString(36).slice(2);
        var group = document.createElement('div');
        group.className = 'form-group mb-2';
        var l = document.createElement('label');
        l.htmlFor = id;
        l.textContent = label;
        input.id = id;
        if (input.type === 'checkbox') {
            group.className += ' form-check';
            input.className = 'form-check-input';
            l.className = 'form-check-label';
            group.appendChild(input);
            group.appendChild(l);
        } else {
            if (input.type !== 'color') {
                input.className = 'form-control form-control-sm';
            }
            group.appendChild(l);
            group.appendChild(input);
        }
        body.appendChild(group);
        return input;
    };

    Editor.prototype.input = function(type, value) {
        var el = document.createElement(type === 'textarea' ? 'textarea' : 'input');
        if (type !== 'textarea') {
            el.type = type;
        } else {
            el.rows = 4;
        }
        if (type === 'checkbox') {
            el.checked = !!value;
        } else {
            el.value = value === undefined || value === null ? '' : value;
        }
        return el;
    };

    Editor.prototype.selectInput = function(options, value) {
        var el = document.createElement('select');
        options.forEach(function(opt) {
            var o = document.createElement('option');
            o.value = opt[0];
            o.textContent = opt[1];
            o.selected = String(opt[0]) === String(value);
            el.appendChild(o);
        });
        return el;
    };

    Editor.prototype.button = function(body, label, cls, handler) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm ' + cls + ' mr-1 me-1 mb-1';
        b.textContent = label;
        b.addEventListener('click', handler);
        body.appendChild(b);
        return b;
    };

    Editor.prototype.renderPanel = function() {
        var self = this;
        var str = this.str;
        var body = document.createElement('div');
        body.className = 'card-body';
        var o = this.selected;
        if (!o) {
            var p = document.createElement('p');
            p.className = 'text-muted small mb-0';
            p.textContent = str.selectoverlay;
            body.appendChild(p);
            this.panel.replaceChildren(body);
            return;
        }
        var s = o.settings;
        var title = document.createElement('h5');
        title.textContent = str['overlay_' + o.type];
        body.appendChild(title);
        var read = [];

        if (o.type === 'textframe' || o.type === 'imageframe') {
            this.renderFramePanel(body, o, read);
        } else if (o.type === 'textbox') {
            var text = this.field(body, str.text, this.input('textarea', s.text));
            var size = this.field(body, str.fontsize, this.input('number', s.fontsize));
            size.min = 6;
            size.max = 96;
            var color = this.field(body, str.textcolor, this.input('color', s.color));
            var bg = this.field(body, str.bgcolor, this.input('color', s.bgcolor));
            this.button(body, str.matchbackground, 'btn-outline-secondary', function() {
                bg.value = self.sampleBackground(o);
            });
            var bold = this.field(body, str.bold, this.input('checkbox', s.bold));
            var align = this.field(body, str.align, this.selectInput([['left', str.align_left],
                ['center', str.align_center], ['right', str.align_right]], s.align));
            read.push(function() {
                s.text = text.value;
                s.fontsize = parseInt(size.value, 10) || 16;
                s.color = color.value;
                s.bgcolor = bg.value;
                s.bold = bold.checked;
                s.align = align.value;
            });
        } else if (o.type === 'mask') {
            var style = this.field(body, str.maskstyle, this.selectInput([['white', str.mask_white],
                ['black', str.mask_black]], s.style));
            var reveal = this.field(body, str.revealable, this.input('checkbox', s.revealable));
            var mlabel = this.field(body, str.label, this.input('text', s.label));
            read.push(function() {
                s.style = style.value;
                s.revealable = reveal.checked;
                s.label = mlabel.value;
            });
        } else if (o.type === 'audio') {
            this.renderAudio(body, o, read);
        } else if (o.type === 'glossary') {
            var gl = this.field(body, str.glossary, this.selectInput(this.config.glossaries.map(function(g) {
                return [g.id, g.name];
            }), s.glossaryid));
            var term = this.field(body, str.term, this.input('text', s.term));
            read.push(function() {
                s.glossaryid = parseInt(gl.value, 10);
                s.term = term.value;
            });
        } else if (o.type === 'reflow') {
            var role = this.field(body, str.reflowrole, this.selectInput([['p', str.role_p], ['h2', str.role_h2],
                ['h3', str.role_h3], ['li', str.role_li]], s.role));
            var rtext = this.field(body, str.text, this.input('textarea', s.text));
            rtext.rows = 8;
            read.push(function() {
                s.role = role.value;
                s.text = rtext.value;
            });
        } else if (o.type === 'column') {
            var info = document.createElement('p');
            info.className = 'small text-muted';
            info.textContent = str.columnhelp;
            body.appendChild(info);
        }

        var actions = document.createElement('div');
        actions.className = 'mt-3';
        body.appendChild(actions);
        this.button(actions, str.save, 'btn-primary', function() {
            read.forEach(function(fn) {
                fn();
            });
            self.save(o).then(function() {
                Notification.addNotification({message: str.saved, type: 'success'});
                return null;
            }).catch(Notification.exception);
        });
        this.button(actions, str.deleteoverlay, 'btn-outline-danger', function() {
            self.remove(o);
        });
        this.panel.replaceChildren(body);
        var first = body.querySelector('input, textarea, select');
        if (first && !this.editing) {
            first.focus();
        }
    };

    Editor.prototype.renderAudio = function(body, o, read) {
        var self = this;
        var str = this.str;
        var s = o.settings;
        var label = this.field(body, str.label, this.input('text', s.label));
        var mode = this.field(body, str.audiosource, this.selectInput([['file', str.audio_file], ['tts', str.audio_tts]],
            s.mode));

        var filebox = document.createElement('div');
        var player = document.createElement('audio');
        player.controls = true;
        player.className = 'w-100 mb-2';
        if (o.audiourl) {
            player.src = o.audiourl;
        }
        filebox.appendChild(player);
        var rec = this.button(filebox, str.record, 'btn-outline-danger', function() {
            if (self.recorder && self.recorder.state === 'recording') {
                self.recorder.stop();
                return;
            }
            navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
                var chunks = [];
                self.recorder = new MediaRecorder(stream);
                self.recorder.ondataavailable = function(e) {
                    chunks.push(e.data);
                };
                self.recorder.onstop = function() {
                    stream.getTracks().forEach(function(t) {
                        t.stop();
                    });
                    rec.textContent = str.record;
                    var type = self.recorder.mimeType || 'audio/webm';
                    var ext = 'webm';
                    if (type.indexOf('ogg') >= 0) {
                        ext = 'ogg';
                    } else if (type.indexOf('mp4') >= 0) {
                        ext = 'm4a';
                    }
                    self.uploadAudio(o, new Blob(chunks, {type: type}), 'recording.' + ext, player);
                };
                self.recorder.start();
                rec.textContent = str.stoprecording;
                return null;
            }).catch(Notification.exception);
        });
        var upload = this.input('file');
        upload.accept = 'audio/*';
        this.field(filebox, str.uploadaudio, upload);
        upload.addEventListener('change', function() {
            if (upload.files.length) {
                self.uploadAudio(o, upload.files[0], upload.files[0].name, player);
            }
        });
        body.appendChild(filebox);

        var ttsbox = document.createElement('div');
        var ttstext = this.field(ttsbox, str.ttstext, this.input('textarea', s.ttstext));
        var ttslang = this.field(ttsbox, str.ttslang, this.input('text', s.ttslang));
        this.button(ttsbox, str.preview, 'btn-outline-secondary', function() {
            if (window.speechSynthesis) {
                var u = new SpeechSynthesisUtterance(ttstext.value);
                u.lang = ttslang.value;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(u);
            }
        });
        body.appendChild(ttsbox);

        var toggle = function() {
            filebox.hidden = mode.value !== 'file';
            ttsbox.hidden = mode.value !== 'tts';
        };
        mode.addEventListener('change', toggle);
        toggle();

        read.push(function() {
            s.label = label.value;
            s.mode = mode.value;
            s.ttstext = ttstext.value;
            s.ttslang = ttslang.value;
        });
    };

    Editor.prototype.uploadAudio = function(o, blob, filename, player) {
        var self = this;
        if (this.config.maxbytes > 0 && blob.size > this.config.maxbytes) {
            Notification.alert('', this.str.errorfiletoolarge);
            return;
        }
        var reader = new FileReader();
        reader.onload = function() {
            var base64 = String(reader.result).split(',')[1] || '';
            Ajax.call([{
                methodname: 'mod_buchbinder_save_audio',
                args: {cmid: self.config.cmid, overlayid: o.id, filename: filename, content: base64}
            }])[0].then(function(result) {
                o.audiourl = result.url;
                o.settings = JSON.parse(result.settings);
                player.src = result.url;
                self.refresh(o);
                Notification.addNotification({message: self.str.audiosaved, type: 'success'});
                return null;
            }).catch(Notification.exception);
        };
        reader.readAsDataURL(blob);
    };

    return {
        /**
         * Initialise the editor.
         *
         * @param {string} selector
         */
        init: function(selector) {
            var root = document.querySelector(selector);
            if (root) {
                new Editor(root).start();
            }
        }
    };
});
