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

        function isMobile() {
            return window.innerWidth <= 992;
        }

        function closeSidebar() {
            document.body.classList.remove('lm-sidebar-open');
        }

        function toggleSidebar() {
            if (isMobile()) {
                document.body.classList.toggle('lm-sidebar-open');
                return;
            }
            document.body.classList.toggle('lm-sidebar-collapsed');
        }

        if (toggle) {
            toggle.addEventListener('click', toggleSidebar);
        }

        // Sidebar close button
        var closeBtn = document.getElementById('loanSidebarClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        // Close sidebar on mobile when clicking backdrop
        document.addEventListener('click', function (event) {
            if (!isMobile()) return;
            if (!document.body.classList.contains('lm-sidebar-open')) return;

            var sidebar = document.getElementById('loanManagementSidebar');
            if (!sidebar) return;

            // If click is outside sidebar and not on toggle button
            if (!sidebar.contains(event.target) && event.target !== toggle && !toggle.contains(event.target)) {
                closeSidebar();
            }
        });

        // Close sidebar on Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                if (document.body.classList.contains('lm-sidebar-open')) {
                    closeSidebar();
                    event.preventDefault();
                }
            }
        });

        // Close sidebar when clicking a link on mobile
        document.addEventListener('click', function (event) {
            if (!isMobile()) return;
            var link = event.target.closest('.lm-menu-link, .lm-submenu-link');
            if (link && !link.classList.contains('lm-menu-toggle')) {
                closeSidebar();
            }
        });

        // Submenu toggle
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

        // Handle window resize: remove mobile classes when going to desktop
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (!isMobile()) {
                    document.body.classList.remove('lm-sidebar-open');
                }
            }, 150);
        });

        // Handle swipe to open/close sidebar on mobile
        var touchStartX = 0;
        var touchStartY = 0;
        document.addEventListener('touchstart', function (event) {
            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', function (event) {
            if (!isMobile()) return;
            var touchEndX = event.changedTouches[0].clientX;
            var touchEndY = event.changedTouches[0].clientY;
            var deltaX = touchEndX - touchStartX;
            var deltaY = Math.abs(touchEndY - touchStartY);

            // Only trigger on horizontal swipe (not vertical scroll)
            if (deltaY > 50) return;

            // Swipe right from left edge to open sidebar
            if (touchStartX < 20 && deltaX > 80) {
                document.body.classList.add('lm-sidebar-open');
            }
            // Swipe left to close sidebar
            else if (deltaX < -80 && document.body.classList.contains('lm-sidebar-open')) {
                closeSidebar();
            }
        }, { passive: true });
    });
})();
