@extends('layouts.guest')
@section('title', $title)
@section('content')

<div class="container">
    <div class="spacer"></div>
    <div class="row">
        <div class="col-md-12 text-right mb-12" >
            @if(!empty($payment_link))
                <a href="{{$payment_link}}" class="btn btn-info no-print" style="margin-right: 20px;"><i class="fas fa-money-check-alt" title="@lang('lang_v1.pay')"></i> @lang('lang_v1.pay')
                </a>
            @endif
            @auth
                @can('loan_management.create_from_sell')
                    @if(!empty($transaction) && !empty($transaction->id))
                        <a href="{{ url('/loan-management/loans/sell/'.$transaction->id.'/clone') }}"
                           class="btn btn-warning no-print convert-to-installment-invoice"
                           data-check-url="{{ url('/loan-management/loans/sell/'.$transaction->id.'/check-duplicate') }}"
                           data-clone-url="{{ url('/loan-management/loans/sell/'.$transaction->id.'/clone') }}"
                           style="margin-right: 10px;">
                            <i class="fa fa-credit-card"></i> Convert To Installment
                        </a>
                    @endif
                @endcan
            @endauth
            <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white no-print tw-dw-btn-sm" id="print_invoice" 
                 aria-label="Print"><i class="fas fa-print"></i> @lang( 'messages.print' )
            </button>
            @auth
                <a href="{{action([\App\Http\Controllers\SellController::class, 'index'])}}" class="tw-dw-btn tw-dw-btn-success tw-text-white no-print tw-dw-btn-sm" ><i class="fas fa-backward"></i>
                </a>
            @endauth
        </div>
    </div>
    <div class="row">
        <div class="col-md-8 col-md-offset-2 col-sm-12" style="border: 1px solid #ccc;">
            <div class="spacer"></div>
            <div id="invoice_content">
                {!! $receipt['html_content'] !!}
            </div>
            <div class="spacer"></div>
        </div>
    </div>
    <div class="spacer"></div>
</div>
@stop
@section('javascript')
<script type="text/javascript">
    $(document).ready(function(){
        $(document).on('click', '#print_invoice', function(){
            $('#invoice_content').printThis();
        });

        $(document).on('click', '.convert-to-installment-invoice', function(e){
            e.preventDefault();
            var checkUrl = $(this).data('check-url');
            var cloneUrl = $(this).data('clone-url') || $(this).attr('href');
            $.get(checkUrl, function(res){
                if (res.success && res.data && res.data.exists) {
                    if (typeof swal !== 'undefined') {
                        swal({
                            title: 'This sell already has installment loan.',
                            text: 'Do you want to view the existing loan?',
                            icon: 'warning',
                            buttons: {
                                cancel: 'Cancel',
                                confirm: {
                                    text: 'View Loan',
                                    value: true
                                }
                            }
                        }).then(function(ok){
                            if (ok && res.data.loan_url) {
                                window.location = res.data.loan_url;
                            }
                        });
                    } else {
                        alert('This sell already has installment loan.');
                    }
                } else {
                    window.location = cloneUrl;
                }
            }).fail(function(){
                window.location = cloneUrl;
            });
        });
    });
    @if(!empty(request()->input('print_on_load')))
        $(window).on('load', function(){
            $('#invoice_content').printThis();
        });
    @endif
</script>
@endsection
