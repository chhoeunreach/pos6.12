<script type="text/javascript">
    accessory_base_path = "<?php echo e(url(config('accessory.route_prefix', 'accessory-pos')), false); ?>";
    base_path = accessory_base_path;
    window.Laravel = window.Laravel || {};
    window.Laravel.csrfToken = "<?php echo e(csrf_token(), false); ?>";
    //used for push notification
    APP = {};
    APP.PUSHER_APP_KEY = '<?php echo e(config('broadcasting.connections.pusher.key'), false); ?>';
    APP.PUSHER_APP_CLUSTER = '<?php echo e(config('broadcasting.connections.pusher.options.cluster'), false); ?>';
    APP.INVOICE_SCHEME_SEPARATOR = '<?php echo e(config('constants.invoice_scheme_separator'), false); ?>';
    //variable from app service provider
    APP.PUSHER_ENABLED = '<?php echo e($__is_pusher_enabled, false); ?>';
    <?php if(auth()->guard()->check()): ?>
    <?php
        $user = Auth::user();
    ?>
    APP.USER_ID = "<?php echo e($user->id, false); ?>";
    <?php else: ?>
        APP.USER_ID = '';
    <?php endif; ?>
</script>

<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js?v=$asset_v"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js?v=$asset_v"></script>
<![endif]-->

<script src="<?php echo e(asset('modules/accessory/v7/js/vendor.js?v=' . $asset_v), false); ?>"></script>

<?php if(file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js'))): ?>
    <script src="<?php echo e(asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v), false); ?>">
    </script>
<?php else: ?>
    <script src="<?php echo e(asset('js/lang/en.js?v=' . $asset_v), false); ?>"></script>
<?php endif; ?>
<?php
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
?>

<script>
    Dropzone.autoDiscover = false;
    moment.tz.setDefault('<?php echo e(Session::get('business.time_zone'), false); ?>');

    function accessoryRouteUrl(url, forceAbsolute) {
        if (!url || typeof url !== 'string') {
            return url;
        }

        var trimmedUrl = $.trim(url);
        var routePrefix = '/<?php echo e(trim(config('accessory.route_prefix', 'accessory-pos'), '/'), false); ?>';
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

            if ($element.is('[data-accessory-skip-rewrite]')) {
                return;
            }

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

        var csrfToken = $('meta[name="csrf-token"]').attr('content') || window.Laravel.csrfToken;

        if (csrfToken) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }

        <?php if(config('app.debug') == false): ?>
            $.fn.dataTable.ext.errMode = 'throw';
        <?php endif; ?>
    });

    $(document).ajaxComplete(function(event, xhr, settings) {
        rewriteAccessoryUrls(document);
    });

    $(document).on('shown.bs.modal', function(event) {
        rewriteAccessoryUrls(event.target);
    });

    var financial_year = {
        start: moment('<?php echo e(Session::get('financial_year.start'), false); ?>'),
        end: moment('<?php echo e(Session::get('financial_year.end'), false); ?>'),
    }
    <?php if(file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js'))): ?>
        //Default setting for select2
        $.fn.select2.defaults.set("language", "<?php echo e(session()->get('user.language', config('app.locale')), false); ?>");
    <?php endif; ?>

    var datepicker_date_format = "<?php echo e($datepicker_date_format, false); ?>";
    var moment_date_format = "<?php echo e($moment_date_format, false); ?>";
    var moment_time_format = "<?php echo e($moment_time_format, false); ?>";

    var app_locale = "<?php echo e(session()->get('user.language', config('app.locale')), false); ?>";

    var non_utf8_languages = [
        <?php $__currentLoopData = config('constants.non_utf8_languages'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $const): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            "<?php echo e($const, false); ?>",
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    ];

    var __default_datatable_page_entries = "<?php echo e($default_datatable_page_entries, false); ?>";

    var __new_notification_count_interval = "<?php echo e(config('constants.new_notification_count_interval', 60), false); ?>000";
</script>

<?php if(file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js'))): ?>
    <script src="<?php echo e(asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v), false); ?>">
    </script>
<?php else: ?>
    <script src="<?php echo e(asset('js/lang/en.js?v=' . $asset_v), false); ?>"></script>
<?php endif; ?>

<script src="<?php echo e(asset('modules/accessory/v7/js/functions.js?v=' . $asset_v), false); ?>"></script>
<script src="<?php echo e(asset('modules/accessory/v7/js/common.js?v=' . $asset_v), false); ?>"></script>
<script src="<?php echo e(asset('modules/accessory/v7/js/app.js?v=' . $asset_v), false); ?>"></script>
<script src="<?php echo e(asset('modules/accessory/v7/js/help-tour.js?v=' . $asset_v), false); ?>"></script>
<script src="<?php echo e(asset('modules/accessory/v7/js/documents_and_note.js?v=' . $asset_v), false); ?>"></script>

<!-- TODO -->
<?php if(file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js'))): ?>
    <script
        src="<?php echo e(asset('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v), false); ?>">
    </script>
<?php endif; ?>
<?php
    $validation_lang_file = 'messages_' . session()->get('user.language', config('app.locale')) . '.js';
?>
<?php if(file_exists(public_path() . '/js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file)): ?>
    <script src="<?php echo e(asset('js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file . '?v=' . $asset_v), false); ?>">
    </script>
<?php endif; ?>

<?php if(!empty($__system_settings['additional_js'])): ?>
    <?php echo $__system_settings['additional_js']; ?>

<?php endif; ?>
<?php echo $__env->yieldContent('javascript'); ?>

<?php if(Module::has('Essentials')): ?>
    <?php if ($__env->exists('essentials::layouts.partials.footer_part')) echo $__env->make('essentials::layouts.partials.footer_part', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php endif; ?>

<script type="text/javascript">
    $(document).ready(function() {
        var locale = "<?php echo e(session()->get('user.language', config('app.locale')), false); ?>";
        var isRTL =
            <?php if(in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl'))): ?>
                true;
            <?php else: ?>
                false;
            <?php endif; ?>

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

<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules/Accessory\Resources/views/layouts/partials/javascripts.blade.php ENDPATH**/ ?>