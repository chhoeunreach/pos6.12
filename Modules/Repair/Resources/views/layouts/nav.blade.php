<section class="no-print">
    <nav class="navbar-default tw-transition-all tw-duration-5000 tw-shrink-0 tw-rounded-2xl tw-m-[16px] tw-border-2 !tw-bg-white">
        <div class="container-fluid">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false" style="margin-top: 3px; margin-right: 3px;">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{ repair_route('dashboard.index') }}"><i class="fas fa-wrench"></i> {{__('repair::lang.repair')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @if(auth()->user()->can('job_sheet.create') || auth()->user()->can('job_sheet.view_assigned') || auth()->user()->can('job_sheet.view_all'))
                        <li @if(request()->is('repair/job-sheet') || request()->is(trim(config('service.route_prefix', 'service'), '/') . '/repair/job-sheet')) class="active" @endif>
                            <a href="{{ repair_route('job-sheet.index') }}">
                                @lang('repair::lang.job_sheets')
                            </a>
                        </li>
                    @endif

                    @can('job_sheet.create')
                        <li @if(request()->is('repair/job-sheet/create') || request()->is(trim(config('service.route_prefix', 'service'), '/') . '/repair/job-sheet/create')) class="active" @endif>
                            <a href="{{ repair_route('job-sheet.create') }}">
                                @lang('repair::lang.add_job_sheet')
                            </a>
                        </li>
                    @endcan

                    @if(auth()->user()->can('repair.view') || auth()->user()->can('repair.view_own'))
                        <li @if(request()->is('repair/repair') || request()->is(trim(config('service.route_prefix', 'service'), '/') . '/repair/repair')) class="active" @endif><a href="{{ repair_route('repair.index') }}">@lang('repair::lang.list_invoices')</a></li>
                    @endif

                    @can('repair.create')
                        <li @if(request('sub_type') == 'repair' && (request()->is('pos/create') || request()->is(trim(config('service.route_prefix', 'service'), '/') . '/pos/create'))) class="active" @endif><a href="{{ repair_route('pos.create', ['sub_type' => 'repair']) }}">@lang('repair::lang.add_invoice')</a></li>
                    @endcan

                    @if(auth()->user()->can('brand.view') || auth()->user()->can('brand.create'))
                        <li @if(request()->is('brands*') || request()->is(trim(config('service.route_prefix', 'service'), '/') . '/brands*')) class="active" @endif><a href="{{ repair_route('brands.index') }}">@lang('brand.brands')</a></li>
                    @endif

                    @if (auth()->user()->can('edit_repair_settings'))
                        <li @if(request()->is('repair/repair-settings*') || request()->is(trim(config('service.route_prefix', 'service'), '/') . '/repair/repair-settings*')) class="active" @endif><a href="{{ repair_route('repair-settings.index') }}">@lang('messages.settings')</a></li>
                    @endif
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>
