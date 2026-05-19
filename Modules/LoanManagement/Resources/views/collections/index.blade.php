@extends('loanmanagement::layouts.app')
@section('title', $definition['title'] ?? 'Collection')
@section('content_body')
<section class="content-header">
    <h1>{{ $definition['title'] ?? 'Collection' }}</h1>
    @if(!empty($definition['khmer']))
        <p class="text-muted">{{ $definition['khmer'] }}</p>
    @endif
</section>
<section class="content">
    @include('loanmanagement::collections.partials.filters')
    @include('loanmanagement::collections.partials.loan_table')
</section>
@endsection
