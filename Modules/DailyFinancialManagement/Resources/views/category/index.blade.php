@extends('layouts.app')

@section('title', __('dailyfinancialmanagement::lang.financial_categories'))

@section('content')
<div class="content">
    @include('dailyfinancialmanagement::partials.header', ['title' => __('dailyfinancialmanagement::lang.financial_categories')])

    <div class="box box-solid tw-rounded-xl tw-shadow-sm">
        <div class="box-header with-border tw-flex tw-justify-between tw-items-center">
            <h3 class="box-title tw-text-base tw-font-bold tw-text-gray-900">
                Chart of Financial Categories
            </h3>
            <div class="box-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCategoryModal">
                    <i class="fa fa-plus"></i> Add New Category
                </button>
            </div>
        </div>
        <div class="box-body no-padding">
            <div class="table-responsive">
                <table class="table table-striped table-hover tw-text-sm">
                    <thead>
                        <tr class="tw-bg-gray-50">
                            <th>Code</th>
                            <th>Category Name (EN)</th>
                            <th>Category Name (KH)</th>
                            <th>Accounting Type</th>
                            <th>Parent Category</th>
                            <th class="tw-text-center">Operating?</th>
                            <th class="tw-text-center">Active?</th>
                            <th class="tw-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $cat)
                            <tr>
                                <td class="tw-font-mono tw-font-semibold">{{ $cat->code ?: '-' }}</td>
                                <td class="tw-font-medium">{{ $cat->name_en }}</td>
                                <td>{{ $cat->name_kh ?: '-' }}</td>
                                <td>
                                    <span class="label {{ in_array($cat->type, ['income', 'non_operating_income']) ? 'label-success' : 'label-danger' }}">
                                        {{ strtoupper(str_replace('_', ' ', $cat->type)) }}
                                    </span>
                                </td>
                                <td>{{ $cat->parent ? $cat->parent->name_en : '-' }}</td>
                                <td class="tw-text-center">
                                    @if ($cat->is_operating)
                                        <i class="fa fa-check text-success"></i>
                                    @else
                                        <i class="fa fa-times text-muted"></i>
                                    @endif
                                </td>
                                <td class="tw-text-center">
                                    <span class="badge {{ $cat->is_active ? 'bg-green' : 'bg-gray' }}">
                                        {{ $cat->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="tw-text-center">
                                    @if (! $cat->is_system)
                                        <form action="{{ route('daily-financial.categories.destroy', $cat->id) }}" method="POST" class="tw-inline" onsubmit="return confirm('Delete this category?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @else
                                        <span class="text-muted"><i class="fa fa-lock" title="System Category"></i></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="tw-text-center tw-text-gray-400 tw-py-6">No financial categories registered.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="box-footer">
            {{ $categories->links() }}
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal">
    <div class="modal-dialog">
        <form action="{{ route('daily-financial.categories.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Create Financial Category</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Category Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. OPEX_RENT" />
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Accounting Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="operating_expense">Operating Expense (OPEX)</option>
                                <option value="income">Commercial Revenue / Operating Income</option>
                                <option value="cost_of_goods_sold">Cost of Goods Sold (COGS)</option>
                                <option value="non_operating_income">Non-Operating Income</option>
                                <option value="non_operating_expense">Non-Operating Expense / Capital</option>
                                <option value="asset">Asset (Stock / Equipment)</option>
                                <option value="liability">Liability (Payable / Loan)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Category Name (English) <span class="text-danger">*</span></label>
                        <input type="text" name="name_en" class="form-control" placeholder="e.g. Office Rent & Leases" required />
                    </div>

                    <div class="form-group">
                        <label>Category Name (Khmer) ឈ្មោះជាភាសាខ្មែរ</label>
                        <input type="text" name="name_kh" class="form-control" placeholder="ឧទាហរណ៍៖ ថ្លៃជួលទីតាំង" />
                    </div>

                    <div class="form-group">
                        <label>Parent Category (Optional)</label>
                        <select name="parent_id" class="form-control select2">
                            <option value="">-- None (Top Level) --</option>
                            @foreach ($parentCategories as $p)
                                <option value="{{ $p->id }}">{{ $p->name_en }} ({{ strtoupper($p->type) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="is_operating" value="1" checked />
                            <strong>Is Operating Flow?</strong> (Counts towards P&L Gross/Net Profit)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

