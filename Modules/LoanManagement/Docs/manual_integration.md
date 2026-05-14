# Manual Integration: LoanManagement Entry Link

Do not modify Ultimate POS core automatically.

To open LoanManagement full layout from Ultimate POS sidebar/top nav, add this link manually in your POS menu blade:

```blade
<a href="{{ url('/loan-management') }}">
    <i class="fa fa-credit-card"></i>
    <span>Installment / Loan</span>
</a>
```

Or top navigation button:

```blade
<a href="{{ url('/loan-management') }}" class="btn btn-primary">
    Installment / Loan
</a>
```

Routes:
- `/loan-management` redirects to `/loan-management/dashboard`
- `/loan-management/dashboard` loads LoanManagement dedicated full layout

All LoanManagement pages now extend:

```blade
@extends('loanmanagement::layouts.app')
```
