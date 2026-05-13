@extends('layouts.app')
@section('title', 'Loan Management Dashboard')

@section('content')
    @include('loanmanagement::dashboard.dashboard')
    @include('loanmanagement::layouts.sidebar_focus')
@endsection
