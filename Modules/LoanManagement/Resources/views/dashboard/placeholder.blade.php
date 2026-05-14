@extends('loanmanagement::layouts.app')
@section('title', 'Loan Management')
@section('content')
<section class="content-header">
    <h1>{{ $page }}</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <div class="row">
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-aqua"><i class="fa fa-database"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Source Table</span>
                            <span class="info-box-number">{{ $payload['summary']['table'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-green"><i class="fa fa-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Records</span>
                            <span class="info-box-number">{{ $payload['summary']['total'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            @foreach(($payload['columns'] ?? []) as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($payload['rows'] ?? []) as $row)
                            <tr>
                                @foreach(($payload['columns'] ?? []) as $column)
                                    <td>{{ is_bool($row[$column] ?? null) ? (($row[$column] ?? false) ? 'true' : 'false') : ($row[$column] ?? '') }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(1, count($payload['columns'] ?? [])) }}" class="text-center text-muted">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
