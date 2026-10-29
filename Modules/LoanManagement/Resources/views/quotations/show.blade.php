@extends('loanmanagement::layouts.app')
@section('title', 'Quotation ' . $quotation->quotation_no)
@section('hide_breadcrumb', '1')

@section('loan_css')
<style>
    .qtn-show-shell {
        font-family: 'Kantumruy Pro', -apple-system, sans-serif;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .qtn-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        padding: 24px;
    }
    .qtn-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 20px 24px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .qtn-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .qtn-badge-draft { background: #f1f5f9; color: #475569; }
    .qtn-badge-sent { background: #e0f2fe; color: #0284c7; }
    .qtn-badge-accepted { background: #dcfce7; color: #16a34a; }
    .qtn-badge-converted { background: #f3e8ff; color: #9333ea; font-weight: 800; }
    .qtn-badge-rejected { background: #fee2e2; color: #dc2626; }
    .qtn-summary-metric {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 18px;
    }
    .qtn-summary-metric span {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }
    .qtn-summary-metric h3 {
        margin: 6px 0 0 0;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
    }
</style>
@endsection

@section('content_body')
<div class="qtn-show-shell">
    <!-- TOP ACTION BAR -->
    <div class="qtn-header">
        <div>
            <div style="display:flex;align-items:center;gap:12px;">
                <h2 style="margin:0;font-size:22px;font-weight:800;color:#0f172a;">
                    {{ $quotation->quotation_no }}
                </h2>
                <span class="qtn-badge qtn-badge-{{ $quotation->status }}">{{ ucfirst($quotation->status) }}</span>
            </div>
            <p style="margin:4px 0 0 0;color:#64748b;font-size:13px;">
                Date: <strong>{{ $quotation->quotation_date ? $quotation->quotation_date->format('M d, Y') : '-' }}</strong> |
                Valid Until: <strong>{{ $quotation->valid_until ? $quotation->valid_until->format('M d, Y') : 'No Expiry' }}</strong>
            </p>
        </div>

        <div style="display:flex;gap:8px;align-items:center;">
            <a href="{{ route('loan-management.quotations.print', $quotation->id) }}" target="_blank" class="btn btn-default">
                <i class="fa fa-print"></i> Print / ព្រីន
            </a>

            @if($quotation->status !== 'converted' && !$quotation->converted_loan_id)
                @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.edit|loan_management.edit'))
                    <a href="{{ route('loan-management.quotations.edit', $quotation->id) }}" class="btn btn-default" title="Edit Quotation"><i class="fa fa-pencil"></i> Edit</a>
                @endif
                @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.delete|loan_management.delete'))
                    <form method="POST" action="{{ route('loan-management.quotations.destroy', $quotation->id) }}" style="display:inline;" onsubmit="return confirm('Delete this quotation?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger" title="Delete Quotation"><i class="fa fa-trash"></i> Delete</button>
                    </form>
                @endif
                @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
                <a href="{{ route('loan-management.loans.create', ['quotation_id' => $quotation->id]) }}" class="btn btn-default" style="font-weight:600;" title="Open and edit as a full loan application">
                    <i class="fa fa-pencil"></i> Open in Application Form
                </a>

                <form method="POST" action="{{ route('loan-management.quotations.convert', $quotation->id) }}" onsubmit="return confirm('Are you sure you want to convert this quotation to an Active Loan?');" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-success" style="font-weight:700;">
                        <i class="fa fa-check-circle"></i> Convert to Active Loan / បម្លែងជាកម្ចី
                    </button>
                </form>
                @endif
            @else
                <a href="{{ route('loan-management.loans.view', $quotation->converted_loan_id) }}" class="btn btn-primary" style="font-weight:700;">
                    <i class="fa fa-external-link"></i> View Active Loan #{{ $quotation->convertedLoan->loan_number ?? '' }}
                </a>
            @endif

            @if(\Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.create|loan_management.create'))
            <form method="POST" action="{{ route('loan-management.quotations.duplicate', $quotation->id) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-default" title="Duplicate Quotation">
                    <i class="fa fa-copy"></i> Duplicate
                </button>
            </form>
            @endif

            <a href="{{ route('loan-management.quotations.index') }}" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- SUMMARY METRICS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;">
        <div class="qtn-summary-metric">
            <span>Total Value</span>
            <h3>${{ number_format($quotation->total_amount, 2) }}</h3>
        </div>
        <div class="qtn-summary-metric">
            <span>Down Payment</span>
            <h3 class="text-secondary">${{ number_format($quotation->down_payment, 2) }}</h3>
        </div>
        <div class="qtn-summary-metric">
            <span>Financed Loan Amount</span>
            <h3 class="text-primary">${{ number_format($quotation->loan_amount, 2) }}</h3>
        </div>
        <div class="qtn-summary-metric">
            <span>Interest Rate</span>
            <h3>{{ number_format($quotation->interest_rate, 2) }}% / mo</h3>
        </div>
        <div class="qtn-summary-metric">
            <span>Installment / Month</span>
            <h3 class="text-success">${{ number_format($quotation->installment_amount, 2) }}</h3>
        </div>
        <div class="qtn-summary-metric">
            <span>Total Payable</span>
            <h3 style="color:#9333ea;">${{ number_format($quotation->total_payable, 2) }}</h3>
        </div>
    </div>

    <div class="row">
        <!-- CUSTOMER & DETAILS -->
        <div class="col-md-5">
            <div class="qtn-card" style="margin-bottom:20px;">
                <h4 style="margin:0 0 14px 0;font-weight:700;font-size:15px;color:#0f172a;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">
                    <i class="fa fa-user text-primary"></i> Customer Details
                </h4>
                <table class="table table-condensed" style="margin-bottom:0;font-size:13px;">
                    <tr>
                        <td style="width:130px;color:#64748b;">Customer Name:</td>
                        <td><strong>{{ $quotation->customer_name_snapshot }}</strong></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b;">Phone Number:</td>
                        <td><a href="tel:{{ $quotation->customer_phone_snapshot }}">{{ $quotation->customer_phone_snapshot }}</a></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b;">Address:</td>
                        <td>{{ $quotation->customer_address_snapshot ?: 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="color:#64748b;">Branch / Location:</td>
                        <td>{{ $quotation->location_name_snapshot ?: ($quotation->location->name ?? 'Head Office') }}</td>
                    </tr>
                    <tr>
                        <td style="color:#64748b;">Interest Method:</td>
                        <td>{{ $quotation->interest_type === 'flat_rate' ? 'Flat Rate (ការប្រាក់ថេរ)' : 'Declining Balance (ការប្រាក់ថយចុះ)' }}</td>
                    </tr>
                    <tr>
                        <td style="color:#64748b;">Repayment Frequency:</td>
                        <td>{{ ucfirst($quotation->payment_frequency) }} ({{ $quotation->duration_months }} cycles)</td>
                    </tr>
                    <tr>
                        <td style="color:#64748b;">First Payment Due:</td>
                        <td><strong>{{ $quotation->first_due_date ? $quotation->first_due_date->format('M d, Y') : '-' }}</strong></td>
                    </tr>
                </table>

                <!-- STATUS CHANGE FORM -->
                @if($quotation->status !== 'converted' && !$quotation->converted_loan_id && \Modules\LoanManagement\Helpers\LoanMenuHelper::loanUserCan('loan_management.loans.edit|loan_management.edit'))
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9;">
                        <form method="POST" action="{{ route('loan-management.quotations.status', $quotation->id) }}" style="display:flex;gap:8px;align-items:center;">
                            @csrf
                            <label style="margin:0;font-size:12px;color:#64748b;">Change Status:</label>
                            <select name="status" class="form-control input-sm" style="flex:1;">
                                <option value="draft" {{ $quotation->status === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="sent" {{ $quotation->status === 'sent' ? 'selected' : '' }}>Sent to Client</option>
                                <option value="accepted" {{ $quotation->status === 'accepted' ? 'selected' : '' }}>Accepted</option>
                                <option value="rejected" {{ $quotation->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="cancelled" {{ $quotation->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            <button type="submit" class="btn btn-default btn-sm">Update</button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- ITEMS TABLE -->
            <div class="qtn-card">
                <h4 style="margin:0 0 14px 0;font-weight:700;font-size:15px;color:#0f172a;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">
                    <i class="fa fa-cubes text-primary"></i> Quoted Products / Items
                </h4>
                <table class="table table-bordered" style="font-size:13px;margin-bottom:0;">
                    <thead>
                        <tr style="background:#f8fafc;font-size:12px;">
                            <th>Item Name</th>
                            <th style="width:60px;text-align:center;">Qty</th>
                            <th style="width:90px;text-align:right;">Price</th>
                            <th style="width:100px;text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotation->items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->product_name_snapshot }}</strong>
                                    @if($item->sku_snapshot)
                                        <br><small class="text-muted">SKU: {{ $item->sku_snapshot }}</small>
                                    @endif
                                </td>
                                <td style="text-align:center;">{{ number_format($item->quantity) }}</td>
                                <td style="text-align:right;">${{ number_format($item->unit_price, 2) }}</td>
                                <td style="text-align:right;"><strong>${{ number_format($item->line_total, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Direct Loan / No specific items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AMORTIZATION SCHEDULE TABLE -->
        <div class="col-md-7">
            <div class="qtn-card">
                <h4 style="margin:0 0 14px 0;font-weight:700;font-size:15px;color:#0f172a;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">
                    <i class="fa fa-calendar-check-o text-primary"></i> Estimated Repayment Schedule / កាលវិភាគបង់ប្រាក់
                </h4>

                <div class="table-responsive" style="max-height:550px;overflow-y:auto;">
                    <table class="table table-striped table-hover" style="font-size:13px;margin-bottom:0;">
                        <thead>
                            <tr style="background:#f8fafc;font-size:12px;">
                                <th style="width:50px;">#</th>
                                <th>Due Date</th>
                                <th style="text-align:right;">Principal</th>
                                <th style="text-align:right;">Interest</th>
                                <th style="text-align:right;">Installment</th>
                                <th style="text-align:right;">Remaining Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quotation->schedules as $sched)
                                <tr>
                                    <td><strong>{{ $sched->installment_no }}</strong></td>
                                    <td>{{ $sched->due_date ? $sched->due_date->format('M d, Y') : '-' }}</td>
                                    <td style="text-align:right;">${{ number_format($sched->principal_amount, 2) }}</td>
                                    <td style="text-align:right;">${{ number_format($sched->interest_amount, 2) }}</td>
                                    <td style="text-align:right;color:#16a34a;font-weight:700;">${{ number_format($sched->schedule_amount, 2) }}</td>
                                    <td style="text-align:right;">${{ number_format($sched->balance_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No schedule generated.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:#f1f5f9;font-weight:800;">
                                <td colspan="2">TOTAL</td>
                                <td style="text-align:right;">${{ number_format($quotation->loan_amount, 2) }}</td>
                                <td style="text-align:right;">${{ number_format($quotation->total_interest, 2) }}</td>
                                <td style="text-align:right;color:#16a34a;">${{ number_format($quotation->total_payable, 2) }}</td>
                                <td style="text-align:right;">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($quotation->terms)
                    <div style="margin-top:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;">
                        <strong style="font-size:12px;color:#475569;">Terms & Notes:</strong>
                        <p style="margin:4px 0 0 0;font-size:12px;color:#64748b;white-space:pre-line;">{{ $quotation->terms }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
