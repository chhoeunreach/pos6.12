@extends('loanmanagement::layouts.app')
@section('title', 'Loan Quotations & Proposals')
@section('hide_breadcrumb', '1')

@section('loan_css')
<style>
    .qtn-shell {
        display: flex;
        flex-direction: column;
        gap: 20px;
        font-family: 'Kantumruy Pro', -apple-system, sans-serif;
    }
    .qtn-hero {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 22px 26px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        border-left: 4px solid var(--lm-primary, #2563eb);
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .qtn-hero h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .qtn-hero p {
        margin: 4px 0 0 0;
        color: #64748b;
        font-size: 13px;
    }
    .qtn-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }
    .qtn-stat-card {
        background: #fff;
        padding: 16px 20px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .qtn-stat-card span {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .qtn-stat-card h3 {
        margin: 6px 0 0 0;
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
    }
    .qtn-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .qtn-filters {
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }
    .qtn-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .qtn-badge-draft { background: #f1f5f9; color: #475569; }
    .qtn-badge-sent { background: #e0f2fe; color: #0284c7; }
    .qtn-badge-accepted { background: #dcfce7; color: #16a34a; }
    .qtn-badge-converted { background: #f3e8ff; color: #9333ea; font-weight: 800; }
    .qtn-badge-rejected { background: #fee2e2; color: #dc2626; }
    .qtn-badge-cancelled { background: #fef2f2; color: #991b1b; }
    .table-qtn th {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-qtn td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 13px;
        border-bottom: 1px solid #f1f5f9;
    }
</style>
@endsection

@section('content_body')
<div class="qtn-shell">
    <div class="qtn-hero">
        <div>
            <h1><i class="fa fa-file-text-o text-primary"></i> Quotations & Installment Proposals / សម្រង់តម្លៃកម្ចី</h1>
            <p>Generate, manage, and convert customer installment quotations to active loans with a single click.</p>
        </div>
        <div>
            @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
            <a href="{{ route('loan-management.quotations.create') }}" class="btn btn-primary" style="border-radius:8px;font-weight:600;padding:8px 18px;">
                <i class="fa fa-plus"></i> New Quotation / បង្កើតសម្រង់តម្លៃ
            </a>
            @endif
        </div>
    </div>

    <!-- STATS SUMMARY -->
    <div class="qtn-stats">
        <div class="qtn-stat-card">
            <span>Total Quotations</span>
            <h3>{{ number_format($summary['total']) }}</h3>
        </div>
        <div class="qtn-stat-card">
            <span>Drafts</span>
            <h3 class="text-secondary">{{ number_format($summary['draft']) }}</h3>
        </div>
        <div class="qtn-stat-card">
            <span>Sent</span>
            <h3 class="text-info">{{ number_format($summary['sent']) }}</h3>
        </div>
        <div class="qtn-stat-card">
            <span>Accepted</span>
            <h3 class="text-success">{{ number_format($summary['accepted']) }}</h3>
        </div>
        <div class="qtn-stat-card">
            <span>Converted to Loan</span>
            <h3 style="color:#9333ea;">{{ number_format($summary['converted']) }}</h3>
        </div>
        <div class="qtn-stat-card">
            <span>Total Value</span>
            <h3 class="text-primary">${{ number_format($summary['total_amount'], 2) }}</h3>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="qtn-card">
        <form method="GET" action="{{ route('loan-management.quotations.index') }}" class="qtn-filters">
            <div style="flex:1;min-width:200px;">
                <input type="text" name="search" class="form-control input-sm" placeholder="Search Quotation #, Customer, Phone..." value="{{ request('search') }}">
            </div>
            <div>
                <select name="status" class="form-control input-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="converted" {{ request('status') === 'converted' ? 'selected' : '' }}>Converted to Loan</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <input type="date" name="date_from" class="form-control input-sm" value="{{ request('date_from') }}" placeholder="From Date">
            </div>
            <div>
                <input type="date" name="date_to" class="form-control input-sm" value="{{ request('date_to') }}" placeholder="To Date">
            </div>
            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-filter"></i> Filter</button>
            <a href="{{ route('loan-management.quotations.index') }}" class="btn btn-link btn-sm text-muted">Clear</a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-qtn" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>QUOTATION #</th>
                        <th>DATE</th>
                        <th>CUSTOMER</th>
                        <th>LOAN AMOUNT</th>
                        <th>DOWN PAYMENT</th>
                        <th>TERM / RATE</th>
                        <th>INSTALLMENT</th>
                        <th>STATUS</th>
                        <th style="text-align:right;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quotations as $q)
                        <tr>
                            <td>
                                <a href="{{ route('loan-management.quotations.show', $q->id) }}" style="font-weight:700;color:var(--lm-primary);">
                                    {{ $q->quotation_no }}
                                </a>
                            </td>
                            <td>
                                {{ $q->quotation_date ? $q->quotation_date->format('M d, Y') : '-' }}
                                @if($q->valid_until)
                                    <br><small class="text-muted">Valid: {{ $q->valid_until->format('M d, Y') }}</small>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $q->customer_name_snapshot }}</strong>
                                <br><small class="text-muted"><i class="fa fa-phone"></i> {{ $q->customer_phone_snapshot }}</small>
                            </td>
                            <td>
                                <strong>${{ number_format($q->loan_amount, 2) }}</strong>
                                <br><small class="text-muted">Total: ${{ number_format($q->total_amount, 2) }}</small>
                            </td>
                            <td>${{ number_format($q->down_payment, 2) }}</td>
                            <td>
                                {{ $q->duration_months }} {{ ucfirst($q->payment_frequency) }}
                                <br><small class="text-muted">{{ number_format($q->interest_rate, 2) }}% / mo</small>
                            </td>
                            <td>
                                <strong class="text-success">${{ number_format($q->installment_amount, 2) }}</strong>
                            </td>
                            <td>
                                <span class="qtn-badge qtn-badge-{{ $q->status }}">{{ ucfirst($q->status) }}</span>
                                @if($q->status === 'converted' && $q->converted_loan_id)
                                    <br><small><a href="{{ route('loan-management.loans.view', $q->converted_loan_id) }}" class="text-primary"><i class="fa fa-external-link"></i> View Loan</a></small>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div class="btn-group">
                                    <a href="{{ route('loan-management.quotations.show', $q->id) }}" class="btn btn-default btn-xs" title="View Details">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('loan-management.quotations.print', $q->id) }}" target="_blank" class="btn btn-default btn-xs" title="Print Quotation">
                                        <i class="fa fa-print"></i>
                                    </a>
                                    @if($q->status !== 'converted' && !$q->converted_loan_id)
                                        @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.edit|loan_management.edit'))
                                            <a href="{{ route('loan-management.quotations.edit', $q->id) }}" class="btn btn-default btn-xs" title="Edit Quotation" aria-label="Edit Quotation"><i class="fa fa-pencil"></i></a>
                                        @endif
                                        @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.delete|loan_management.delete'))
                                            <form method="POST" action="{{ route('loan-management.quotations.destroy', $q->id) }}" style="display:inline;" onsubmit="return confirm('Delete this quotation?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" title="Delete Quotation" aria-label="Delete Quotation"><i class="fa fa-trash"></i></button>
                                            </form>
                                        @endif
                                        @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
                                        <form method="POST" action="{{ route('loan-management.quotations.convert', $q->id) }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to convert this quotation into an Active Loan?');">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-xs" title="Convert to Loan">
                                                <i class="fa fa-check-circle"></i> Convert
                                            </button>
                                        </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">
                                <i class="fa fa-inbox fa-3x" style="opacity:0.4;display:block;margin-bottom:10px;"></i>
                                No quotations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($quotations->hasPages())
            <div style="padding:16px 20px;border-top:1px solid #f1f5f9;">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
