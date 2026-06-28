(function ($) {
    'use strict';

    var tesseractScriptPromise = null;
    var activeOcrRun = 0;
    var TESSERACT_CDN = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';

    function getModal() {
        return $('.hr_sell_list_photo_modal');
    }

    function setStatus($modal, message, isError) {
        $modal.find('.sell-list-ocr-status')
            .toggleClass('text-danger', !!isError)
            .toggleClass('text-muted', !isError)
            .text(message);
    }

    function setProgress($modal, percent) {
        var safePercent = Math.max(0, Math.min(100, Math.round(percent || 0)));
        $modal.find('.sell-list-ocr-progress-wrap').show();
        $modal.find('.sell-list-ocr-progress-bar')
            .css('width', safePercent + '%')
            .text(safePercent + '%');
    }

    function resetOcrUi($modal) {
        activeOcrRun++;
        $modal.find('.sell-list-ocr-text').val('');
        $modal.find('.sell-list-serials')
            .addClass('text-muted')
            .html('No serials detected yet.');
        $modal.find('.sell-list-copy-first-serial-btn')
            .prop('disabled', true)
            .removeData('serial');
        $modal.find('.sell-list-ocr-progress-wrap').hide();
        $modal.find('.sell-list-ocr-progress-bar').css('width', '0%').text('0%');
        setStatus($modal, 'Reading text from image...', false);
    }

    function loadTesseract() {
        if (window.Tesseract) {
            return $.Deferred().resolve(window.Tesseract).promise();
        }

        if (tesseractScriptPromise) {
            return tesseractScriptPromise;
        }

        tesseractScriptPromise = $.ajax({
            url: TESSERACT_CDN,
            dataType: 'script',
            cache: true,
        }).then(function () {
            if (!window.Tesseract) {
                return $.Deferred().reject('Tesseract failed to load.').promise();
            }

            return window.Tesseract;
        });

        return tesseractScriptPromise;
    }

    function extractSerials(text) {
        var matches = (String(text || '').toUpperCase().match(/[A-Z0-9]{8,25}/g) || []);
        var seen = {};

        return matches.filter(function (serial) {
            if (seen[serial]) {
                return false;
            }

            seen[serial] = true;
            return true;
        });
    }

    function renderSerials($modal, serials) {
        var $list = $modal.find('.sell-list-serials');
        var $copyFirst = $modal.find('.sell-list-copy-first-serial-btn');

        if (!serials.length) {
            $list.addClass('text-muted').html('No serial numbers detected.');
            $copyFirst.prop('disabled', true).removeData('serial');
            return;
        }

        $list.removeClass('text-muted').empty();
        $copyFirst.prop('disabled', false).data('serial', serials[0]);

        serials.forEach(function (serial) {
            $('<div/>', { class: 'sell-list-serial-row' })
                .append(
                    $('<button/>', {
                        type: 'button',
                        class: 'btn btn-xs btn-default sell-list-copy-serial-btn',
                        text: 'Copy',
                    }).attr('data-serial', serial)
                )
                .append($('<code/>').text(serial))
                .appendTo($list);
        });
    }

    function notifySuccess(message) {
        if (window.toastr) {
            toastr.success(message);
        }
    }

    function notifyError(message) {
        if (window.toastr) {
            toastr.error(message);
        }
    }

    function copyText(text, successMessage) {
        var value = String(text || '');

        if (!value) {
            notifyError('Nothing to copy.');
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value)
                .then(function () {
                    notifySuccess(successMessage);
                })
                .catch(function () {
                    fallbackCopy(value, successMessage);
                });
            return;
        }

        fallbackCopy(value, successMessage);
    }

    function fallbackCopy(text, successMessage) {
        var $textarea = $('<textarea readonly />')
            .css({
                position: 'fixed',
                left: '-9999px',
                top: '0',
            })
            .val(text)
            .appendTo('body');

        $textarea[0].select();
        document.execCommand('copy');
        $textarea.remove();
        notifySuccess(successMessage);
    }

    function loadImageToBlob(imageUrl, fallbackUrl) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                var canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error('Canvas toBlob failed'));
                    }
                }, 'image/png');
            };
            img.onerror = function () {
                if (fallbackUrl && imageUrl !== fallbackUrl) {
                    loadImageToBlob(fallbackUrl, null).then(resolve).catch(reject);
                } else {
                    reject(new Error('Image load failed'));
                }
            };
            img.src = imageUrl;
        });
    }

    function runOcr($modal, imageUrl) {
        var runId = activeOcrRun;
        var fallbackUrl = $modal.find('.sell-list-photo-preview').attr('data-fallback-url');

        loadTesseract()
            .then(function (Tesseract) {
                if (runId !== activeOcrRun) {
                    return null;
                }

                return loadImageToBlob(imageUrl, fallbackUrl).then(function (blob) {
                    if (runId !== activeOcrRun) {
                        return null;
                    }

                    return Tesseract.recognize(blob, 'eng', {
                        logger: function (message) {
                            if (runId !== activeOcrRun) {
                                return;
                            }

                            if (message && message.status) {
                                setStatus($modal, 'Reading text from image... ' + message.status, false);
                            }

                            if (message && typeof message.progress === 'number') {
                                setProgress($modal, message.progress * 100);
                            }
                        },
                    });
                });
            })
            .then(function (result) {
                if (!result || runId !== activeOcrRun) {
                    return;
                }

                var text = result.data && result.data.text ? result.data.text.trim() : '';
                var serials = extractSerials(text);

                $modal.find('.sell-list-ocr-text').val(text);
                renderSerials($modal, serials);
                setProgress($modal, 100);
                setStatus($modal, text ? 'OCR complete.' : 'OCR complete. No text detected.', false);
            })
            .catch(function () {
                if (runId !== activeOcrRun) {
                    return;
                }

                setStatus($modal, 'Unable to read text from this image.', true);
                $modal.find('.sell-list-ocr-progress-wrap').hide();
                notifyError('Unable to read text from this image.');
            });
    }

    $(document).on('shown.bs.modal', '.hr_sell_list_photo_modal', function () {
        var $modal = $(this);
        var $img = $modal.find('.sell-list-photo-preview');
        var imageUrl = $img.attr('src');

        resetOcrUi($modal);

        if (!imageUrl) {
            setStatus($modal, 'No image found.', true);
            return;
        }

        if (!$img[0].complete) {
            setStatus($modal, 'Loading image...', false);
        }

        runOcr($modal, imageUrl);
    });

    $(document).on('hidden.bs.modal', '.hr_sell_list_photo_modal', function () {
        activeOcrRun++;
    });

    $(document).on('error', '.sell-list-photo-preview', function () {
        var $img = $(this);
        var fallbackUrl = $img.attr('data-fallback-url');

        if (fallbackUrl && $img.attr('src') !== fallbackUrl) {
            $img.attr('src', fallbackUrl);
        }
    });

    $(document).on('click', '.sell-list-copy-text-btn', function () {
        copyText(getModal().find('.sell-list-ocr-text').val(), 'Text copied successfully');
    });

    $(document).on('click', '.sell-list-copy-first-serial-btn', function () {
        copyText($(this).data('serial'), 'Serial copied successfully');
    });

    $(document).on('click', '.sell-list-copy-serial-btn', function () {
        copyText($(this).attr('data-serial'), 'Serial copied successfully');
    });
    /* ───────────────────────────────────────────────
     * Sell Out → POS Price Sync
     * Stores clicked line prices for the copy-all
     * handler in pos.js to consume from data attributes.
     * ─────────────────────────────────────────────── */

    $(document).on('click', '.sell-list-copy-all-btn', function() {
        var $reportRow = $(this).closest('.sell-list-report-row');
        $reportRow.find('.sell-list-product-line[data-status="active"]').each(function() {
            var $line = $(this);
            var price = $line.attr('data-unit-price');
            if (!price || isNaN(parseFloat(price))) {
                var pt = $line.find('.tw-text-xs.tw-font-bold.tw-text-slate-800').text().replace(/,/g, '').trim();
                price = parseFloat(pt);
                if (!isNaN(price)) {
                    $line.attr('data-unit-price', price);
                }
            }
        });
    });
})(jQuery);
