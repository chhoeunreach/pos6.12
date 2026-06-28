@forelse ($reports as $report)
    @include('sale_pos.partials.hr_sell_list_report_row', [
        'report' => $report,
        'line_status' => $line_status,
        'show_copy_button' => $show_copy_button,
        /* Sequential index across the active/added tab (server-rendered rows + AJAX-appended rows).
           The page counter hidden input is the source of truth for the running count. */
        'row_index' => isset($row_index) ? ((int) $row_index + $loop->iteration) : $loop->iteration,
    ])
@empty
    {{-- caller is responsible for rendering its own empty state; this partial only renders rows. --}}
@endforelse