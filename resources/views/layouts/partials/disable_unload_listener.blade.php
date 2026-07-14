<script type="text/javascript">
    (function () {
        if (! window.EventTarget || window.__disableUnloadListenerPatchApplied) {
            return;
        }

        window.__disableUnloadListenerPatchApplied = true;

        var originalAddEventListener = window.EventTarget.prototype.addEventListener;
        var originalRemoveEventListener = window.EventTarget.prototype.removeEventListener;

        window.EventTarget.prototype.addEventListener = function (type, listener, options) {
            if (type === 'unload') {
                return;
            }

            return originalAddEventListener.call(this, type, listener, options);
        };

        window.EventTarget.prototype.removeEventListener = function (type, listener, options) {
            if (type === 'unload') {
                return;
            }

            return originalRemoveEventListener.call(this, type, listener, options);
        };
    })();
</script>
