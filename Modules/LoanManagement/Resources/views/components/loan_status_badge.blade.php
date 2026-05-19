@php
    use Modules\LoanManagement\Support\LoanCollectionConstants;

    $status = strtolower((string) ($status ?? 'draft'));
    $risk = strtolower((string) ($risk ?? ''));
    $map = [
        'draft' => 'default',
        'pending' => 'warning',
        'approved' => 'info',
        'active' => 'primary',
        'due_today' => 'warning',
        'partial_payment' => 'warning',
        'overdue' => 'warning',
        'ptp' => 'warning',
        'broken_ptp' => 'danger',
        'field_visit_required' => 'warning',
        'skip_customer' => 'default',
        'delinquent' => 'danger',
        'recovery' => 'warning',
        'debt_collection' => 'danger',
        'repossession' => 'warning',
        'legal' => 'danger',
        'blacklisted' => 'default',
        'late' => 'danger',
        'defaulted' => 'danger',
        'completed' => 'success',
        'closed' => 'success',
        'cancelled' => 'default',
        'written_off' => 'default',
    ];
    $color = in_array($risk, ['fraud_risk', 'critical'], true) ? 'default' : ($map[$status] ?? 'default');
    $label = LoanCollectionConstants::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
@endphp
<span class="label label-{{ $color }}">{{ $label }}</span>
