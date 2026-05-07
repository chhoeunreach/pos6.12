@extends('layouts.app')
@section('title', 'Import Backup')

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Import Backup
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Import a custom backup .sql file</small>
    </h1>
</section>

<section class="content">
    @if (session('status'))
        @php $status = session('status'); @endphp
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-{{ !empty($status['success']) && $status['success'] ? 'success' : 'danger' }} alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    {{ $status['msg'] ?? '' }}
                </div>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary'])
                {!! Form::open(['url' => action([\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'import']), 'method' => 'post', 'enctype' => 'multipart/form-data']) !!}

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            {!! Form::label('backup_sql', 'SQL file (.sql/.txt):') !!}
                            {!! Form::file('backup_sql', ['accept'=> '.sql,.txt', 'required' => 'required']) !!}
                            <p class="help-block">Max size: {{ config('constants.custom_backup_import_max_kb') }} KB</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <label>Conflict handling:</label>
                        <div class="radio">
                            <label>
                                {!! Form::radio('conflict_mode', 'insert', old('conflict_mode', 'insert') == 'insert') !!}
                                Insert only
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                {!! Form::radio('conflict_mode', 'ignore', old('conflict_mode') == 'ignore') !!}
                                Insert ignore
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                {!! Form::radio('conflict_mode', 'replace', old('conflict_mode') == 'replace') !!}
                                Replace existing
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <label>Users &amp; Permissions conflicts (by email):</label>
                        <div class="radio">
                            <label>
                                {!! Form::radio('user_conflict', 'skip', old('user_conflict', 'skip') == 'skip') !!}
                                Skip existing users
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                {!! Form::radio('user_conflict', 'update', old('user_conflict') == 'update') !!}
                                Update existing users (except password)
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                {!! Form::radio('user_conflict', 'replace', old('user_conflict') == 'replace') !!}
                                Replace existing users (including hashed password)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('confirm_risk', 1, old('confirm_risk')) !!}
                                I understand this may overwrite/duplicate data
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                            <i class="fa fa-upload"></i> Import SQL
                        </button>
                    </div>
                </div>

                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
</section>

@endsection

