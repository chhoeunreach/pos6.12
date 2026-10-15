@extends('smartstockinventory::layouts.master')
@section('page_title', 'Authority — issue details')
@section('hide_action_bar', '1')
@section('module_content')
@include('smartstockinventory::authority.details_content')
<a class="btn btn-default" href="{{ ssi_route('ssi.authority.index') }}">Back to Authority</a>
@endsection
