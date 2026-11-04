@php
    $scope = !empty($branch['location_ids']) ? ['location_ids' => $branch['location_ids']] : [];
    $metricSource = ['main_sales' => 'main', 'accessory_sales' => 'accessory', 'service_sales' => 'service'][$metric] ?? null;
    if ($metricSource) $scope['source'] = $metricSource;
    $value = $branch[$metric];
    $text = str_contains($metric, 'margin') ? ($value === null ? '—' : number_format($value, 2).'%') : $fmt($value);
@endphp
<td class="{{ $value !== null && $value < 0 ? 'negative' : '' }}">
    @if($value !== null && ($scope || ($metric === 'expenses' && $canViewExpenseDetails)))
        <a class="finance-data-link {{ $metric === 'expenses' && $canViewExpenseDetails ? 'finance-expense-details' : '' }}" href="{{ $metric === 'expenses' && $canViewExpenseDetails ? $expenseDetailsLink($scope) : $financeLink($scope) }}" title="{{ $t('finance_click_filter') }}">{{ $text }}</a>
    @else
        {{ $text }}
    @endif
</td>
