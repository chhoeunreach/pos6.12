@extends('loanmanagement::layouts.app')
@section('title', 'Add Loan User')

@section('content_body')
<section class="content-header">
    <h1>Add Loan User</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="POST" action="{{ route('loan-management.users.store') }}">
                @csrf
                @include('loanmanagement::users.partials.form')
                <button type="submit" class="btn btn-primary">Create User</button>
                <a href="{{ route('loan-management.users.index') }}" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </div>
</section>
@endsection
