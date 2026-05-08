@extends('layouts.app')

@section('title', 'Master Data Backup & Restore')

@section('content')
<section class="content-header">
    <h1>Master Data Backup &amp; Restore</h1>
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
                    <h3 class="box-title">Backup</h3>
                </div>
                <div class="box-body">
                    <form method="POST" action="{{ route('master-data.export') }}">
                        @csrf

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="md_select_all">
                                Select all
                            </label>
                        </div>

                        <hr>

                        @php($sections = [
                            'users' => 'Users',
                            'products' => 'Products',
                            'categories' => 'Categories',
                            'brands' => 'Brands',
                            'units' => 'Units',
                            'taxes' => 'Taxes',
                            'locations' => 'Business Locations',
                            'settings' => 'Settings',
                        ])

                        @foreach($sections as $key => $label)
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" class="md_section" name="sections[]" value="{{ $key }}">
                                    {{ $label }}
                                </label>
                            </div>
                        @endforeach

                        <div class="form-group" style="margin-top: 10px;">
                            <label>Export format</label>
                            <select name="format" class="form-control">
                                <option value="zip" selected>ZIP (manifest.json + data.json)</option>
                                <option value="sql">SQL (.sql)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Export
                        </button>
                        <p class="help-block">
                            ZIP contains <code>manifest.json</code> and <code>data.json</code>. No transaction data is included.
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Restore</h3>
                </div>
                <div class="box-body">
                    <form method="POST" action="{{ route('master-data.preview') }}" enctype="multipart/form-data">
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

                        <button type="submit" class="btn btn-success">
                            Preview
                        </button>
                        <p class="help-block">
                            Preview shows included sections, record counts, and match results before restoring.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    (function () {
        var selectAll = document.getElementById('md_select_all');
        if (!selectAll) return;
        selectAll.addEventListener('change', function () {
            var boxes = document.querySelectorAll('.md_section');
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = selectAll.checked;
            }
        });
    })();
</script>
@endsection
