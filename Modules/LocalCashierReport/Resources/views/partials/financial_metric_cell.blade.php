@php
    $scope = !empty($branch['location_ids']) ? ['location_ids' => $branch['location_ids']] : [];
    $metricSource = ['main_sales' => 'main', 'accessory_sales' => 'accessory', 'service_sales' => 'service'][$metric] ?? null;
    if ($metricSource) $scope['source'] = $metricSource;
    $posReportUrl = $metric === 'main_sales' && $canViewSales ? $posReportLink($scope) : null;
    $isCostDetails = $metric === 'cost' && $canViewSales;
    $isRevenueDetails = in_array($metric, ['accessory_sales', 'service_sales']) && $canViewSales && in_array($filters['source'], ['all', $metricSource], true);
    $lineDetailsUrl = $isCostDetails ? $costDetailsLink($scope) : ($isRevenueDetails ? $revenueDetailsLink($scope) : null);
    $value = $branch[$metric];
    $text = str_contains($metric, 'margin') ? ($value === null ? '—' : number_format($value, 2).'%') : $fmt($value);
@endphp
<td class="{{ $value !== null && $value < 0 ? 'negative' : '' }}">
    @if($value !== null && ($scope || $isCostDetails || ($metric === 'expenses' && $canViewExpenseDetails)))
        <a class="finance-data-link {{ $posReportUrl ? 'finance-pos-report-details' : ($lineDetailsUrl ? 'finance-sales-details' : ($metric === 'expenses' && $canViewExpenseDetails ? 'finance-expense-details' : '')) }}" @if($lineDetailsUrl) data-cost-details="1" @endif @if($isRevenueDetails) data-detail-kind="revenue" data-detail-title="{{ $t('finance_'.$metric) }}" @endif data-metric="{{ $metric }}" href="{{ $posReportUrl ?: ($lineDetailsUrl ?: ($metric === 'expenses' && $canViewExpenseDetails ? $expenseDetailsLink($scope) : $financeLink($scope))) }}" title="{{ $t($posReportUrl ? 'finance_stock_sell_report' : ($lineDetailsUrl ? ($isCostDetails ? 'finance_cost_sales_list' : 'finance_'.$metric) : 'finance_click_filter')) }}">{{ $text }}</a>
    @else
        {{ $text }}
    @endif
</td>
