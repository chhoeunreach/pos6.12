@extends('layouts.app')

@section('title', 'Income Entry #' . $income->ref_no)

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => 'Income Transaction #' . $income->ref_no])

    <div class="row">
        <!-- Main Details -->
        <div class="col-md-8">
            <div class="box box-solid tw-rounded-xl tw-shadow-sm">
                <div class="box-header with-border tw-flex tw-justify-between">
                    <h3 class="box-title tw-font-bold">Transaction Details</h3>
                    <span class="badge {{ $income->approval_status === 'approved' ? 'bg-green' : ($income->approval_status === 'pending' ? 'bg-orange' : 'bg-gray') }}">
                        {{ strtoupper($income->approval_status) }}
                    </span>
                </div>
                <div class="box-body tw-p-6">
                    <table class="table table-bordered">
                        <tr>
                            <th class="tw-bg-gray-50 tw-w-1/3">Reference Number</th>
                            <td class="tw-font-mono tw-font-bold">{{ $income->ref_no }}</td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Transaction Date</th>
                            <td>{{ $income->transaction_date->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Branch Location</th>
                            <td>{{ $income->location ? $income->location->name : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Category</th>
                            <td>{{ $income->category ? $income->category->display_name : 'General Income' }}</td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Deposit Account</th>
                            <td>{{ $income->account ? $income->account->account_name : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Payment Method</th>
                            <td><span class="label label-info">{{ strtoupper($income->payment_method) }}</span></td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Amount</th>
                            <td class="tw-text-xl tw-font-bold tw-text-green-700">
                                ${{ number_format($income->amount_in_base_currency, 2) }}
                                @if ($income->currency === 'KHR')
                                    <span class="tw-text-sm tw-text-gray-500">({{ number_format($income->amount) }} ៛)</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Description</th>
                            <td>{{ $income->description }}</td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Operating Revenue?</th>
                            <td>
                                @if ($income->is_operating)
                                    <span class="text-success"><i class="fa fa-check-circle"></i> Yes, recognized in P&L Profit</span>
                                @else
                                    <span class="text-muted"><i class="fa fa-info-circle"></i> No, Non-Operating / Balance Sheet Movement</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="tw-bg-gray-50">Created By</th>
                            <td>{{ $income->creator ? $income->creator->user_full_name : 'System' }} on {{ $income->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    </table>

                    @if ($income->attachments->count())
                        <div class="tw-mt-6">
                            <h4 class="tw-font-bold tw-text-sm tw-text-gray-700 tw-mb-2">Attached Receipt / Documents:</h4>
                            <div class="tw-flex tw-gap-4">
                                @foreach ($income->attachments as $att)
                                    <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="tw-p-3 tw-border tw-rounded-lg tw-flex tw-items-center tw-gap-2 hover:tw-bg-gray-50">
                                        <i class="fa fa-file-text-o tw-text-blue-600"></i>
                                        <span class="tw-text-xs tw-font-medium">{{ $att->file_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Approval History & Actions -->
        <div class="col-md-4">
            <div class="box box-solid tw-rounded-xl tw-shadow-sm">
                <div class="box-header with-border">
                    <h3 class="box-title tw-font-bold">Workflow Actions</h3>
                </div>
                <div class="box-body tw-p-4">
                    @if ($income->approval_status === 'draft')
                        <form action="{{ route('daily-financial.approvals.action', $income->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="action" value="submit" />
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa fa-paper-plane"></i> Submit for Review
                            </button>
                        </form>
                    @elseif ($income->approval_status === 'pending')
                        <form action="{{ route('daily-financial.approvals.action', $income->id) }}" method="POST" class="tw-mb-2">
                            @csrf
                            <input type="hidden" name="action" value="review" />
                            <button type="submit" class="btn btn-warning btn-block">
                                <i class="fa fa-eye"></i> Mark as Reviewed (Accountant)
                            </button>
                        </form>
                    @elseif ($income->approval_status === 'reviewed')
                        <form action="{{ route('daily-financial.approvals.action', $income->id) }}" method="POST" class="tw-mb-2">
                            @csrf
                            <input type="hidden" name="action" value="approve" />
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fa fa-check"></i> Approve (Branch Manager)
                            </button>
                        </form>
                    @endif

                    @if (in_array($income->approval_status, ['pending', 'reviewed']))
                        <button type="button" class="btn btn-danger btn-block tw-mt-2" data-toggle="modal" data-target="#rejectModal">
                            <i class="fa fa-ban"></i> Reject Transaction
                        </button>
                    @endif

                    @if ($income->approval_status === 'approved' && ! $income->is_locked)
                        <button type="button" class="btn btn-default btn-block tw-mt-4 tw-text-red-600" data-toggle="modal" data-target="#reverseModal">
                            <i class="fa fa-undo"></i> Reverse Transaction (Correction)
                        </button>
                    @endif
                </div>
            </div>

            <!-- Approval Timeline -->
            <div class="box box-solid tw-rounded-xl tw-shadow-sm">
                <div class="box-header with-border">
                    <h3 class="box-title tw-font-bold">Approval History</h3>
                </div>
                <div class="box-body tw-p-4">
                    <ul class="timeline">
                        @forelse ($income->approvals as $appr)
                            <li>
                                <i class="fa fa-check bg-blue"></i>
                                <div class="timeline-item">
                                    <span class="time"><i class="fa fa-clock-o"></i> {{ $appr->created_at->diffForHumans() }}</span>
                                    <h3 class="timeline-header tw-text-xs">
                                        <strong>{{ $appr->actor ? $appr->actor->user_full_name : 'User' }}</strong>: {{ strtoupper($appr->action) }}
                                    </h3>
                                    @if ($appr->comments)
                                        <div class="timeline-body tw-text-xs">{{ $appr->comments }}</div>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <p class="tw-text-xs tw-text-gray-400">No workflow actions recorded.</p>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal">
    <div class="modal-dialog">
        <form action="{{ route('daily-financial.approvals.action', $income->id) }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="reject" />
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Reject Transaction</h4>
                </div>
                <div class="modal-body">
                    <label>Reason for rejection <span class="text-danger">*</span></label>
                    <textarea name="comments" class="form-control" rows="3" required placeholder="Explain why this entry is rejected..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reversal Modal -->
<div class="modal fade" id="reverseModal">
    <div class="modal-dialog">
        <form action="{{ route('daily-financial.approvals.action', $income->id) }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="reverse" />
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Reverse Approved Transaction</h4>
                </div>
                <div class="modal-body">
                    <p class="text-danger">Approved records cannot be deleted. Reversal creates an offsetting negative financial entry with full audit trail.</p>
                    <label>Reason for reversal <span class="text-danger">*</span></label>
                    <textarea name="comments" class="form-control" rows="3" required placeholder="Explain why this transaction is being reversed..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Execute Reversal</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

