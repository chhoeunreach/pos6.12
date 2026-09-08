@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

<div class="lm-step-card lm-step-card-indigo" id="sectionItems">
    <div class="lm-step-card-header">
        <div class="lm-step-card-title-wrap">
            <span class="lm-step-badge">2</span>
            <div>
                <h3 class="lm-step-title"><i class="fa fa-cubes text-info"></i> {{ $lmText('Purchased Items & Collateral Products', 'ទំនិញបង់រំលស់ ឬទ្រព្យធានា') }}</h3>
                <p class="lm-step-subtitle">{{ $lmText('Type IMEI or serial for instant product lookup.', 'វាយ IMEI ឬស៊េរីដើម្បីទាញយកទំនិញស្វ័យប្រវត្តិ') }}</p>
            </div>
        </div>
        <div class="lm-step-header-actions">
            <button type="button" class="btn btn-primary btn-xs lm-btn-action" id="btnAddItem" style="font-size:11.5px; padding:4px 10px;">
                <i class="fa fa-plus-circle"></i> {{ $lmText('Add Item', 'បន្ថែមទំនិញ') }}
            </button>
        </div>
    </div>

    <div class="lm-step-card-body">
        <div class="table-responsive lm-table-responsive-clean" style="margin-bottom:4px;">
            <table class="table table-bordered lm-items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:30%;">{{ $lmText('Product / Model', 'ឈ្មោះទំនិញ') }} <span class="text-danger">*</span></th>
                        <th style="width:14%;">{{ $lmText('SKU', 'កូដ') }}</th>
                        <th style="width:18%;">{{ $lmText('IMEI / Serial', 'IMEI/ស៊េរី') }}</th>
                        <th style="width:10%; text-align:center;">{{ $lmText('Photo', 'រូប') }}</th>
                        <th style="width:8%; text-align:center;">{{ $lmText('Qty', 'ចំនួន') }}</th>
                        <th style="width:13%; text-align:right;">{{ $lmText('Price', 'តម្លៃ') }}</th>
                        <th style="width:13%; text-align:right;">{{ $lmText('Total', 'សរុប') }}</th>
                        <th style="width:4%; text-align:center;"></th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="lm-table-total-row">
                        <td colspan="6" class="text-right lm-total-label" style="padding:6px 10px;">
                            <strong><i class="fa fa-calculator text-primary"></i> {{ $lmText('Total Price (Principal Base):', 'តម្លៃទំនិញសរុប:') }}</strong>
                        </td>
                        <td class="text-right lm-total-amount" style="padding:6px 10px;">
                            <span id="computedPrincipal" class="lm-badge-total" style="font-size:14px;">0.00</span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="lm-item-hint-strip" style="margin-top:4px; padding:4px 8px; font-size:11px;">
            <i class="fa fa-lightbulb-o text-warning"></i>
            <span>{{ $lmText('Typing 3+ chars in IMEI looks up stock products automatically.', 'បញ្ចូល IMEI/Serial លើសពី ៣ តួ ដើម្បីទាញយកទំនិញស្វ័យប្រវត្តិ') }}</span>
        </div>
    </div>
</div>
