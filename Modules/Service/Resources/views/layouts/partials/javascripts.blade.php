<script type="text/javascript">
    service_base_path = "{{ url(config('service.route_prefix', 'service-pos')) }}";
    base_path = service_base_path;
    window.Laravel = window.Laravel || {};
    window.Laravel.csrfToken = "{{ csrf_token() }}";
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

<script src="{{ asset('modules/service/v7/js/vendor.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>

@if (file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script src="{{ asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v . '-service-jsfix-20260609') }}">
    </script>
@else
    <script src="{{ asset('js/lang/en.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
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

    var csrfToken = $('meta[name="csrf-token"]').attr('content') || window.Laravel.csrfToken;

    if (csrfToken) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
    }

    function serviceRouteUrl(url, forceAbsolute) {
        if (!url || typeof url !== 'string') {
            return url;
        }

        var trimmedUrl = $.trim(url);
        var routePrefix = '/{{ trim(config('service.route_prefix', 'service-pos'), '/') }}';
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

    function rewriteServiceUrls(context) {
        var $context = context ? $(context) : $(document);
        $context.find('a[href], form[action], [data-href]').addBack('a[href], form[action], [data-href]').each(function() {
            var $element = $(this);

            if ($element.is('[data-service-skip-rewrite]')) {
                return;
            }

            $.each(['href', 'action', 'data-href'], function(index, attribute) {
                var value = $element.attr(attribute);

                if (value) {
                    $element.attr(attribute, serviceRouteUrl(value));
                }
            });
        });
    }

    $.ajaxPrefilter(function(options) {
        options.url = serviceRouteUrl(options.url, true);
    });

    $(document).ready(function() {
        rewriteServiceUrls(document);

        @if (config('app.debug') == false)
            $.fn.dataTable.ext.errMode = 'throw';
        @endif
    });

    $(document).ajaxComplete(function(event, xhr, settings) {
        rewriteServiceUrls(document);
    });

    $(document).on('shown.bs.modal', function(event) {
        rewriteServiceUrls(event.target);
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
    <script src="{{ asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v . '-service-jsfix-20260609') }}">
    </script>
@else
    <script src="{{ asset('js/lang/en.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
@endif

<script src="{{ asset('modules/service/v7/js/functions.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
<script>
    window.__is_online = window.__is_online || function() {
        return true;
    };
    window.update_font_size = window.update_font_size || function() {};
    window.__currency_convert_recursively = window.__currency_convert_recursively || function(element, use_page_currency) {
        if (typeof __currency_trans_from_en !== 'function') {
            return;
        }

        $(element).find('.display_currency').each(function() {
            var value = $(this).text();
            var show_symbol = $(this).data('currency_symbol');
            var highlight = $(this).data('highlight');

            $(this).text(__currency_trans_from_en(value, show_symbol === true, use_page_currency));

            if (highlight === true) {
                __highlight(value, $(this));
            }
        });
    };
    window.__number_uf = window.__number_uf || function(input_number) {
        var thousand_separator = window.__currency_thousand_separator || ',';
        var decimal_separator = window.__currency_decimal_separator || '.';
        var num = String(input_number || '0').replace(new RegExp('\\' + thousand_separator, 'g'), '');
        num = num.replace(decimal_separator, '.');

        return parseFloat(num) || 0;
    };
    window.__number_f = window.__number_f || function(input_number) {
        return String(input_number || 0);
    };
    window.__read_number = window.__read_number || function(input_element) {
        return __number_uf(input_element.val());
    };
    window.__write_number = window.__write_number || function(input_element, value) {
        input_element.val(value);
    };
    window.__currency_trans_from_en = window.__currency_trans_from_en || function(input_number, show_symbol) {
        var value = __number_f(input_number);
        var symbol = window.__currency_symbol || '';

        return show_symbol && symbol ? symbol + value : value;
    };
    window.__translate = window.__translate || function(str, data) {
        return str;
    };
    window.__highlight = window.__highlight || function() {};
</script>
<script src="{{ asset('modules/service/v7/js/common.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
<script src="{{ asset('modules/service/v7/js/app.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
<script src="{{ asset('modules/service/v7/js/help-tour.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>
<script src="{{ asset('modules/service/v7/js/documents_and_note.js?v=' . $asset_v . '-service-jsfix-20260609') }}"></script>

<!-- TODO -->
@if (file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script
        src="{{ asset('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v . '-service-jsfix-20260609') }}">
    </script>
@endif
@php
    $validation_lang_file = 'messages_' . session()->get('user.language', config('app.locale')) . '.js';
@endphp
@if (file_exists(public_path() . '/js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file))
    <script src="{{ asset('js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file . '?v=' . $asset_v . '-service-jsfix-20260609') }}">
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

