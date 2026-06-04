<script type="text/javascript">
    accessory_base_path = "{{ url(config('accessory.route_prefix', 'accessory-pos')) }}";
    base_path = accessory_base_path;
    //used for push notification
    APP = {};
    APP.PUSHER_APP_KEY = '{{ config('broadcasting.connections.pusher.key') }}';
    APP.PUSHER_APP_CLUSTER = '{{ config('broadcasting.connections.pusher.options.cluster') }}';
    APP.INVOICE_SCHEME_SEPARATOR = '{{ config('constants.invoice_scheme_separator') }}';
    //variable from app service provider
    APP.PUSHER_ENABLED = '{{ $__is_pusher_enabled }}';
    @auth
    @php
        $user = Auth::user();
    @endphp
    APP.USER_ID = "{{ $user->id }}";
    @else
        APP.USER_ID = '';
    @endauth
</script>

<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js?v=$asset_v"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js?v=$asset_v"></script>
<![endif]-->

<script src="{{ asset('modules/accessory/v7/js/vendor.js?v=' . $asset_v) }}"></script>

@if (file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script src="{{ asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v) }}">
    </script>
@else
    <script src="{{ asset('js/lang/en.js?v=' . $asset_v) }}"></script>
@endif
@php
    $business_date_format = session('business.date_format', config('constants.default_date_format'));
    $datepicker_date_format = str_replace('d', 'dd', $business_date_format);
    $datepicker_date_format = str_replace('m', 'mm', $datepicker_date_format);
    $datepicker_date_format = str_replace('Y', 'yyyy', $datepicker_date_format);

    $moment_date_format = str_replace('d', 'DD', $business_date_format);
    $moment_date_format = str_replace('m', 'MM', $moment_date_format);
    $moment_date_format = str_replace('Y', 'YYYY', $moment_date_format);

    $business_time_format = session('business.time_format');
    $moment_time_format = 'HH:mm';
    if ($business_time_format == 12) {
        $moment_time_format = 'hh:mm A';
    }

    $common_settings = !empty(session('business.common_settings')) ? session('business.common_settings') : [];

    $default_datatable_page_entries = !empty($common_settings['default_datatable_page_entries'])
        ? $common_settings['default_datatable_page_entries']
        : 25;
@endphp

<script>
    Dropzone.autoDiscover = false;
    moment.tz.setDefault('{{ Session::get('business.time_zone') }}');

    function accessoryRouteUrl(url, forceAbsolute) {
        if (!url || typeof url !== 'string') {
            return url;
        }

        var trimmedUrl = $.trim(url);
        var routePrefix = '/{{ trim(config('accessory.route_prefix', 'accessory-pos'), '/') }}';
        var skipPrefixes = [
            '/login',
            '/logout',
            '/register',
            '/password',
            '/broadcasting',
            '/js',
            '/css',
            '/img',
            '/fonts',
            '/audio',
            '/uploads',
            '/storage',
            '/vendor',
            '/AdminLTE',
            '/Modules',
            '/favicon',
        ];

        if (
            trimmedUrl === '' ||
            trimmedUrl === '#' ||
            trimmedUrl.indexOf('#') === 0 ||
            trimmedUrl.indexOf('javascript:') === 0 ||
            trimmedUrl.indexOf('mailto:') === 0 ||
            trimmedUrl.indexOf('tel:') === 0 ||
            trimmedUrl.indexOf('data:') === 0 ||
            trimmedUrl.indexOf('//') === 0
        ) {
            return url;
        }

        var parsedUrl;
        try {
            parsedUrl = new URL(trimmedUrl, window.location.origin);
        } catch (e) {
            return url;
        }

        if (parsedUrl.origin !== window.location.origin) {
            return url;
        }

        if (parsedUrl.pathname === routePrefix || parsedUrl.pathname.indexOf(routePrefix + '/') === 0) {
            return forceAbsolute ? parsedUrl.href : trimmedUrl;
        }

        for (var i = 0; i < skipPrefixes.length; i++) {
            if (parsedUrl.pathname === skipPrefixes[i] || parsedUrl.pathname.indexOf(skipPrefixes[i] + '/') === 0) {
                return url;
            }
        }

        parsedUrl.pathname = routePrefix + parsedUrl.pathname;

        if (forceAbsolute || trimmedUrl.indexOf('://') !== -1) {
            return parsedUrl.href;
        }

        return parsedUrl.pathname + parsedUrl.search + parsedUrl.hash;
    }

    function rewriteAccessoryUrls(context) {
        var $context = context ? $(context) : $(document);
        $context.find('a[href], form[action], [data-href]').addBack('a[href], form[action], [data-href]').each(function() {
            var $element = $(this);

            $.each(['href', 'action', 'data-href'], function(index, attribute) {
                var value = $element.attr(attribute);

                if (value) {
                    $element.attr(attribute, accessoryRouteUrl(value));
                }
            });
        });
    }

    $.ajaxPrefilter(function(options) {
        options.url = accessoryRouteUrl(options.url, true);
    });

    $(document).ready(function() {
        rewriteAccessoryUrls(document);

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        @if (config('app.debug') == false)
            $.fn.dataTable.ext.errMode = 'throw';
        @endif
    });

    $(document).ajaxComplete(function(event, xhr, settings) {
        rewriteAccessoryUrls(document);
    });

    $(document).on('shown.bs.modal', function(event) {
        rewriteAccessoryUrls(event.target);
    });

    var financial_year = {
        start: moment('{{ Session::get('financial_year.start') }}'),
        end: moment('{{ Session::get('financial_year.end') }}'),
    }
    @if (file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
        //Default setting for select2
        $.fn.select2.defaults.set("language", "{{ session()->get('user.language', config('app.locale')) }}");
    @endif

    var datepicker_date_format = "{{ $datepicker_date_format }}";
    var moment_date_format = "{{ $moment_date_format }}";
    var moment_time_format = "{{ $moment_time_format }}";

    var app_locale = "{{ session()->get('user.language', config('app.locale')) }}";

    var non_utf8_languages = [
        @foreach (config('constants.non_utf8_languages') as $const)
            "{{ $const }}",
        @endforeach
    ];

    var __default_datatable_page_entries = "{{ $default_datatable_page_entries }}";

    var __new_notification_count_interval = "{{ config('constants.new_notification_count_interval', 60) }}000";
</script>

@if (file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script src="{{ asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v) }}">
    </script>
@else
    <script src="{{ asset('js/lang/en.js?v=' . $asset_v) }}"></script>
@endif

<script src="{{ asset('modules/accessory/v7/js/functions.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('modules/accessory/v7/js/common.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('modules/accessory/v7/js/app.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('modules/accessory/v7/js/help-tour.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('modules/accessory/v7/js/documents_and_note.js?v=' . $asset_v) }}"></script>

<!-- TODO -->
@if (file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script
        src="{{ asset('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v) }}">
    </script>
@endif
@php
    $validation_lang_file = 'messages_' . session()->get('user.language', config('app.locale')) . '.js';
@endphp
@if (file_exists(public_path() . '/js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file))
    <script src="{{ asset('js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file . '?v=' . $asset_v) }}">
    </script>
@endif

@if (!empty($__system_settings['additional_js']))
    {!! $__system_settings['additional_js'] !!}
@endif
@yield('javascript')

@if (Module::has('Essentials'))
    @includeIf('essentials::layouts.partials.footer_part')
@endif

<script type="text/javascript">
    $(document).ready(function() {
        var locale = "{{ session()->get('user.language', config('app.locale')) }}";
        var isRTL =
            @if (in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')))
                true;
            @else
                false;
            @endif

        $('#calendar').fullCalendar('option', {
            locale: locale,
            isRTL: isRTL
        });


        // Initialize popovers and close them when clicking outside
        $('[data-toggle="popover"]').popover();
        $(document).on('click', function (e) {
            $('[data-toggle="popover"]').each(function () {
                if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                    $(this).popover('hide');
                }
            });
        });

        $('.dt-buttons.btn-group').find('a.btn').removeClass('btn-default');
        $('.dt-buttons.btn-group').find('a.btn').removeClass('btn');
        
        // $('.date_range').on('show.daterangepicker', function (ev, picker) {
        //     $(picker.container).insertAfter($(this));
        // });
   
    });
</script>

