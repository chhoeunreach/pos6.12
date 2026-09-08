@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

<div class="lm-step-card lm-step-card-cyan" id="sectionSchedule">
    <div class="lm-step-card-header" id="headerScheduleCard" style="cursor:pointer;">
        <div class="lm-step-card-title-wrap">
            <span class="lm-step-badge lm-step-badge-icon" style="background:#0891b2;"><i class="fa fa-table"></i></span>
            <div>
                <h3 class="lm-step-title"><i class="fa fa-calendar-check-o text-info"></i> {{ $lmText('Amortization Schedule Preview', 'កាលវិភាគបង់ប្រាក់សាកល្បង') }}</h3>
                <p class="lm-step-subtitle">{{ $lmText('Breakdown of installment periods, principal, and interest.', 'កាលវិភាគបង់ប្រាក់ ប្រាក់ដើម និងការប្រាក់តាមវគ្គ') }}</p>
            </div>
        </div>
        <div class="lm-step-header-actions" style="display:flex; align-items:center; gap:6px;">
            <button type="button" class="btn btn-info btn-xs lm-btn-action" id="btnPreviewScheduleTop" style="font-size:11px; padding:3px 8px;">
                <i class="fa fa-refresh"></i> {{ $lmText('Calculate', 'គណនា') }}
            </button>
            <span style="font-size:11px; color:#64748b;"><i class="fa fa-chevron-down" id="lmScheduleChevron"></i></span>
        </div>
    </div>

    <div class="lm-step-card-body" id="bodyScheduleCard">
        <div class="table-responsive lm-table-responsive-clean" style="margin-bottom:0; max-height:260px; overflow-y:auto;">
            <table class="table table-bordered table-hover lm-schedule-table" id="schedulePreviewTable">
                <thead>
                    <tr>
                        <th style="width:8%; text-align:center; padding:4px 6px; font-size:11px;">#</th>
                        <th style="width:20%; padding:4px 6px; font-size:11px;">{{ $lmText('Due Date', 'ថ្ងៃត្រូវបង់') }}</th>
                        <th style="width:18%; text-align:right; padding:4px 6px; font-size:11px;">{{ $lmText('Principal', 'ប្រាក់ដើម') }}</th>
                        <th style="width:18%; text-align:right; padding:4px 6px; font-size:11px;">{{ $lmText('Interest', 'ការប្រាក់') }}</th>
                        <th style="width:18%; text-align:right; padding:4px 6px; font-size:11px;">{{ $lmText('Payment', 'សរុប') }}</th>
                        <th style="width:18%; text-align:right; padding:4px 6px; font-size:11px;">{{ $lmText('Balance', 'សមតុល្យ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="lm-schedule-empty-state">
                        <td colspan="6" class="text-center text-muted" style="padding: 10px 14px;">
                            <i class="fa fa-calculator" style="font-size: 16px; color: #94a3b8; margin-right: 6px;"></i>
                            <span style="font-size:11.5px;">{{ $lmText('Click "Calculate" to preview repayment schedule breakdown.', 'ចុច "គណនា" ដើម្បីមើលតារាងកាលវិភាគបង់ប្រាក់') }}</span>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="lm-schedule-tfoot-row">
                        <th colspan="2" class="text-right lm-total-label" style="padding:4px 6px; font-size:11px;">{{ $lmText('Totals:', 'សរុប:') }}</th>
                        <th class="text-right lm-stat-num" style="padding:4px 6px; font-size:11px;">0.00</th>
                        <th class="text-right lm-stat-num" style="padding:4px 6px; font-size:11px;">0.00</th>
                        <th class="text-right lm-stat-num" style="padding:4px 6px; font-size:11px;">0.00</th>
                        <th class="text-right lm-stat-num" style="padding:4px 6px; font-size:11px;">0.00</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
