<div class="col-md-6">
    @component('service::components.widget')
        @include('service::report.partials.opening_stock_report_table')
    @endcomponent
</div>

<div class="col-md-6">
    @component('service::components.widget')
        @include('service::report.partials.clossing_stock_report_table')
    @endcomponent
</div>
<br>
<div class="col-xs-12">
    @component('service::components.widget')
        @include('service::report.partials.net_gross_profit_report_details')
    @endcomponent
</div>
