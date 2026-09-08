(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var toggles = Array.prototype.slice.call(document.querySelectorAll('#loanSidebarToggle, #loanSidebarCollapse, #loanMobileSidebarToggle'));
        var sidebar = document.getElementById('loanManagementSidebar');
        var sidebarBackdrop = document.getElementById('loanSidebarBackdrop');

        if (!sidebarBackdrop && sidebar) {
            sidebarBackdrop = document.createElement('button');
            sidebarBackdrop.type = 'button';
            sidebarBackdrop.id = 'loanSidebarBackdrop';
            sidebarBackdrop.className = 'lm-sidebar-backdrop';
            sidebarBackdrop.setAttribute('aria-label', 'Close sidebar');
            sidebar.parentNode.insertBefore(sidebarBackdrop, sidebar);
        }

        function isMobile() {
            return window.innerWidth <= 992;
        }

        function closeSidebar() {
            document.body.classList.remove('lm-sidebar-open');
        }

        function toggleSidebar() {
            if (isMobile()) {
                document.body.classList.remove('lm-sidebar-collapsed');
                document.body.classList.toggle('lm-sidebar-open');
                return;
            }
            document.body.classList.toggle('lm-sidebar-collapsed');
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleSidebar();
            });
        });

        // Sidebar close button
        var closeBtn = document.getElementById('loanSidebarClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', function (event) {
                event.preventDefault();
                closeSidebar();
            });
        }

        // Close sidebar on mobile when clicking backdrop
        document.addEventListener('click', function (event) {
            if (!isMobile()) return;
            if (!document.body.classList.contains('lm-sidebar-open')) return;

            if (!sidebar) return;

            // If click is outside sidebar and not on toggle button
            var clickedToggle = toggles.some(function (toggle) {
                return toggle === event.target || toggle.contains(event.target);
            });

            if (!sidebar.contains(event.target) && !clickedToggle) {
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

        var sidebarSearch = document.getElementById('lmSidebarSearch');
        if (sidebarSearch) {
            var searchTimer = null;

            function runMenuFilter() {
                var term = sidebarSearch.value.trim().toLowerCase();
                var sections = document.querySelectorAll('.lm-menu-section');

                sections.forEach(function (section) {
                    var hasVisibleItem = false;
                    var entries = Array.prototype.filter.call(section.children, function (child) {
                        return child.classList && (child.classList.contains('lm-menu-link') || child.classList.contains('lm-menu-group'));
                    });

                    entries.forEach(function (entry) {
                        var text = (entry.getAttribute('data-lm-menu-text') || entry.textContent || '').toLowerCase();
                        var isMatch = !term || text.indexOf(term) !== -1;

                        entry.style.display = isMatch ? '' : 'none';
                        if (isMatch) {
                            hasVisibleItem = true;
                        }

                        if (term && isMatch && entry.classList.contains('lm-menu-group')) {
                            entry.classList.add('open');
                            var submenu = entry.querySelector('.lm-submenu');
                            if (submenu) {
                                submenu.style.display = 'block';
                            }
                        }
                    });

                    section.style.display = hasVisibleItem ? '' : 'none';
                });
            }

            // Debounce filtering for smoother typing on older devices
            sidebarSearch.addEventListener('input', function () {
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                searchTimer = setTimeout(runMenuFilter, 'ontouchstart' in window ? 80 : 0);
            }, { passive: true });
        }

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
                } else {
                    document.body.classList.remove('lm-sidebar-collapsed');
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
