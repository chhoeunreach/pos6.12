@php
    $calendarDays = $currentReportLang === 'km' ? ['អា', 'ច', 'អ', 'ព', 'ព្រ', 'សុ', 'ស'] : ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    $calendarMonths = $currentReportLang === 'km' ? ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'] : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
@endphp
const reportDataTableLanguage = {
    search: @json($t('search') . ':'),
    lengthMenu: @json($t('show_entries')),
    emptyTable: @json($t('table_empty')),
    zeroRecords: @json($t('no_data')),
    info: @json($t('table_info')),
    infoEmpty: @json($t('table_info_empty')),
    infoFiltered: @json($t('table_info_filtered')),
    processing: @json($t('processing')),
    loadingRecords: @json($t('loading')),
    paginate: {
        first: @json($t('first')), last: @json($t('last')),
        next: @json($t('next')), previous: @json($t('previous'))
    },
    aria: { sortAscending: @json($t('sort_ascending')), sortDescending: @json($t('sort_descending')) },
    buttons: {
        copy: @json($t('copy')),
        copyTitle: @json($t('copy_title')),
        copyKeys: @json($t('copy_keys')),
        copySuccess: { 1: @json($t('copy_success_one')), _: @json($t('copy_success_many')) },
        print: @json($t('print')),
        colvis: @json($t('column_visibility')),
        pageLength: { '-1': @json($t('show_all')), _: @json($t('page_length')) }
    }
};
const reportSelect2Language = {
    noResults: function () { return @json($t('select2_no_results')); },
    searching: function () { return @json($t('select2_searching')); }
};
const reportDateSettings = {
    locale: {
        applyLabel: @json($t('apply')),
        cancelLabel: @json($t('cancel')),
        fromLabel: @json($t('from')),
        toLabel: @json($t('to')),
        customRangeLabel: @json($t('custom_range')),
        daysOfWeek: @json($calendarDays),
        monthNames: @json($calendarMonths)
    }
};
const reportDateRanges = {};
reportDateRanges[@json($t('today'))] = [moment(), moment()];
reportDateRanges[@json($t('yesterday'))] = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
reportDateRanges[@json($t('last_7_days'))] = [moment().subtract(6, 'days'), moment()];
reportDateRanges[@json($t('last_30_days'))] = [moment().subtract(29, 'days'), moment()];
reportDateRanges[@json($t('this_month'))] = [moment().startOf('month'), moment().endOf('month')];
reportDateRanges[@json($t('last_month'))] = [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')];
