@extends('loanmanagement::layouts.app')
@section('title', 'Customer Detail')

@section('content_body')
@php
    $primaryName = trim((string) ($customerRow->khmer_name ?? '')) ?: trim((string) ($customerRow->name ?? ''));
    $englishName = trim((string) ($customerRow->name ?? ''));
    $telegramLinked = !empty($customerRow->telegram_chat_id);
@endphp
<section class="content-header">
    <h1>Customer Detail</h1>
</section>
<section class="content">
    <div class="box box-primary"><div class="box-body">
        <div class="row">
            <div class="col-md-2 col-sm-3">
                <div class="lm-customer-detail-photo">
                    @if(!empty($customerPhotoUrl))
                        <img src="{{ $customerPhotoUrl }}" alt="Customer profile photo">
                    @else
                        <i class="fa fa-user"></i>
                    @endif
                </div>
            </div>
            <div class="col-md-10 col-sm-9">
                <div class="row">
                    <div class="col-md-4"><strong>Code:</strong> {{ $customerRow->customer_code ?? '-' }}</div>
                    <div class="col-md-4"><strong>Name:</strong> {{ $primaryName !== '' ? $primaryName : '-' }}</div>
                    <div class="col-md-4"><strong>English Name:</strong> {{ $englishName !== '' ? $englishName : '-' }}</div>
                    <div class="col-md-4"><strong>Khmer Name:</strong> {{ trim((string) ($customerRow->khmer_name ?? '')) ?: '-' }}</div>
                    <div class="col-md-4"><strong>Phone:</strong> {{ $customerRow->phone ?? '-' }}</div>
                    <div class="col-md-4"><strong>Status:</strong> {{ $customerRow->status ?? '-' }}</div>
                    <div class="col-md-4"><strong>Can Login:</strong> {{ !empty($customerRow->can_login) ? 'Yes' : 'No' }}</div>
                    <div class="col-md-4"><strong>GPS Tracking:</strong> {{ !empty($customerRow->allow_gps_tracking) ? 'Enabled' : 'Disabled' }}</div>
                </div>
                <div class="row" style="margin-top:10px">
                    <div class="col-md-12">
                        <span id="lmTelegramStatus">
                            @if($telegramLinked)
                                <span class="label label-success"><i class="fa fa-telegram"></i> Telegram Connected @if(!empty($customerRow->telegram_username)){{ '(@'.$customerRow->telegram_username.')' }}@endif</span>
                                <button type="button" class="btn btn-default btn-xs" id="lmUnlinkTelegram" style="margin-left:6px">Unlink</button>
                            @else
                                <span class="label label-default"><i class="fa fa-telegram"></i> Telegram Not Connected</span>
                                <button type="button" class="btn btn-info btn-xs" id="lmGenerateTelegramLink" style="margin-left:6px">Connect Telegram</button>
                            @endif
                        </span>
                        <div id="lmTelegramLinkBox" style="display:none;margin-top:8px;padding:10px;border:1px dashed #d1d5db;border-radius:6px;background:#f8fafc">
                            <div style="font-size:12px;color:#64748b;margin-bottom:6px">Send this link to the customer. Valid for a limited time and can only be used once.</div>
                            <input type="text" id="lmTelegramLinkValue" readonly class="form-control input-sm" style="display:inline-block;width:70%">
                            <button type="button" class="btn btn-default btn-sm" id="lmCopyTelegramLink">Copy</button>
                        </div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:6px"><i class="fa fa-arrow-down"></i> Use the Telegram button in the bottom-right corner to open the chat.</div>
                    </div>
                </div>
            </div>
        </div>
    </div></div>

    <div class="box box-default"><div class="box-header"><h3 class="box-title">Loans</h3></div><div class="box-body">
        <table class="table table-bordered"><thead><tr><th>ID</th><th>Loan Number</th><th>Status</th><th>Balance</th></tr></thead><tbody>
            @forelse($loans as $l)<tr><td>{{ $l->id }}</td><td>{{ $l->loan_number ?? '-' }}</td><td>{{ $l->status ?? '-' }}</td><td>{{ $l->balance_amount ?? 0 }}</td></tr>@empty<tr><td colspan="4" class="text-center">No loans</td></tr>@endforelse
        </tbody></table>
    </div></div>

    <div class="box box-default"><div class="box-header"><h3 class="box-title">Payments</h3></div><div class="box-body">
        <table class="table table-bordered"><thead><tr><th>ID</th><th>Receipt</th><th>Amount</th><th>Date</th></tr></thead><tbody>
            @forelse($payments as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->receipt_number ?? '-' }}</td><td>{{ $p->total_paid ?? 0 }}</td><td>{{ $p->paid_date ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center">No payments</td></tr>@endforelse
        </tbody></table>
    </div></div>
</section>
@endsection

@section('loan_js')
<script>
(function($){
    var csrf = '{{ csrf_token() }}';

    $('#lmGenerateTelegramLink').on('click', function(){
        var btn = $(this).prop('disabled', true);
        fetch('{{ route("loan-management.customers.telegram.link", $customerRow->id) }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf}
        }).then(function(r){ return r.json(); }).then(function(resp){
            btn.prop('disabled', false);
            if (resp && resp.link) {
                $('#lmTelegramLinkValue').val(resp.link);
                $('#lmTelegramLinkBox').show();
            }
        }).catch(function(){ btn.prop('disabled', false); });
    });

    $('#lmCopyTelegramLink').on('click', function(){
        var input = document.getElementById('lmTelegramLinkValue');
        input.select();
        document.execCommand('copy');
    });

    $('#lmUnlinkTelegram').on('click', function(){
        if (!confirm('Unlink this customer\'s Telegram account?')) return;
        fetch('{{ route("loan-management.customers.telegram.unlink", $customerRow->id) }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf}
        }).then(function(){ window.location.reload(); }).catch(function(){});
    });
})(jQuery);
</script>
@endsection
