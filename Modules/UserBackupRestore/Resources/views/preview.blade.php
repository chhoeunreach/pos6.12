@extends('layouts.app')

@section('title', 'Preview User Import')

@section('content')
<section class="content-header">
    <h1>Preview User Import</h1>
</section>

<section class="content">
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">Summary</h3>
        </div>
        <div class="box-body">
            <p><b>Total users in backup:</b> {{ $record_count ?? 0 }}</p>
            <p><b>New users:</b> {{ $new_count ?? 0 }}</p>
            <p><b>Matched existing users:</b> {{ $matched_count ?? 0 }}</p>
            <p><b>Would be skipped (Insert Only):</b> {{ $skipped_count ?? 0 }}</p>
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

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Confirm Import</h3>
        </div>
        <div class="box-body">
            <form method="POST" action="{{ route('user-backup-restore.import') }}">
                @csrf
                <input type="hidden" name="stored_path" value="{{ $stored_path }}">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="password_option" value="{{ $password_option }}">
                <input type="hidden" name="default_password" value="{{ $default_password }}">

                <p><b>Mode:</b> {{ $mode }}</p>
                <p><b>Password option:</b> {{ $password_option }}</p>

                <button type="submit" class="btn btn-primary">
                    Confirm Import
                </button>
                <a href="{{ route('user-backup-restore.index') }}" class="btn btn-default">Cancel</a>
            </form>
        </div>
    </div>

    @if(!empty($sample))
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Sample (first 10 users)</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sample as $row)
                            <tr>
                                <td>{{ $row['email'] ?? '' }}</td>
                                <td>{{ $row['username'] ?? '' }}</td>
                                <td>{{ $row['contact_no'] ?? ($row['contact_number'] ?? '') }}</td>
                                <td>{{ $row['status'] ?? '' }}</td>
                                <td>{{ !empty($row['_match']) ? 'MATCH' : 'NEW' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
@endsection

