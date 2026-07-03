@php
    $asset_v = $asset_v ?? config('constants.asset_version', 1);
    $__system_settings = $__system_settings ?? [];
    $businessName = Session::get('business.name', config('app.name', 'Ultimate POS'));
    $pageTitle = trim($__env->yieldContent('page_title')) ?: 'Smart Stock Inventory';
    $menuGroups = [
        [
            'label' => 'Smart Stock Inventory',
            'icon' => 'fa fa-cubes',
            'active' => ['smart-stock-inventory'],
            'items' => [
                ['label' => 'Dashboard', 'url' => route('ssi.dashboard'), 'active' => ['smart-stock-inventory/dashboard']],
                ['label' => 'Inventory Count', 'url' => route('ssi.count.index'), 'active' => ['smart-stock-inventory/count']],
                ['label' => 'Enterprise Count', 'url' => route('ssi.count.enterprise'), 'active' => ['smart-stock-inventory/count/enterprise']],
                ['label' => 'Verification Report', 'url' => route('ssi.verification.index'), 'active' => ['smart-stock-inventory/verification']],
                ['label' => 'Mismatch Detector', 'url' => route('ssi.mismatch.index'), 'active' => ['smart-stock-inventory/mismatch']],
                ['label' => 'Movement History', 'url' => route('ssi.movement.index'), 'active' => ['smart-stock-inventory/movement']],
                ['label' => 'IMEI Management', 'url' => route('ssi.imei.index'), 'active' => ['smart-stock-inventory/imei']],
                ['label' => 'Lot Management', 'url' => route('ssi.lot.index'), 'active' => ['smart-stock-inventory/lot']],
                ['label' => 'Fix Logs', 'url' => route('ssi.fix_logs'), 'active' => ['smart-stock-inventory/fix-logs']],
                ['label' => 'Inventory Reports', 'url' => route('ssi.count.reports'), 'active' => ['smart-stock-inventory/count/reports']],
                ['label' => 'Stock Sell Report', 'url' => url('/reports/stock-sell-report'), 'active' => ['reports/stock-sell-report']],
                ['label' => 'Stock Purchase Report', 'url' => url('/reports/stock-purchase-report'), 'active' => ['reports/stock-purchase-report']],
                @if(\Nwidart\Modules\Facades\Module::has('stock_transfers') && \Nwidart\Modules\Facades\Module::isEnabled('stock_transfers'))
                ['label' => 'Stock Transfer Report', 'url' => url('/reports/stock-transfer-report'), 'active' => ['reports/stock-transfer-report']],
                @endif
                ['label' => 'Settings', 'url' => route('ssi.settings.index'), 'active' => ['smart-stock-inventory/settings']],
            ],
        ],
        [
            'label' => 'Contacts',
            'icon' => 'fa fa-address-book',
            'active' => ['contacts', 'customer-group'],
            'items' => [
                ['label' => 'Suppliers', 'url' => url('/contacts?type=supplier'), 'active' => ['contacts?type=supplier']],
                ['label' => 'Customers', 'url' => url('/contacts?type=customer'), 'active' => ['contacts?type=customer']],
                ['label' => 'Customer Groups', 'url' => url('/customer-group'), 'active' => ['customer-group']],
                ['label' => 'Import Contacts', 'url' => url('/contacts/import'), 'active' => ['contacts/import']],
            ],
        ],
        [
            'label' => 'Products',
            'icon' => 'fa fa-cube',
            'active' => ['products', 'update-product-price', 'labels', 'variation-templates', 'import-products', 'import-opening-stock', 'selling-price-group', 'units', 'taxonomies', 'brands', 'warranties'],
            'items' => [
                ['label' => 'List Products', 'url' => url('/products'), 'active' => ['products']],
                ['label' => 'Add Product', 'url' => url('/products/create'), 'active' => ['products/create']],
                ['label' => 'Update Price', 'url' => url('/update-product-price'), 'active' => ['update-product-price']],
                ['label' => 'Print Labels', 'url' => url('/labels/show'), 'active' => ['labels/show']],
                ['label' => 'Variations', 'url' => url('/variation-templates'), 'active' => ['variation-templates']],
                ['label' => 'Import Products', 'url' => url('/import-products'), 'active' => ['import-products']],
                ['label' => 'Import Opening Stock', 'url' => url('/import-opening-stock'), 'active' => ['import-opening-stock']],
                ['label' => 'Selling Price Group', 'url' => url('/selling-price-group'), 'active' => ['selling-price-group']],
                ['label' => 'Units', 'url' => url('/units'), 'active' => ['units']],
                ['label' => 'Categories', 'url' => url('/taxonomies?type=product'), 'active' => ['taxonomies?type=product']],
                ['label' => 'Brands', 'url' => url('/brands'), 'active' => ['brands']],
                ['label' => 'Warranties', 'url' => url('/warranties'), 'active' => ['warranties']],
            ],
        ],
        [
            'label' => 'Purchases',
            'icon' => 'fa fa-download',
            'active' => ['purchase-requisition', 'purchases', 'purchase-return'],
            'items' => [
                ['label' => 'Purchase Requisition', 'url' => url('/purchase-requisition'), 'active' => ['purchase-requisition']],
                ['label' => 'List Purchases', 'url' => url('/purchases'), 'active' => ['purchases']],
                ['label' => 'Add Purchase', 'url' => url('/purchases/create'), 'active' => ['purchases/create']],
                ['label' => 'List Purchase Return', 'url' => url('/purchase-return'), 'active' => ['purchase-return']],
            ],
        ],
        [
            'label' => 'Sell',
            'icon' => 'fa fa-upload',
            'active' => ['sells', 'pos', 'sell-return', 'shipments', 'discount', 'import-sales'],
            'items' => [
                ['label' => 'All sales', 'url' => url('/sells'), 'active' => ['sells']],
                ['label' => 'Add Sale', 'url' => url('/sells/create'), 'active' => ['sells/create']],
                ['label' => 'List POS', 'url' => url('/pos'), 'active' => ['pos']],
                ['label' => 'POS', 'url' => url('/pos/create'), 'active' => ['pos/create']],
                ['label' => 'Add Draft', 'url' => url('/sells/create?status=draft'), 'active' => ['sells/create?status=draft']],
                ['label' => 'List Drafts', 'url' => url('/sells/drafts'), 'active' => ['sells/drafts']],
                ['label' => 'Add Quotation', 'url' => url('/sells/create?status=quotation'), 'active' => ['sells/create?status=quotation']],
                ['label' => 'List quotations', 'url' => url('/sells/quotations'), 'active' => ['sells/quotations']],
                ['label' => 'List Sell Return', 'url' => url('/sell-return'), 'active' => ['sell-return']],
                ['label' => 'Shipments', 'url' => url('/shipments'), 'active' => ['shipments']],
                ['label' => 'Discounts', 'url' => url('/discount'), 'active' => ['discount']],
                ['label' => 'Import Sales', 'url' => url('/import-sales'), 'active' => ['import-sales']],
            ],
        ],
        [
            'label' => 'Stock Transfers',
            'icon' => 'fa fa-truck',
            'active' => ['stock-transfers'],
            'items' => [
                ['label' => 'List Stock Transfers', 'url' => url('/stock-transfers'), 'active' => ['stock-transfers']],
                ['label' => 'Add Stock Transfer', 'url' => url('/stock-transfers/create'), 'active' => ['stock-transfers/create']],
            ],
        ],
        [
            'label' => 'Stock Adjustment',
            'icon' => 'fa fa-database',
            'active' => ['stock-adjustments'],
            'items' => [
                ['label' => 'List Stock Adjustments', 'url' => url('/stock-adjustments'), 'active' => ['stock-adjustments']],
                ['label' => 'Add Stock Adjustment', 'url' => url('/stock-adjustments/create'), 'active' => ['stock-adjustments/create']],
            ],
        ],
        [
            'label' => 'Reports',
            'icon' => 'fa fa-line-chart',
            'active' => ['reports', 'manage-lot'],
            'items' => [
                ['label' => 'Profit / Loss Report', 'url' => url('/reports/profit-loss'), 'active' => ['reports/profit-loss']],
                ['label' => 'Purchase & Sale', 'url' => url('/reports/purchase-sell'), 'active' => ['reports/purchase-sell']],
                ['label' => 'Tax Report', 'url' => url('/reports/tax-report'), 'active' => ['reports/tax-report']],
                ['label' => 'Supplier & Customer Report', 'url' => url('/reports/customer-supplier'), 'active' => ['reports/customer-supplier']],
                ['label' => 'Customer Groups Report', 'url' => url('/reports/customer-group'), 'active' => ['reports/customer-group']],
                ['label' => 'Stock Report', 'url' => url('/reports/stock-report'), 'active' => ['reports/stock-report']],
                ['label' => 'Lot Report', 'url' => url('/reports/lot-report'), 'active' => ['reports/lot-report']],
                ['label' => 'Manage Lot', 'url' => url('/manage-lot'), 'active' => ['manage-lot']],
                ['label' => 'Stock Adjustment Report', 'url' => url('/reports/stock-adjustment-report'), 'active' => ['reports/stock-adjustment-report']],
                ['label' => 'Trending Products', 'url' => url('/reports/trending-products'), 'active' => ['reports/trending-products']],
                ['label' => 'Items Report', 'url' => url('/reports/items-report'), 'active' => ['reports/items-report']],
                ['label' => 'Product Purchase Report', 'url' => url('/reports/product-purchase-report'), 'active' => ['reports/product-purchase-report']],
                ['label' => 'Product Sell Report', 'url' => url('/reports/product-sell-report'), 'active' => ['reports/product-sell-report']],
                ['label' => 'Purchase Payment Report', 'url' => url('/reports/purchase-payment-report'), 'active' => ['reports/purchase-payment-report']],
                ['label' => 'Sell Payment Report', 'url' => url('/reports/sell-payment-report'), 'active' => ['reports/sell-payment-report']],
                ['label' => 'Expense Report', 'url' => url('/reports/expense-report'), 'active' => ['reports/expense-report']],
                ['label' => 'Register Report', 'url' => url('/reports/register-report'), 'active' => ['reports/register-report']],
                ['label' => 'Sales Representative Report', 'url' => url('/reports/sales-representative-report'), 'active' => ['reports/sales-representative-report']],
                ['label' => 'Activity Log', 'url' => url('/reports/activity-log'), 'active' => ['reports/activity-log']],
            ],
        ],
        [
            'label' => 'Settings',
            'icon' => 'fa fa-cogs',
            'active' => ['business', 'business-location', 'invoice-schemes', 'invoice-layouts', 'barcodes', 'printers', 'tax-rates', 'types-of-service'],
            'items' => [
                ['label' => 'Business Settings', 'url' => url('/business/settings'), 'active' => ['business/settings']],
                ['label' => 'Business Locations', 'url' => url('/business-location'), 'active' => ['business-location']],
                ['label' => 'Invoice Settings', 'url' => url('/invoice-schemes'), 'active' => ['invoice-schemes', 'invoice-layouts']],
                ['label' => 'Barcode Settings', 'url' => url('/barcodes'), 'active' => ['barcodes']],
                ['label' => 'Receipt Printers', 'url' => url('/printers'), 'active' => ['printers']],
                ['label' => 'Tax Rates', 'url' => url('/tax-rates'), 'active' => ['tax-rates']],
                ['label' => 'Types of service', 'url' => url('/types-of-service'), 'active' => ['types-of-service']],
            ],
        ],
    ];
    $currentPath = trim(request()->path(), '/');
    $currentWithQuery = $currentPath . (request()->getQueryString() ? '?' . request()->getQueryString() : '');
    $isActive = function (array $patterns) use ($currentPath, $currentWithQuery) {
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern, '/');
            if ($currentWithQuery === $pattern || $currentPath === $pattern || str_starts_with($currentPath . '/', $pattern . '/')) {
                return true;
            }
        }

        return false;
    };
    $isItemActive = function (array $patterns) use ($currentPath, $currentWithQuery) {
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern, '/');
            if ($currentWithQuery === $pattern || $currentPath === $pattern) {
                return true;
            }
        }

        return false;
    };
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} - {{ $businessName }}</title>

    @include('layouts.partials.css')
    @include('layouts.partials.extracss')
    <link rel="stylesheet" href="{{ asset('Modules/SmartStockInventory/Resources/assets/css/smart_stock_inventory.css') }}">
    <style>
        body.ssi-standalone {
            min-height: 100vh;
            background: #f4f6f9;
        }
        .ssi-shell {
            display: flex;
            min-height: 100vh;
        }
        .ssi-sidebar {
            width: 260px;
            flex: 0 0 260px;
            background: #fff;
            border-right: 1px solid #e5e7eb;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            z-index: 30;
        }
        .ssi-brand {
            height: 58px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 18px;
            color: #fff;
            background: #1f6f8b;
            font-weight: 600;
        }
        .ssi-brand-mark {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.16);
        }
        .ssi-brand-text {
            min-width: 0;
            line-height: 1.15;
        }
        .ssi-brand-text span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ssi-brand-subtitle {
            font-size: 11px;
            opacity: 0.8;
            font-weight: 400;
        }
        .ssi-nav {
            padding: 12px 10px;
            height: calc(100vh - 58px);
            overflow-y: auto;
        }
        .ssi-nav-section {
            margin-bottom: 4px;
        }
        .ssi-nav-section summary {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 40px;
            padding: 9px 12px;
            margin-bottom: 3px;
            border-radius: 7px;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            list-style: none;
        }
        .ssi-nav-section summary::-webkit-details-marker {
            display: none;
        }
        .ssi-nav-section summary:hover,
        .ssi-nav-section[open] > summary {
            color: #111827;
            background: #e9f4f8;
        }
        .ssi-nav-section summary .ssi-chevron {
            margin-left: auto;
            font-size: 10px;
            transition: transform 0.2s ease;
        }
        .ssi-nav-section[open] summary .ssi-chevron {
            transform: rotate(90deg);
        }
        .ssi-nav-sub {
            padding: 2px 0 6px 31px;
        }
        .ssi-nav a {
            display: flex;
            align-items: center;
            min-height: 31px;
            padding: 6px 9px;
            margin-bottom: 2px;
            border-radius: 6px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
        }
        .ssi-nav a:hover,
        .ssi-nav a.active {
            color: #111827;
            background: #e9f4f8;
        }
        .ssi-nav a.active {
            border-left: 3px solid #1f6f8b;
        }
        .ssi-nav-section summary > i {
            width: 18px;
            text-align: center;
            color: #1f6f8b;
        }
        .ssi-main {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .ssi-topbar {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 18px;
            color: #fff;
            background: #1f6f8b;
            border-bottom: 1px solid rgba(255, 255, 255, 0.16);
        }
        .ssi-topbar .btn {
            border-radius: 6px;
        }
        .ssi-mobile-menu {
            display: none;
        }
        .ssi-user {
            text-align: right;
            font-size: 12px;
            line-height: 1.3;
            opacity: 0.92;
        }
        .ssi-page {
            flex: 1;
            overflow: auto;
            padding: 18px;
        }
        .ssi-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }
        .ssi-page-header h1 {
            margin: 0;
            color: #111827;
            font-size: 24px;
            font-weight: 600;
        }
        .ssi-action-bar {
            margin-bottom: 12px;
        }
        .ssi-action-bar .btn {
            margin-bottom: 4px;
            border-radius: 5px;
        }
        .ssi-overlay {
            display: none;
        }
        #ssi_app .box {
            border-radius: 8px;
            border-top-color: #1f6f8b;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
        }
        @media (max-width: 991px) {
            .ssi-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: -280px;
                transition: left 0.2s ease;
            }
            .ssi-sidebar.is-open {
                left: 0;
            }
            .ssi-mobile-menu {
                display: inline-flex;
            }
            .ssi-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                z-index: 20;
            }
            .ssi-overlay.is-open {
                display: block;
            }
            .ssi-page {
                padding: 12px;
            }
            .ssi-page-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
        @media print {
            .ssi-sidebar,
            .ssi-topbar,
            .ssi-action-bar,
            .ssi-overlay {
                display: none !important;
            }
            .ssi-shell,
            .ssi-main,
            .ssi-page {
                display: block;
                padding: 0;
                min-height: auto;
                overflow: visible;
            }
        }
    </style>
    @yield('css')
