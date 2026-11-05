@if (session('status'))
    <div class="alert alert-{{ session('status.success') ? 'success' : 'danger' }} alert-dismissible" style="border-radius: 8px;">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fa fa-{{ session('status.success') ? 'check' : 'ban' }}"></i> {{ session('status.success') ? 'Success' : 'Notice' }}</h4>
        {{ session('status.msg') }}
    </div>
@endif

<div class="tw-mb-6 tw-bg-white tw-p-4 tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-200 no-print">
    <div class="tw-flex tw-flex-col md:tw-flex-row md:tw-items-center md:tw-justify-between tw-gap-4">
        <div>
            <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-flex tw-items-center tw-gap-2">
                <span class="tw-p-2 tw-bg-blue-600 tw-text-white tw-rounded-lg">
                    <i class="fa fa-line-chart"></i>
                </span>
                {{ $title ?? __('dailyfinancialmanagement::lang.daily_financial_management') }}
            </h1>
            <p class="tw-text-sm tw-text-gray-500 tw-mt-1">
                KNEAYERNG PHONE SHOP &bull; {{ __('dailyfinancialmanagement::lang.daily_financial_management') }}
            </p>
        </div>

        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
            <a href="{{ route('daily-financial.income.create') }}" class="btn btn-success btn-sm tw-font-medium tw-rounded-lg tw-shadow-sm">
                <i class="fa fa-plus-circle"></i> {{ __('dailyfinancialmanagement::lang.add_income') }}
            </a>
            <a href="{{ route('daily-financial.expense.create') }}" class="btn btn-danger btn-sm tw-font-medium tw-rounded-lg tw-shadow-sm">
                <i class="fa fa-minus-circle"></i> {{ __('dailyfinancialmanagement::lang.add_expense') }}
            </a>
            <a href="{{ route('daily-financial.closing.create') }}" class="btn btn-primary btn-sm tw-font-medium tw-rounded-lg tw-shadow-sm">
                <i class="fa fa-calendar-check-o"></i> {{ __('dailyfinancialmanagement::lang.run_daily_closing') }}
            </a>
            <a href="{{ route('daily-financial.sync.index') }}" class="btn btn-default btn-sm tw-font-medium tw-rounded-lg tw-shadow-sm">
                <i class="fa fa-refresh"></i> {{ __('dailyfinancialmanagement::lang.sync_pos_data') }}
            </a>
        </div>
    </div>
</div>

