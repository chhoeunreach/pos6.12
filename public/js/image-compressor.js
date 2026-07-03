/**
 * Client-side image compression utility
 * Compresses images on the browser before upload using Canvas API
 */
window.ImageCompressor = {
    /**
     * Compress a single image file
     * @param {File} file
     * @param {Object} opts - { maxWidth, maxHeight, quality }
     * @returns {Promise<File>}
     */
    compress: function (file, opts) {
        opts = opts || {};
        var maxW = opts.maxWidth || 800;
        var maxH = opts.maxHeight || 800;
        var quality = opts.quality || 0.85;

        return new Promise(function (resolve) {
            if (!file || !file.type || !file.type.match('image.*') || file.type === 'image/gif' || file.size < 1024) {
                resolve(file);
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.onload = function () {
                    var canvas = document.createElement('canvas');
                    var w = img.width;
                    var h = img.height;

                    if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                    if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }

                    canvas.width = w;
                    canvas.height = h;
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);

                    canvas.toBlob(function (blob) {
                        if (!blob || blob.size >= file.size) {
                            resolve(file);
                            return;
                        }
                        var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                        var cf = new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
                        resolve(cf);
                    }, 'image/jpeg', quality);
                };
                img.onerror = function () { resolve(file); };
                img.src = e.target.result;
            };
            reader.onerror = function () { resolve(file); };
            reader.readAsDataURL(file);
        });
    }
};
