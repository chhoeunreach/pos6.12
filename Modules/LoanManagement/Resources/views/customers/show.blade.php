@extends('loanmanagement::layouts.app')
@section('title', 'Customer Detail')

@section('loan_css')
<link rel="stylesheet" href="{{ asset('css/loan-customer-detail.css') }}?v=20261029">
@endsection

@section('content_body')
@include('loanmanagement::customers.partials.detail')
@endsection
