@php
    $isKhmer = session('user.language', config('app.locale')) === 'km';
    $text = fn ($en, $km) => $isKhmer ? $km : $en;
@endphp
<div data-customer-modal-view="edit">
    <div class="lcm-edit-heading"><div><span>{{ $customerRow->customer_code ?? '' }}</span><h3>{{ ($customerRow->khmer_name ?? '') ?: $customerRow->name }}</h3><p>{{ $text('Update customer information, documents and app access.', 'កែប្រែព័ត៌មានអតិថិជន ឯកសារ និងសិទ្ធិចូលប្រើ។') }}</p></div><a href="{{ route('loan-management.customers.show', $customerRow->id) }}" class="btn btn-default js-customer-modal" data-title="{{ $text('Customer Detail', 'ព័ត៌មានអតិថិជន') }}"><i class="fa fa-eye" aria-hidden="true"></i> {{ $text('View Detail', 'មើលព័ត៌មាន') }}</a></div>
    <form method="POST" action="{{ route('loan-management.customers.update', $customerRow->id) }}" enctype="multipart/form-data" id="loanCustomerModalForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="expected_customer_id" value="{{ $customerRow->id }}">
        <input type="hidden" name="expected_customer_code" value="{{ $customerRow->customer_code ?? '' }}">
        <div class="alert alert-danger lcm-errors" role="alert" hidden></div>
        @include('loanmanagement::customers.partials.form')
        <div class="lcm-form-actions"><button type="button" class="btn btn-default" data-dismiss="modal">{{ $text('Cancel', 'បោះបង់') }}</button><button type="submit" class="btn btn-primary lcm-save"><i class="fa fa-save" aria-hidden="true"></i> {{ $text('Save Changes', 'រក្សាទុកការផ្លាស់ប្តូរ') }}</button></div>
    </form>
</div>
