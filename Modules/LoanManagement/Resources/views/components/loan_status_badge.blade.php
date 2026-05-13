@php
    $status = strtolower((string) ($status ?? 'draft'));
    $map = [
        'draft' => 'default',
        'pending' => 'warning',
        'approved' => 'info',
        'active' => 'primary',
        'late' => 'danger',
        'defaulted' => 'danger',
        'completed' => 'success',
        'cancelled' => 'default',
    ];
    $color = $map[$status] ?? 'default';
@endphp
<span class="label label-{{ $color }}">{{ ucfirst($status) }}</span>

