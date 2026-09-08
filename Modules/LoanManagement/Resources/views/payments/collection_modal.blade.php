@php
    $loanNumber = $loan->loan_number ?? ('Installment #'.$loan->id);
    $customerName = trim((string) ($loan->customer_name_snapshot ?? '')) ?: '-';
    $addPaymentUrl = route('loan-management.loans.payment.create', [
        'loan' => $loan->id,
        'return_to' => route('loan-management.dashboard'),
    ]);
@endphp

<div class="modal-dialog modal-lg lm-collection-modal" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ lm_label('messages.close', 'Close', 'បិទ') }}">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title"><i class="fa fa-calendar-check-o"></i> Payment Collection</h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-sm-4">
                    <div class="well well-sm">
                        <strong>Installment #:</strong> {{ $loanNumber }}<br>
                        <strong>Customer:</strong> {{ $customerName }}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="well well-sm">
                        <strong>Records:</strong> {{ number_format((int) $summary['count']) }}<br>
                        <strong>Total:</strong> {{ number_format((float) $summary['amount'], 2) }}
                    </div>
                </div>
                <div class="col-sm-4 text-right">
                    <button type="button"
                            class="btn btn-success btn-modal"
                            data-href="{{ $addPaymentUrl }}"
                            data-container=".view_modal">
                        <i class="fa fa-plus-circle"></i> Add Collection
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-condensed table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Receipt</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->paid_date ?? '-' }}</td>
                                <td>{{ $payment->receipt_number ?? ('Payment #'.$payment->id) }}</td>
                                <td>{{ $payment->payment_method ?? '-' }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $payment->status ?? 'confirmed')) }}</td>
                                <td class="text-right">{{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                                <td class="text-center">
                                    <button type="button"
                                            class="btn btn-xs btn-primary js-payment-edit-frame"
                                            data-title="Edit Collection Payment"
                                            data-url="{{ route('loan-management.payments.edit', ['payment' => $payment->id, 'return_to' => route('loan-management.dashboard')]) }}">
                                        <i class="fa fa-pencil"></i> Edit
                                    </button>
                                    <button type="button"
                                            class="btn btn-xs btn-danger js-payment-delete"
                                            data-url="{{ route('loan-management.payments.destroy', ['payment' => $payment->id, 'return_to' => route('loan-management.dashboard')]) }}">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No collection payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function($) {
    $('.view_modal').off('click.loanPaymentCollectionEdit').on('click.loanPaymentCollectionEdit', '.js-payment-edit-frame', function(event) {
        event.preventDefault();

        var url = $(this).data('url');
        var title = $(this).data('title') || 'Edit Payment';

        if (!url) {
            return;
        }

        if (url.indexOf('_lm_modal=1') === -1) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + '_lm_modal=1';
        }

        $('.view_modal').html(
            '<div class="modal-dialog modal-xl lm-dashboard-iframe-modal" role="document" style="width:96%;max-width:1180px;">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                        '<h4 class="modal-title">' + $('<div>').text(title).html() + '</h4>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:0;height:86vh;">' +
                        '<iframe src="' + $('<div>').text(url).html() + '" style="width:100%;height:100%;border:0;" title="' + $('<div>').text(title).html() + '"></iframe>' +
                    '</div>' +
                '</div>' +
            '</div>'
        ).modal('show');
    });

    $('.view_modal').off('click.loanPaymentCollectionDelete').on('click.loanPaymentCollectionDelete', '.js-payment-delete', function(event) {
        event.preventDefault();

        if (!confirm('Delete this payment? This will update loan totals.')) {
            return;
        }

        var $button = $(this);
        var url = $button.data('url');

        $button.prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: {
                _method: 'DELETE',
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(res) {
                if (window.toastr) {
                    toastr.success(res.message || 'Payment deleted successfully.');
                }

                $button.closest('tr').remove();
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete payment.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
})(jQuery);
</script>
