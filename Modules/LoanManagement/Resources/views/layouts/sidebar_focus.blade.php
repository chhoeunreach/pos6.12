@if(request()->segment(1) === 'loan-management' || request()->boolean('installment_focus'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var menu = document.querySelector('.sidebar-menu');
    if (!menu) return;
    menu.innerHTML = @json(view('loanmanagement::layouts.partials.navigation_tree')->render());

    function setSectionState(li, open) {
        var ul = li.querySelector(':scope > ul.treeview-menu');
        if (!ul) return;
        if (open) {
            li.classList.add('menu-open', 'active');
            ul.style.display = 'block';
        } else {
            li.classList.remove('menu-open');
            if (!li.querySelector('li.active')) {
                li.classList.remove('active');
            }
            ul.style.display = 'none';
        }
    }

    var storageKey = 'installment_sidebar_open_sections';
    function getSectionKey(li) {
        var classes = Array.from(li.classList);
        var match = classes.find(function (c) { return c.indexOf('section-') === 0; });
        return match ? match.replace('section-', '') : null;
    }
    function getOpenSections() {
        try {
            var raw = localStorage.getItem(storageKey);
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }
    function saveOpenSections() {
        var opened = [];
        menu.querySelectorAll('li.installment-section.menu-open').forEach(function (li) {
            var key = getSectionKey(li);
            if (key) opened.push(key);
        });
        localStorage.setItem(storageKey, JSON.stringify(opened));
    }

    window.collapseMenu = function () {
        menu.querySelectorAll('li.installment-section').forEach(function (li) {
            setSectionState(li, false);
        });
        saveOpenSections();
    };

    window.showMenu = function (sectionKey) {
        var li = menu.querySelector('li.section-' + sectionKey);
        if (li) {
            li.style.display = '';
            setSectionState(li, true);
            saveOpenSections();
        }
    };

    window.hideMenu = function (sectionKey) {
        var li = menu.querySelector('li.section-' + sectionKey);
        if (li) {
            li.style.display = 'none';
            setSectionState(li, false);
            saveOpenSections();
        }
    };

    document.getElementById('btn-collapse-all')?.addEventListener('click', function () {
        window.collapseMenu();
    });
    document.getElementById('btn-expand-all')?.addEventListener('click', function () {
        menu.querySelectorAll('li.installment-section').forEach(function (li) {
            if (li.style.display !== 'none') setSectionState(li, true);
        });
        saveOpenSections();
    });

    // Restore expanded sections after refresh (like Purchase menu behavior).
    var restored = getOpenSections();
    if (restored.length) {
        menu.querySelectorAll('li.installment-section').forEach(function (li) {
            var key = getSectionKey(li);
            if (key && restored.indexOf(key) !== -1) {
                setSectionState(li, true);
            }
        });
    }

    // Ensure current active page's section is opened.
    menu.querySelectorAll('li.installment-section').forEach(function (li) {
        if (li.querySelector('li.active')) {
            setSectionState(li, true);
        }
    });

    menu.querySelectorAll('li.treeview > a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var li = a.parentElement;
            var open = li.classList.contains('menu-open');
            setSectionState(li, !open);
            saveOpenSections();
        });
    });

    // Submenu action click should keep section expanded and remember it.
    menu.querySelectorAll('li.treeview-menu li a').forEach(function (a) {
        a.addEventListener('click', function () {
            var parentSection = a.closest('li.installment-section');
            if (parentSection) {
                setSectionState(parentSection, true);
                saveOpenSections();
            }
        });
    });
});
</script>
@endif
