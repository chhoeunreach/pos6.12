@extends('layouts.app')

@section('title', 'Master Data Preview')

@section('content')
<section class="content-header">
    <h1>Master Data Preview</h1>
</section>

<section class="content">
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">Included Sections</h3>
        </div>
        <div class="box-body">
            <p><b>Sections:</b> {{ !empty($sections) ? implode(', ', $sections) : '' }}</p>
            @if(!empty($record_counts))
                <ul>
                    @foreach($record_counts as $k => $v)
                        <li><b>{{ $k }}:</b> {{ $v }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Match Result (based on mode)</h3>
        </div>
        <div class="box-body">
            @if(!empty($section_stats))
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>New</th>
                            <th>Matched</th>
                            <th>Will Insert</th>
                            <th>Will Update</th>
                            <th>Will Skip</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section_stats as $row)
                            <tr>
                                <td>{{ $row['section'] }}</td>
                                <td>{{ $row['new'] }}</td>
                                <td>{{ $row['matched'] }}</td>
                                <td>{{ $row['will_insert'] }}</td>
                                <td>{{ $row['will_update'] }}</td>
                                <td>{{ $row['will_skip'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p><b>Mode:</b> {{ $mode }}</p>
        </div>
    </div>

    @if(!empty($warnings))
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">Warnings</h3>
            </div>
            <div class="box-body">
                <ul>
                    @foreach($warnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="box box-success">
        <div class="box-header with-border">
            <h3 class="box-title">Confirm Restore</h3>
        </div>
        <div class="box-body">
            <form method="POST" action="{{ route('master-data.import') }}">
                @csrf
                <input type="hidden" name="stored_path" value="{{ $stored_path }}">
                <input type="hidden" name="mode" value="{{ $mode }}">

                <button type="submit" class="btn btn-success">
                    Confirm Restore
                </button>
                <a href="{{ route('master-data.index') }}" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </div>
</section>
@endsection

