@php
    $mdText = fn ($en, $km) => session('user.language', config('app.locale')) === 'km' ? $km : $en;
@endphp
<style>
    .md-doc-slot {
        border: 1.5px dashed #cbd5e1;
        border-radius: 10px;
        padding: 14px;
        background: #fafbfc;
        position: relative;
    }
    .md-doc-slot.md-doc-has-files { border-style: solid; background: #fff; }
    .md-doc-slot > [data-md-input] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .md-doc-slot.md-doc-has-files > [data-md-input] { position: static; width: auto; height: auto; opacity: 0; display: none; }
    .md-doc-count {
        display: none; position: absolute; top: 8px; right: 8px; min-width: 20px; height: 20px; padding: 0 6px;
        border-radius: 10px; background: #2563eb; color: #fff; font-size: 11px; font-weight: 800;
        align-items: center; justify-content: center;
    }
    .md-doc-slot.md-doc-has-files .md-doc-count { display: inline-flex; }
    .md-doc-hint { font-size: 11px; color: #94a3b8; }
    .md-doc-queue { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
    .md-doc-queue:empty { display: none; }
    .md-doc-item { display: flex; gap: 9px; align-items: center; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
    .md-doc-thumb { width: 42px; height: 42px; flex-shrink: 0; border-radius: 6px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 9px; font-weight: 800; }
    .md-doc-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .md-doc-item-body { min-width: 0; flex: 1; }
    .md-doc-item-name { font-size: 11px; font-weight: 800; color: #334155; overflow-wrap: anywhere; }
    .md-doc-item-meta { font-size: 10px; color: #94a3b8; margin-top: 2px; }
    .md-doc-badge { display: inline-block; margin-top: 3px; padding: 1px 6px; border-radius: 4px; font-size: 9px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; }
    .md-doc-badge.md-doc-original { background: #ecfdf5; color: #047857; }
    .md-doc-badge.md-doc-cropped { background: #eff6ff; color: #1d4ed8; }
    .md-doc-item-actions { display: flex; flex-direction: column; gap: 4px; flex-shrink: 0; }
    .md-doc-btn { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 5px; font-size: 10px; font-weight: 800; padding: 4px 7px; cursor: pointer; white-space: nowrap; }
    .md-doc-btn:hover:not([disabled]) { border-color: #2563eb; color: #2563eb; }
    .md-doc-btn.md-doc-remove:hover { border-color: #dc2626; color: #dc2626; }
    .md-doc-btn[disabled] { opacity: .45; cursor: not-allowed; }
    .md-doc-add { display: none; width: 100%; margin-top: 10px; padding: 7px 10px; border: 1px dashed #cbd5e1; border-radius: 6px; background: #fff; color: #475569; font-size: 11px; font-weight: 800; cursor: pointer; }
    .md-doc-slot.md-doc-has-files .md-doc-add { display: flex; align-items: center; justify-content: center; gap: 6px; }
    .md-doc-add:hover { border-color: #2563eb; color: #2563eb; }

    .md-crop-dialog { width: min(720px, calc(100% - 32px)); max-height: 94vh; padding: 18px; border: 0; border-radius: 12px; background: #fff; color: #0f172a; overflow: auto; }
    .md-crop-dialog::backdrop { background: rgba(2, 6, 23, .78); }
    .md-crop-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
    .md-crop-title { font-size: 15px; font-weight: 800; overflow-wrap: anywhere; }
    .md-crop-close { border: 0; background: none; font-size: 22px; color: #64748b; cursor: pointer; line-height: 1; }
    .md-crop-canvas { display: block; width: 100%; max-height: 56vh; border-radius: 10px; background: #0f172a; touch-action: none; border: 1px solid #334155; }
    .md-crop-status { font-size: 11px; color: #64748b; margin: 8px 0 0; }
    .md-crop-status.md-crop-error { color: #dc2626; }
    .md-crop-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 14px; }
    .md-crop-actions button { min-height: 38px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #334155; font-size: 12px; font-weight: 800; cursor: pointer; }
    .md-crop-actions button:hover { background: #f1f5f9; }
    .md-crop-actions button.md-crop-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
    .md-crop-actions button.md-crop-primary:hover { filter: brightness(.94); }
</style>
<script>
(function (window, document) {
    if (window.MDUpload) { return; }

    var MAX_FILES = 8;
    var MAX_BYTES = 10 * 1024 * 1024;
    var seq = 0;

    function isImage(file) {
        return !!(file && file.type && file.type.indexOf('image/') === 0);
    }
    function formatBytes(bytes) {
        if (!bytes) return '0 KB';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
    function outputType(file) {
        var type = (file && file.type ? file.type : '').toLowerCase();
        return (type === 'image/png' || type === 'image/webp') ? type : 'image/jpeg';
    }
    function extensionFor(type) {
        if (type === 'image/png') return 'png';
        if (type === 'image/webp') return 'webp';
        return 'jpg';
    }
    function croppedName(original, type) {
        var name = String(original || 'photo');
        var dot = name.lastIndexOf('.');
        if (dot > 0) name = name.slice(0, dot);
        return name + '-cropped.' + extensionFor(type);
    }
    function readDataUrl(file, callback) {
        var reader = new FileReader();
        reader.onload = function (event) { callback(event.target.result); };
        reader.onerror = function () { callback(null); };
        reader.readAsDataURL(file);
    }
    function blobToDataUrl(blob, callback) {
        var reader = new FileReader();
        reader.onload = function (event) { callback(event.target.result); };
        reader.onerror = function () { callback(null); };
        reader.readAsDataURL(blob);
    }
    function makeFile(blob, name, type) {
        try {
            return new File([blob], name, { type: type, lastModified: Date.now() });
        } catch (error) {
            return blob;
        }
    }

    function createCropper(canvas, image, type) {
        var context = canvas.getContext('2d');
        var scale = Math.min(1, 700 / image.width);
        var width = Math.round(image.width * scale);
        var height = Math.round(image.height * scale);
        var handle = 16;
        var minSize = 40;
        var drag = null;
        var last = null;
        var crop = {};

        canvas.width = width;
        canvas.height = height;

        function constrain() {
            crop.width = Math.max(minSize, Math.min(crop.width, width));
            crop.height = Math.max(minSize, Math.min(crop.height, height));
            crop.x = Math.max(0, Math.min(crop.x, width - crop.width));
            crop.y = Math.max(0, Math.min(crop.y, height - crop.height));
        }
        function draw() {
            context.clearRect(0, 0, width, height);
            context.drawImage(image, 0, 0, width, height);
            context.fillStyle = 'rgba(15, 23, 42, 0.5)';
            context.fillRect(0, 0, width, height);
            context.drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, crop.x, crop.y, crop.width, crop.height);
            context.strokeStyle = '#2563eb';
            context.lineWidth = 3;
            context.strokeRect(crop.x, crop.y, crop.width, crop.height);
            context.fillStyle = '#2563eb';
            [[crop.x, crop.y], [crop.x + crop.width, crop.y], [crop.x, crop.y + crop.height], [crop.x + crop.width, crop.y + crop.height]].forEach(function (point) {
                context.fillRect(point[0] - handle / 2, point[1] - handle / 2, handle, handle);
            });
        }
        function reset() {
            crop = {
                x: Math.round(width * 0.06),
                y: Math.round(height * 0.06),
                width: Math.round(width * 0.88),
                height: Math.round(height * 0.88)
            };
            constrain();
            draw();
        }
        function point(event) {
            var source = (event.touches && event.touches.length) ? event.touches[0] : event;
            var rect = canvas.getBoundingClientRect();
            return {
                x: (source.clientX - rect.left) * (canvas.width / rect.width),
                y: (source.clientY - rect.top) * (canvas.height / rect.height)
            };
        }
        function mode(p) {
            var handles = { nw: [crop.x, crop.y], ne: [crop.x + crop.width, crop.y], sw: [crop.x, crop.y + crop.height], se: [crop.x + crop.width, crop.y + crop.height] };
            for (var key in handles) {
                if (Math.abs(p.x - handles[key][0]) <= handle && Math.abs(p.y - handles[key][1]) <= handle) return key;
            }
            if (p.x >= crop.x && p.x <= crop.x + crop.width && p.y >= crop.y && p.y <= crop.y + crop.height) return 'move';
            return null;
        }
        function start(event) {
            var p = point(event);
            drag = mode(p);
            last = p;
            if (drag) event.preventDefault();
        }
        function move(event) {
            if (!drag) return;
            var p = point(event);
            var dx = p.x - last.x;
            var dy = p.y - last.y;
            if (drag === 'move') {
                crop.x += dx;
                crop.y += dy;
            } else {
                if (drag.indexOf('n') !== -1) { crop.y += dy; crop.height -= dy; }
                if (drag.indexOf('s') !== -1) { crop.height += dy; }
                if (drag.indexOf('w') !== -1) { crop.x += dx; crop.width -= dx; }
                if (drag.indexOf('e') !== -1) { crop.width += dx; }
            }
            constrain();
            last = p;
            draw();
            event.preventDefault();
        }
        function end() { drag = null; last = null; }

        canvas.onmousedown = start;
        canvas.onmousemove = move;
        canvas.onmouseup = end;
        canvas.onmouseleave = end;
        canvas.ontouchstart = start;
        canvas.ontouchmove = move;
        canvas.ontouchend = end;
        reset();

        return {
            reset: reset,
            getBlob: function (callback) {
                var sourceWidth = Math.max(1, Math.round(crop.width / scale));
                var sourceHeight = Math.max(1, Math.round(crop.height / scale));
                var outScale = Math.min(1, 1600 / Math.max(sourceWidth, sourceHeight));
                var out = document.createElement('canvas');
                out.width = Math.max(1, Math.round(sourceWidth * outScale));
                out.height = Math.max(1, Math.round(sourceHeight * outScale));
                out.getContext('2d').drawImage(image, crop.x / scale, crop.y / scale, crop.width / scale, crop.height / scale, 0, 0, out.width, out.height);
                if (typeof out.toBlob === 'function') {
                    out.toBlob(callback, type, 0.9);
                    return;
                }
                var dataUrl = out.toDataURL(type, 0.9);
                var binary = atob(dataUrl.substring(dataUrl.indexOf(',') + 1));
                var bytes = new Uint8Array(binary.length);
                for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                callback(new Blob([bytes], { type: type }));
            }
        };
    }

    var dialog = null;
    var active = null;

    function labels() {
        return {
            title: 'Crop Photo',
            status: 'Drag the blue box to adjust the area. "Keep original" uploads the file untouched.',
            reset: 'Reset crop',
            keep: 'Keep original',
            apply: 'Apply crop',
            failed: 'Cropping failed. Please use "Keep original".',
            unsupported: 'This file type cannot be cropped.'
        };
    }

    function buildDialog() {
        var text = labels();
        dialog = document.createElement('dialog');
        dialog.id = 'mdCropDialog';
        dialog.className = 'md-crop-dialog';
        dialog.innerHTML =
            '<div class="md-crop-head">' +
            '<strong class="md-crop-title"></strong>' +
            '<button type="button" class="md-crop-close" aria-label="Close">&times;</button>' +
            '</div>' +
            '<canvas class="md-crop-canvas"></canvas>' +
            '<p class="md-crop-status"></p>' +
            '<div class="md-crop-actions">' +
            '<button type="button" data-md-crop-reset>' + text.reset + '</button>' +
            '<button type="button" data-md-crop-keep>' + text.keep + '</button>' +
            '<button type="button" class="md-crop-primary" data-md-crop-apply>' + text.apply + '</button>' +
            '</div>';

        document.body.appendChild(dialog);

        dialog.querySelector('.md-crop-close').addEventListener('click', closeDialog);
        dialog.querySelector('[data-md-crop-reset]').addEventListener('click', function () {
            if (active && active.cropper) active.cropper.reset();
        });
        dialog.querySelector('[data-md-crop-keep]').addEventListener('click', function () {
            if (active && active.onKeepOriginal) active.onKeepOriginal();
            closeDialog();
        });
        dialog.querySelector('[data-md-crop-apply]').addEventListener('click', function () {
            if (!active || !active.cropper) return;
            var pending = active;
            pending.cropper.getBlob(function (blob) {
                if (!blob) {
                    dialog.querySelector('.md-crop-status').textContent = labels().failed;
                    dialog.querySelector('.md-crop-status').classList.add('md-crop-error');
                    return;
                }
                pending.onCropped(blob);
                closeDialog();
            });
        });
        dialog.addEventListener('cancel', function () { active = null; });
        return dialog;
    }

    function ensureDialog() {
        if (!dialog) buildDialog();
        return dialog;
    }

    function closeDialog() {
        active = null;
        if (dialog && dialog.open) dialog.close();
    }

    function openCropper(file, handlers) {
        var dlg = ensureDialog();
        var titleEl = dlg.querySelector('.md-crop-title');
        var statusEl = dlg.querySelector('.md-crop-status');
        var canvas = dlg.querySelector('.md-crop-canvas');

        if (!isImage(file)) {
            if (statusEl) {
                statusEl.textContent = labels().unsupported;
                statusEl.classList.add('md-crop-error');
            }
            if (!dlg.open) dlg.showModal();
            return;
        }

        readDataUrl(file, function (dataUrl) {
            var image = new Image();
            image.onload = function () {
                titleEl.textContent = (handlers && handlers.title) || labels().title;
                statusEl.textContent = labels().status;
                statusEl.classList.remove('md-crop-error');
                active = {
                    cropper: createCropper(canvas, image, outputType(file)),
                    onCropped: function (blob) {
                        if (handlers && handlers.onCropped) handlers.onCropped(blob, makeFile(blob, croppedName(file.name, outputType(file)), outputType(file)));
                    },
                    onKeepOriginal: function () {
                        if (handlers && handlers.onKeepOriginal) handlers.onKeepOriginal();
                    }
                };
                if (!dlg.open) dlg.showModal();
            };
            image.onerror = function () {
                statusEl.textContent = labels().unsupported;
                statusEl.classList.add('md-crop-error');
                if (!dlg.open) dlg.showModal();
            };
            image.src = dataUrl;
        });
    }

    function Slot(element) {
        var self = this;
        this.el = element;
        this.input = element.querySelector('[data-md-input]');
        this.queueEl = element.querySelector('[data-md-queue]');
        this.addBtn = element.querySelector('[data-md-add]');
        this.countEl = element.querySelector('[data-md-count]');
        this.items = [];
        this.maxFiles = parseInt(element.getAttribute('data-md-max'), 10) || MAX_FILES;
        this.maxBytes = parseInt(element.getAttribute('data-md-max-bytes'), 10) || MAX_BYTES;

        if (this.addBtn) {
            this.addBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (self.input) self.input.click();
            });
        }
        if (this.input) {
            this.input.addEventListener('change', function () {
                var picked = Array.prototype.slice.call(self.input.files || []);
                self.addFiles(picked);
            });
        }
        this.render();
    }

    Slot.prototype.addFiles = function (files) {
        var self = this;
        var skipped = [];
        files.forEach(function (file) {
            if (self.items.length >= self.maxFiles) {
                skipped.push(file.name + ' (max ' + self.maxFiles + ' files)');
                return;
            }
            if (!isImage(file) && file.type !== 'application/pdf') {
                skipped.push(file.name + ' (unsupported type)');
                return;
            }
            if (file.size > self.maxBytes) {
                skipped.push(file.name + ' (over ' + formatBytes(self.maxBytes) + ')');
                return;
            }
            self.items.push({
                id: ++seq,
                name: file.name,
                originalFile: file,
                originalSize: file.size,
                file: file,
                cropped: false
            });
        });
        this.render();
        if (skipped.length && typeof window.alert === 'function') {
            window.alert('Some files were skipped:\n\n' + skipped.join('\n'));
        }
    };

    Slot.prototype.find = function (id) {
        for (var i = 0; i < this.items.length; i++) {
            if (this.items[i].id === id) return this.items[i];
        }
        return null;
    };

    Slot.prototype.remove = function (id) {
        this.items = this.items.filter(function (item) { return item.id !== id; });
        this.render();
    };

    Slot.prototype.keepOriginal = function (id) {
        var item = this.find(id);
        if (!item) return;
        item.file = item.originalFile;
        item.cropped = false;
        this.render();
    };

    Slot.prototype.crop = function (id) {
        var self = this;
        var item = this.find(id);
        if (!item) return;
        openCropper(item.originalFile, {
            title: 'Crop: ' + item.name,
            onCropped: function (blob, croppedFile) {
                item.file = croppedFile || makeFile(blob, croppedName(item.name, outputType(item.file)), outputType(item.file));
                item.cropped = true;
                self.render();
            },
            onKeepOriginal: function () {
                item.file = item.originalFile;
                item.cropped = false;
                self.render();
            }
        });
    };

    Slot.prototype.files = function () {
        return this.items.map(function (item) { return item.file; });
    };

    Slot.prototype.count = function () {
        return this.items.length;
    };

    Slot.prototype.clear = function () {
        this.items = [];
        this.render();
    };

    Slot.prototype.syncNative = function () {
        if (!this.input || typeof DataTransfer === 'undefined') return;
        var transfer = new DataTransfer();
        this.items.forEach(function (item) { transfer.items.add(item.file); });
        this.input.files = transfer.files;
    };

    Slot.prototype.dataUris = function () {
        var self = this;
        return Promise.all(this.items.map(function (item) {
            return new Promise(function (resolve) {
                readDataUrl(item.file, function (dataUri) {
                    resolve({ dataUri: dataUri, name: item.name, cropped: item.cropped });
                });
            });
        }));
    };

    Slot.prototype.render = function () {
        var self = this;
        if (!this.queueEl) { this.syncNative(); return; }

        this.queueEl.innerHTML = '';
        if (this.countEl) this.countEl.textContent = String(this.items.length);
        this.el.classList.toggle('md-doc-has-files', this.items.length > 0);

        this.items.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'md-doc-item';

            var thumb = document.createElement('div');
            thumb.className = 'md-doc-thumb';
            if (isImage(item.file)) {
                readDataUrl(item.file, function (dataUri) {
                    var img = document.createElement('img');
                    img.alt = item.name;
                    if (dataUri) img.src = dataUri;
                    thumb.appendChild(img);
                });
            } else {
                thumb.textContent = 'PDF';
            }

            var body = document.createElement('div');
            body.className = 'md-doc-item-body';
            var nameEl = document.createElement('div');
            nameEl.className = 'md-doc-item-name';
            nameEl.textContent = item.name;
            var metaEl = document.createElement('div');
            metaEl.className = 'md-doc-item-meta';
            metaEl.textContent = formatBytes(item.file.size) + (item.cropped ? ' (from ' + formatBytes(item.originalSize) + ')' : '');
            var badge = document.createElement('span');
            badge.className = 'md-doc-badge ' + (item.cropped ? 'md-doc-cropped' : 'md-doc-original');
            badge.textContent = item.cropped ? 'Cropped' : 'Original file';
            body.appendChild(nameEl);
            body.appendChild(metaEl);
            body.appendChild(badge);

            var actions = document.createElement('div');
            actions.className = 'md-doc-item-actions';
            actions.appendChild(self.button('Crop', 'md-doc-btn', function () { self.crop(item.id); }, !isImage(item.file)));
            actions.appendChild(self.button('Keep original', 'md-doc-btn', function () { self.keepOriginal(item.id); }, !item.cropped));
            actions.appendChild(self.button('Remove', 'md-doc-btn md-doc-remove', function () { self.remove(item.id); }, false));

            row.appendChild(thumb);
            row.appendChild(body);
            row.appendChild(actions);
            self.queueEl.appendChild(row);
        });

        this.syncNative();
    };

    Slot.prototype.button = function (text, className, handler, disabled) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = text;
        button.disabled = !!disabled;
        button.addEventListener('click', handler);
        return button;
    };

    var slots = [];

    function attach(element) {
        if (!element || element.__mdSlot) return element && element.__mdSlot;
        var slot = new Slot(element);
        element.__mdSlot = slot;
        slots.push(slot);
        return slot;
    }

    window.MDUpload = {
        slots: slots,
        Slot: Slot,
        attach: attach,
        isImage: isImage,
        formatBytes: formatBytes,
        outputType: outputType,
        croppedName: croppedName,
        openCropper: openCropper,
        syncAll: function () {
            slots.forEach(function (slot) { slot.syncNative(); });
        },
        attachAll: function (root) {
            var scope = root || document;
            Array.prototype.forEach.call(scope.querySelectorAll('[data-md-slot]'), attach);
            return slots;
        }
    };

    function boot() {
        window.MDUpload.attachAll(document);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
</script>
