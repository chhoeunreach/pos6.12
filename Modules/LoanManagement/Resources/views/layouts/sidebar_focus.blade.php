@if(request()->segment(1) === 'loan-management' || request()->boolean('installment_focus'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var menu = document.querySelector('.sidebar-menu');
    if (!menu) return;
    var currentUrl = window.location.href;
    var hasInstallmentFocus = new URLSearchParams(window.location.search).get('installment_focus') === '1';

    var links = {
        dashboard: "{{ route('loan-management.dashboard') }}",
        customers: "{{ route('loan-management.customers.index') }}",
        guarantors: "{{ route('loan-management.guarantors.index') }}",
        blacklist: "{{ route('loan-management.blacklist.index') }}",
        installment: "{{ route('loan-management.loans.index') }}",
        overdue: "{{ route('loan-management.overdue.index') }}",
        monthly: "{{ route('loan-management.monthly-payments.index') }}",
        gps: "{{ route('loan-management.gps.index') }}",
        chat: "{{ route('loan-management.chat.index') }}",
        reportsPayments: "{{ route('loan-management.reports.payments') }}",
        reportsSummary: "{{ route('loan-management.reports.index') }}",
        reportsAba: "{{ route('loan-management.aba.index') }}",
        toolsMonthlyImport: "{{ route('loan-management.tools.monthly-import-export') }}",
        toolsLoanImport: "{{ route('loan-management.tools.loan-import-export') }}",
        toolsNotify: "{{ route('loan-management.tools.send-notification') }}",
        settingsGeneral: "{{ route('loan-management.settings.index') }}",
        settingsLocations: "{{ route('loan-management.locations.index') }}",
        settingsPaymentMethods: "{{ route('loan-management.settings.payment-methods') }}",
        settingsCurrencies: "{{ route('loan-management.settings.currencies') }}"
    };

    function activeClass(url) {
        return currentUrl.indexOf(url) === 0 ? 'active' : '';
    }

    function treeOpenClass(urls) {
        if (!Array.isArray(urls)) return '';
        var active = urls.some(function (u) { return currentUrl.indexOf(u) === 0; });
        return active ? 'active menu-open' : '';
    }

    function treeMenuStyle(urls) {
        if (!Array.isArray(urls)) return '';
        var active = urls.some(function (u) { return currentUrl.indexOf(u) === 0; });
        return active ? 'style="display: block;"' : 'style="display: none;"';
    }

    menu.innerHTML = `
        <li class="header">INSTALLMENT MANAGEMENT</li>
        <li class="installment-controls" style="padding:6px 10px;">
            <a href="javascript:void(0)" id="btn-collapse-all" style="display:inline-block;padding:2px 6px;"><i class="fa fa-compress"></i> Collapse</a>
            <a href="javascript:void(0)" id="btn-expand-all" style="display:inline-block;padding:2px 6px;"><i class="fa fa-expand"></i> Expand</a>
        </li>
        <li class="${activeClass(links.dashboard)}"><a href="${links.dashboard}"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>
        <li class="treeview installment-section section-customers ${treeOpenClass([links.customers, links.guarantors, links.blacklist])}" data-section="customers">
            <a href="#"><i class="fa fa-users"></i> <span>Customers</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu" ${treeMenuStyle([links.customers, links.guarantors, links.blacklist])}>
                <li class="${activeClass(links.customers)}"><a href="${links.customers}"><i class="fa fa-user"></i> Customers</a></li>
                <li class="${activeClass(links.guarantors)}"><a href="${links.guarantors}"><i class="fa fa-handshake-o"></i> Guarantors</a></li>
                <li class="${activeClass(links.blacklist)}"><a href="${links.blacklist}"><i class="fa fa-ban"></i> Blacklist</a></li>
                <li><a href="{{ route('loan-management.customers.clone-from-pos') }}"><i class="fa fa-copy"></i> Clone From POS (Recommended)</a></li>
            </ul>
        </li>
        <li class="treeview installment-section section-loans ${treeOpenClass([links.installment, links.overdue])}" data-section="loans">
            <a href="#"><i class="fa fa-credit-card"></i> <span>Loans</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu" ${treeMenuStyle([links.installment, links.overdue])}>
                <li class="${activeClass(links.installment)}"><a href="${links.installment}"><i class="fa fa-money"></i> Installment</a></li>
                <li class="${activeClass(links.overdue)}"><a href="${links.overdue}"><i class="fa fa-exclamation-triangle"></i> Overdue / Late Payments</a></li>
                <li><a href="{{ route('loan-management.loans.create-from-sell') }}"><i class="fa fa-plus-circle"></i> Create From Sell (Recommended)</a></li>
            </ul>
        </li>
        <li class="treeview installment-section section-collections ${treeOpenClass([links.monthly, links.gps, links.chat])}" data-section="collections">
            <a href="#"><i class="fa fa-map-marker"></i> <span>Collections</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu" ${treeMenuStyle([links.monthly, links.gps, links.chat])}>
                <li class="${activeClass(links.monthly)}"><a href="${links.monthly}"><i class="fa fa-calendar-check-o"></i> Monthly Payments</a></li>
                <li class="${activeClass(links.gps)}"><a href="${links.gps}"><i class="fa fa-map"></i> GPS Tracking</a></li>
                <li class="${activeClass(links.chat)}"><a href="${links.chat}"><i class="fa fa-comments"></i> Live Chat</a></li>
                <li><a href="{{ route('loan-management.collection-visits.index') }}"><i class="fa fa-street-view"></i> Collection Visits (Recommended)</a></li>
            </ul>
        </li>
        <li class="treeview installment-section section-reports ${treeOpenClass([links.reportsPayments, links.reportsSummary, links.reportsAba])}" data-section="reports">
            <a href="#"><i class="fa fa-bar-chart"></i> <span>Reports</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu" ${treeMenuStyle([links.reportsPayments, links.reportsSummary, links.reportsAba])}>
                <li class="${activeClass(links.reportsPayments)}"><a href="${links.reportsPayments}"><i class="fa fa-line-chart"></i> Payments Report</a></li>
                <li class="${activeClass(links.reportsSummary)}"><a href="${links.reportsSummary}"><i class="fa fa-list"></i> Loan Summary Report</a></li>
                <li class="${activeClass(links.reportsAba)}"><a href="${links.reportsAba}"><i class="fa fa-qrcode"></i> ABA Transactions Report</a></li>
            </ul>
        </li>
        <li class="treeview installment-section section-tools ${treeOpenClass([links.toolsMonthlyImport, links.toolsLoanImport, links.toolsNotify])}" data-section="tools">
            <a href="#"><i class="fa fa-cogs"></i> <span>Tools</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu" ${treeMenuStyle([links.toolsMonthlyImport, links.toolsLoanImport, links.toolsNotify])}>
                <li class="${activeClass(links.toolsMonthlyImport)}"><a href="${links.toolsMonthlyImport}"><i class="fa fa-exchange"></i> Monthly Payments Import/Export</a></li>
                <li class="${activeClass(links.toolsLoanImport)}"><a href="${links.toolsLoanImport}"><i class="fa fa-upload"></i> Loan Import/Export</a></li>
                <li class="${activeClass(links.toolsNotify)}"><a href="${links.toolsNotify}"><i class="fa fa-bell"></i> Send Notification</a></li>
            </ul>
        </li>
        <li class="treeview installment-section section-settings ${treeOpenClass([links.settingsGeneral, links.settingsLocations, links.settingsPaymentMethods, links.settingsCurrencies])}" data-section="settings">
            <a href="#"><i class="fa fa-wrench"></i> <span>Settings</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu" ${treeMenuStyle([links.settingsGeneral, links.settingsLocations, links.settingsPaymentMethods, links.settingsCurrencies])}>
                <li class="${activeClass(links.settingsGeneral)}"><a href="${links.settingsGeneral}"><i class="fa fa-building"></i> Business Setting</a></li>
                <li class="${activeClass(links.settingsLocations)}"><a href="${links.settingsLocations}"><i class="fa fa-map-marker"></i> Locations</a></li>
                <li class="${activeClass(links.settingsPaymentMethods)}"><a href="${links.settingsPaymentMethods}"><i class="fa fa-credit-card"></i> Payment Methods</a></li>
                <li class="${activeClass(links.settingsCurrencies)}"><a href="${links.settingsCurrencies}"><i class="fa fa-money"></i> Currencies</a></li>
            </ul>
        </li>
    `;

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
