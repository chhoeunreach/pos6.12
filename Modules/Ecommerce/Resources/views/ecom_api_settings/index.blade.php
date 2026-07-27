@extends('layouts.app')
@section('title', 'Ecommerce API Settings')

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Ecommerce API Settings
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Manage external ecommerce tokens</small>
    </h1>
</section>

<section class="content">
    @if(session('new_api_token'))
        <div class="alert alert-success">
            <strong>New API token:</strong>
            <input type="text" class="form-control" readonly value="{{ session('new_api_token') }}" onclick="this.select();" style="margin-top: 8px;">
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            @component('components.widget', ['class' => 'box-primary', 'title' => 'Create token'])
                {!! Form::open(['url' => action([\Modules\Ecommerce\Http\Controllers\EcomApiSettingController::class, 'store']), 'method' => 'post']) !!}
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':*') !!}
                        {!! Form::select('location_id', $locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required', 'style' => 'width: 100%;']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('shop_domain', 'Shop domain:') !!}
                        {!! Form::text('shop_domain', null, ['class' => 'form-control', 'placeholder' => '127.0.0.1:8001']) !!}
                        <p class="help-block">Optional. Leave blank to allow this token from any ecommerce shop.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-key"></i> Generate token
                    </button>
                {!! Form::close() !!}
            @endcomponent
        </div>

        <div class="col-md-8">
            @component('components.widget', ['class' => 'box-primary', 'title' => 'API tokens'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('purchase.business_location')</th>
                                <th>Shop domain</th>
                                <th>Token</th>
                                <th>Status</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($settings as $setting)
                                <tr>
                                    <td>{{ $locations[$setting->location_id] ?? $setting->location_id }}</td>
                                    <td>{{ $setting->shop_domain ?: 'Any' }}</td>
                                    <td>
                                        <input type="text" class="form-control input-sm ecom-api-token" readonly value="{{ $setting->api_token }}" onclick="this.select();">
                                    </td>
                                    <td>
                                        @if($setting->is_active)
                                            <span class="label label-success">Active</span>
                                        @else
                                            <span class="label label-default">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-info test-ecom-api" data-token="{{ $setting->api_token }}" data-domain="{{ $setting->shop_domain }}">
                                            <i class="fa fa-plug"></i> Test API
                                        </button>
                                        {!! Form::open(['url' => action([\Modules\Ecommerce\Http\Controllers\EcomApiSettingController::class, 'regenerate'], [$setting->id]), 'method' => 'post', 'style' => 'display:inline;']) !!}
                                            <button type="submit" class="btn btn-xs btn-warning">
                                                <i class="fa fa-refresh"></i> Regenerate
                                            </button>
                                        {!! Form::close() !!}
                                        @if($setting->is_active)
                                            {!! Form::open(['url' => action([\Modules\Ecommerce\Http\Controllers\EcomApiSettingController::class, 'deactivate'], [$setting->id]), 'method' => 'post', 'style' => 'display:inline;']) !!}
                                                <button type="submit" class="btn btn-xs btn-danger">
                                                    <i class="fa fa-power-off"></i> Deactivate
                                                </button>
                                            {!! Form::close() !!}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No ecommerce API tokens found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div id="ecom_api_test_result" class="alert" style="display:none; margin-top: 10px;"></div>
            @endcomponent
        </div>
    </div>
</section>

@endsection

@section('javascript')
<script>
    $(document).on('click', '.test-ecom-api', function() {
        var token = $(this).data('token');
        var domain = $(this).data('domain');
        var result = $('#ecom_api_test_result');
        var headers = {'API-TOKEN': token};

        if (domain) {
            headers['SHOP-DOMAIN'] = domain;
        }

        result.removeClass('alert-success alert-danger').addClass('alert-info').text('Testing API...').show();

        $.ajax({
            url: '{{ url('/api/ecom/settings') }}',
            method: 'GET',
            headers: headers,
            success: function() {
                result.removeClass('alert-info alert-danger').addClass('alert-success').text('API test successful.');
            },
            error: function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'API test failed.';
                result.removeClass('alert-info alert-success').addClass('alert-danger').text(message);
            }
        });
    });
</script>
@endsection