</head>
<body class="hold-transition skin-blue-light ssi-standalone">
    <div class="ssi-shell">
        <aside class="ssi-sidebar no-print" id="ssi_sidebar">
            <a href="{{ route('ssi.dashboard') }}" class="ssi-brand">
                <span class="ssi-brand-mark"><i class="fa fa-cubes"></i></span>
                <span class="ssi-brand-text">
                    <span>Smart Stock Inventory</span>
                    <span class="ssi-brand-subtitle">{{ $businessName }}</span>
                </span>
            </a>
            <nav class="ssi-nav">
                @foreach($menuGroups as $group)
                    @php($groupActive = $isActive($group['active']))
                    <details class="ssi-nav-section" @if($groupActive) open @endif>
                        <summary>
                            <i class="{{ $group['icon'] }}"></i>
                            <span>{{ $group['label'] }}</span>
                            <i class="fa fa-chevron-right ssi-chevron"></i>
                        </summary>
                        <div class="ssi-nav-sub">
                            @foreach($group['items'] as $item)
                                <a href="{{ $item['url'] }}" class="{{ $isItemActive($item['active']) ? 'active' : '' }}">
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </nav>
        </aside>

        <div class="ssi-overlay no-print" id="ssi_overlay"></div>

        <main class="ssi-main">
            <header class="ssi-topbar no-print">
                <div class="tw-flex tw-items-center tw-gap-3">
                    <button type="button" class="btn btn-sm btn-primary ssi-mobile-menu" id="ssi_menu_toggle">
                        <i class="fa fa-bars"></i>
                    </button>
                    <a href="{{ route('home') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-home"></i> POS
                    </a>
                    <a href="{{ route('ssi.dashboard') }}" class="btn btn-sm btn-info">
                        <i class="fa fa-dashboard"></i> Dashboard
                    </a>
                </div>
                <div class="ssi-user">
                    <strong>{{ Auth::user()->first_name ?? Auth::user()->username ?? 'User' }}</strong><br>
                    {{ $businessName }}
                </div>
            </header>

            <section class="ssi-page" id="scrollable-container">
                <div class="ssi-page-header">
                    <h1>{{ $pageTitle }}</h1>
                </div>

                <section class="content" id="ssi_app" style="font-size:14px;">
                    <div class="ssi-action-bar btn-group">
                        <a class="btn btn-xs btn-primary" href="{{ route('ssi.count.index') }}">Add</a>
                        <a class="btn btn-xs btn-info" href="{{ route('ssi.count.index') }}">Edit</a>
                        <a class="btn btn-xs btn-success" href="{{ route('ssi.settings.index') }}">Update</a>
                        <a class="btn btn-xs btn-danger" href="{{ route('ssi.count.index') }}">Delete</a>
                        <a class="btn btn-xs btn-warning" href="{{ route('ssi.mismatch.index') }}">Fix</a>
                        <a class="btn btn-xs btn-default" href="{{ route('ssi.fix_logs') }}">Rollback</a>
                        <a class="btn btn-xs btn-default" href="{{ route('ssi.count.export', ['session_id' => request('session_id')]) }}">Export</a>
                        <a class="btn btn-xs btn-default" href="#" onclick="window.print();return false;">Print</a>
                    </div>
                    @if(session('status'))
                        <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }}">{{ session('status.msg') }}</div>
                    @endif
                    @yield('module_content')
                </section>
            </section>
        </main>
    </div>

    @if (in_array(request()->ip(), ['127.0.0.1', '::1']))
        <input type="hidden" id="__is_localhost" value="true">
    @endif
    <input type="hidden" id="__code" value="{{ session('currency')['code'] ?? '' }}">
    <input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] ?? '' }}">
    <input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] ?? ',' }}">
    <input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] ?? '.' }}">
    <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement') }}">
    <input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
    <input type="hidden" id="__quantity_precision" value="{{ session('business.quantity_precision', 2) }}">
    @can('view_export_buttons')
        <input type="hidden" id="view_export_buttons">
    @endcan
    @if (isMobile())
        <input type="hidden" id="__is_mobile">
    @endif
    @if (session('status'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}" data-msg="{{ session('status.msg') }}">
    @endif

    <section class="invoice print_section" id="receipt_section"></section>
    <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

    @include('layouts.partials.javascripts')
    @include('layouts.module-assets')
    <script src="{{ asset('Modules/SmartStockInventory/Resources/assets/js/smart_stock_inventory.js') }}"></script>
    <script>
        $(function() {
            var $sidebar = $('#ssi_sidebar');
            var $overlay = $('#ssi_overlay');
            function closeSidebar() {
                $sidebar.removeClass('is-open');
                $overlay.removeClass('is-open');
            }
            $('#ssi_menu_toggle').on('click', function() {
                $sidebar.toggleClass('is-open');
                $overlay.toggleClass('is-open');
            });
            $overlay.on('click', closeSidebar);
            $('.ssi-nav a').on('click', closeSidebar);
        });
    </script>
    @yield('module_js')
</body>
</html>
