@extends('loanmanagement::layouts.app')
@section('title', $title)
@section('content_body')
<section class="content-header">
    <h1>{{ $title }}</h1>
</section>
<section class="content">
    @include('loanmanagement::collections.partials.filters')
    @include('loanmanagement::collections.partials.loan_table')
</section>
@endsection
