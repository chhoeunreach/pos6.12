(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var toggle = document.getElementById('loanSidebarToggle');

        if (toggle) {
            toggle.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    document.body.classList.toggle('lm-sidebar-open');
                    return;
                }
                document.body.classList.toggle('lm-sidebar-collapsed');
            });
        }

        document.addEventListener('click', function (event) {
            var btn = event.target.closest ? event.target.closest('.lm-menu-toggle') : null;
            if (!btn) return;

            event.preventDefault();
            var group = btn.closest('.lm-menu-group');
            if (!group) return;

            group.classList.toggle('open');
            var submenu = group.querySelector('.lm-submenu');
            if (submenu) {
                submenu.style.display = group.classList.contains('open') ? 'block' : 'none';
            }
        });
    });
})();
