@php
    $businessSettings = \Modules\LoanManagement\Services\BusinessSettingsService::get();
@endphp

<footer class="lm-footer">
    <div>{{ $businessSettings['system_name'] }} Workspace</div>
    <div>Copyright &copy; 2026 All rights reserved. from rvstechsolution.com</div>
</footer>
