@extends('layouts.app')

@section('title', 'User Backup Restore')

@section('content')
<section class="content-header">
    <h1>User Backup Restore</h1>
</section>

<section class="content">
    @if(session('status'))
        @php($s = session('status'))
        <div class="alert {{ !empty($s['success']) ? 'alert-success' : 'alert-danger' }}">
            {{ $s['msg'] ?? '' }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Backup Users</h3>
                </div>
                <div class="box-body">
                    <form method="POST" action="{{ route('user-backup-restore.export') }}">
                        @csrf

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="active_only" value="1" checked>
                                Active users only
                            </label>
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="include_inactive" value="1">
                                Include inactive users
                            </label>
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="include_roles" value="1">
                                Include roles
                            </label>
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="include_location_permissions" value="1">
                                Include location permissions
                            </label>
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="include_passwords" value="1">
                                Include hashed passwords
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Export Users
                        </button>
                        <p class="help-block">
                            Download will be a ZIP containing <code>manifest.json</code> and <code>users.json</code>.
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Restore Users</h3>
                </div>
                <div class="box-body">
                    <form method="POST" action="{{ route('user-backup-restore.preview') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label>Backup ZIP</label>
                            <input type="file" name="backup_zip" class="form-control" required accept=".zip">
                        </div>

                        <div class="form-group">
                            <label>Restore mode</label>
                            <select name="mode" class="form-control">
                                <option value="insert_only" selected>Insert Only</option>
                                <option value="update_existing">Update Existing</option>
                                <option value="insert_update">Insert &amp; Update</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Password option</label>
                            <select name="password_option" class="form-control">
                                <option value="random" selected>Generate random password</option>
                                <option value="default">Use default password</option>
                                <option value="restore_hash">Restore hashed password if available</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Default password (if chosen)</label>
                            <input type="text" name="default_password" class="form-control" value="12345678">
                        </div>

                        <button type="submit" class="btn btn-success">
                            Preview
                        </button>
                        <p class="help-block">
                            Preview shows matched users (by email/username/contact) and warnings before importing.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

