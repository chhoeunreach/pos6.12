@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<section class="content-header"><h1>Mismatch Fixer Settings</h1></section>
<section class="content"><div class="box"><div class="box-body">
<p>Max bulk fix rows per request: <strong>{{ $max_bulk_fix_rows }}</strong></p>
<p>Dangerous global fix is disabled. Use filters + selected rows only.</p>
</div></div></section>
@endsection
