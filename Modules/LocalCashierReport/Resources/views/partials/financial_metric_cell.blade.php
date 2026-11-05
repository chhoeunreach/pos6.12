@php
    $scope = !empty($branch['location_ids']) ? ['location_ids' => $branch['location_ids']] : [];
    $metricSource = ['main_sales' => 'main', 'accessory_sales' => 'accessory', 'service_sales' => 'service'][$metric] ?? null;
    if ($metricSource) $scope['source'] = $metricSource;
    $isCostDetails = $metric === 'cost' && $canViewSales;
    $value = $branch[$metric];
    $text = str_contains($metric, 'margin') ? ($value === null ? '—' : number_format($value, 2).'%') : $fmt($value);
@endphp
<td class="{{ $value !== null && $value < 0 ? 'negative' : '' }}">
    @if($value !== null && ($scope || $isCostDetails || ($metric === 'expenses' && $canViewExpenseDetails)))
        <a class="finance-data-link {{ $isCostDetails ? 'finance-sales-details' : ($metric === 'expenses' && $canViewExpenseDetails ? 'finance-expense-details' : '') }}" @if($isCostDetails) data-cost-details="1" @endif data-metric="{{ $metric }}" href="{{ $isCostDetails ? $costDetailsLink($scope) : ($metric === 'expenses' && $canViewExpenseDetails ? $expenseDetailsLink($scope) : $financeLink($scope)) }}" title="{{ $t($isCostDetails ? 'finance_cost_sales_list' : 'finance_click_filter') }}">{{ $text }}</a>
    @else
        {{ $text }}
    @endif
</td>
