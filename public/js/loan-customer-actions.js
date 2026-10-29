(function () {
    'use strict';
    var current = null;

    function close(restoreFocus) {
        if (!current) return;
        var previous = current;
        current = null;
        previous.menu.hidden = true;
        previous.button.setAttribute('aria-expanded', 'false');
        previous.home.appendChild(previous.menu);
        if (restoreFocus && previous.button.isConnected) previous.button.focus();
    }

    function open(button, focusLast) {
        close(false);
        var menu = document.getElementById(button.getAttribute('aria-controls'));
        if (!menu) return;
        current = { button: button, menu: menu, home: menu.parentNode };
        // Keep the menu outside the table's scrolling and clipping containers.
        document.body.appendChild(menu);
        menu.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        var rect = button.getBoundingClientRect();
        var height = menu.offsetHeight;
        var top = rect.bottom + 5;
        if (top + height > window.innerHeight - 8) top = Math.max(8, rect.top - height - 5);
        menu.style.top = top + 'px';
        menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8)) + 'px';
        if (focusLast !== undefined) {
            var items = menu.querySelectorAll('[role="menuitem"]');
            if (items.length) items[focusLast ? items.length - 1 : 0].focus();
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.lm-customer-actions-toggle');
        if (button) {
            if (current && current.button === button) close(false);
            else open(button);
            return;
        }
        if (!current) return;
        if (!current.menu.contains(event.target)) close(false);
        // Links navigate normally; form buttons keep their existing confirmation.
        else if (event.target.closest('a[role="menuitem"]')) close(false);
    });

    document.addEventListener('keydown', function (event) {
        var button = event.target.closest('.lm-customer-actions-toggle');
        if (button && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            open(button, event.key === 'ArrowUp');
            return;
        }
        if (!current) return;
        if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
        if (event.key === 'Tab') { close(true); return; }
        if (!current.menu.contains(event.target)) return;
        var items = Array.from(current.menu.querySelectorAll('[role="menuitem"]'));
        var index = items.indexOf(document.activeElement);
        var next;
        if (event.key === 'ArrowDown') next = (index + 1) % items.length;
        else if (event.key === 'ArrowUp') next = (index + items.length - 1) % items.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = items.length - 1;
        else return;
        event.preventDefault();
        if (items[next]) items[next].focus();
    });

    document.addEventListener('focusin', function (event) {
        if (current && event.target !== current.button && !current.menu.contains(event.target)) close(false);
    });
    document.addEventListener('scroll', function (event) {
        if (current && !current.menu.contains(event.target)) close(false);
    }, true);
    window.addEventListener('resize', function () { close(false); });
    if (window.jQuery) {
        window.jQuery('#loanCustomersTable').on('preDraw.dt column-visibility.dt', function () { close(false); });
    }
})();
