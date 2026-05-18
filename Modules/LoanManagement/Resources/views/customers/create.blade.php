@extends('loanmanagement::layouts.app')
@section('title', 'Add Loan Customer')

@section('content_body')
<section class="content-header">
    <h1>Add Loan Customer</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="POST" action="{{ route('loan-management.customers.store') }}">
                @csrf
                <input type="hidden" name="create_mode" id="create_mode" value="new">
                @include('loanmanagement::customers.partials.form')
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('loan-management.customers') }}" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </div>
</section>
@endsection
