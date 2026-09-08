@extends('loanmanagement::layouts.app')

@section('title', $title ?? 'Page')

@section('content_body')
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $title ?? 'Page' }}</h3>
        </div>
        <div class="box-body">
            <p>This standalone page is available through the Installment Management menu.</p>
        </div>
    </div>
@endsection
