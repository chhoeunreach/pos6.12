@extends('layouts.app')

@section('title', __('messages.settings'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
	<h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang( 'messages.settings' )</h1>
</section>
<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="nav-tabs-custom">
				<ul class="nav nav-tabs">
					<li class="active">
						<a href="#account_setting" data-toggle="tab" aria-expanded="true">
							@lang('accounting::lang.account_setting') / @lang('accounting::lang.map_transactions')
						</a>
					</li>

					<li>
						<a href="#sub_type_tab" data-toggle="tab" aria-expanded="true">
							@lang('accounting::lang.account_sub_type')
						</a>
					</li>
					<li>
						<a href="#detail_type_tab" data-toggle="tab" aria-expanded="true">
							@lang('accounting::lang.detail_type')
						</a>
					</li>
				</ul>
				<div class="tab-content">

					<div class="tab-pane active" id="account_setting">
						{!! Form::open(['action' => '\Modules\Accounting\Http\Controllers\SettingsController@saveSettings',
						'method' => 'post']) !!}
						<div class="row mb-12">
							<div class="col-md-4">
								<button type="button" class="tw-dw-btn tw-dw-btn-error tw-text-white tw-dw-btn-sm accounting_reset_data" data-href="{{action([\Modules\Accounting\Http\Controllers\SettingsController::class, 'resetData'])}}">
									@lang('accounting::lang.reset_data')
								</button>
							</div>
						</div>
						<br>

						<div class="row">
							<div class="col-md-3">
								<div class="form-group">
									{!! Form::label('journal_entry_prefix', __('accounting::lang.journal_entry_prefix') . ':') !!}
									{!! Form::text('journal_entry_prefix',!empty($accounting_settings['journal_entry_prefix'])?
									$accounting_settings['journal_entry_prefix'] : '',
									['class' => 'form-control ', 'id' => 'journal_entry_prefix']); !!}
								</div>
							</div>
							<div class="col-md-3">
								<div class="form-group">
									{!! Form::label('transfer_prefix', __('accounting::lang.transfer_prefix') . ':') !!}
									{!! Form::text('transfer_prefix',!empty($accounting_settings['transfer_prefix'])?
									$accounting_settings['transfer_prefix'] : '',
									['class' => 'form-control ', 'id' => 'transfer_prefix']); !!}
								</div>
							</div>
						</div>

						<hr />

						<h3>@lang('accounting::lang.map_transactions') @show_tooltip(__('accounting::lang.map_transactions_help'))</h3>

						@foreach($business_locations as $business_location)
						@component('components.widget', ['title' => $business_location->name])

						@php
						$default_map = $business_location->accounting_default_map_array ?? [];
						$account_options = function ($account_id) use ($selected_account_options) {
							return !empty($account_id) && isset($selected_account_options[$account_id])
								? [$account_id => $selected_account_options[$account_id]]
								: [];
						};

						$sale_payment_account_id = $default_map['sale']['payment_account'] ?? null;
						$sale_deposit_to_id = $default_map['sale']['deposit_to'] ?? null;
						$sales_payments_payment_account_id = $default_map['sell_payment']['payment_account'] ?? null;
						$sales_payments_deposit_to_id = $default_map['sell_payment']['deposit_to'] ?? null;
						$purchases_payment_account_id = $default_map['purchases']['payment_account'] ?? null;
						$purchases_deposit_to_id = $default_map['purchases']['deposit_to'] ?? null;
						$purchase_payments_payment_account_id = $default_map['purchase_payment']['payment_account'] ?? null;
						$purchase_payments_deposit_to_id = $default_map['purchase_payment']['deposit_to'] ?? null;
						$expense_payment_account_id = $default_map['expense']['payment_account'] ?? null;
						$expense_deposit_to_id = $default_map['expense']['deposit_to'] ?? null;

						@endphp

						<strong>@lang('sale.sale')</strong>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('payment_account', __('accounting::lang.payment_account') . ':' ) !!}
									{!! Form::select('payment_account', $account_options($sale_payment_account_id), $sale_payment_account_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.payment_account'), 'name' => "accounting_default_map[$business_location->id][sale][payment_account]",
									'id' => $business_location->id . 'sale_payment_account']); !!}
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':' ) !!}
									{!! Form::select('deposit_to', $account_options($sale_deposit_to_id), $sale_deposit_to_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.deposit_to'), 'name' => "accounting_default_map[$business_location->id][sale][deposit_to]",
									'id' => $business_location->id . '_sale_deposit_to']); !!}
								</div>
							</div>
						</div>

						<hr>

						<strong>@lang('accounting::lang.sales_payments')</strong>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('payment_account', __('accounting::lang.payment_account') . ':' ) !!}
									{!! Form::select('payment_account', $account_options($sales_payments_payment_account_id), $sales_payments_payment_account_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.payment_account'), 'name' => "accounting_default_map[$business_location->id][sell_payment][payment_account]", 'id' => $business_location->id . 'sales_payments_payment_account']); !!}
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':' ) !!}
									{!! Form::select('deposit_to', $account_options($sales_payments_deposit_to_id), $sales_payments_deposit_to_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.deposit_to'), 'name' => "accounting_default_map[$business_location->id][sell_payment][deposit_to]",
									'id' => $business_location->id . 'sales_payments_deposit_to'
									]); !!}
								</div>
							</div>
						</div>

						<hr>
						<strong>@lang('purchase.purchases')</strong>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('payment_account', __('accounting::lang.payment_account') . ':' ) !!}
									{!! Form::select('payment_account', $account_options($purchases_payment_account_id), $purchases_payment_account_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.payment_account'), 'name' => "accounting_default_map[$business_location->id][purchases][payment_account]",
									'id' => $business_location->id . 'purchases_payment_account']); !!}
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':' ) !!}
									{!! Form::select('deposit_to', $account_options($purchases_deposit_to_id), $purchases_deposit_to_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.deposit_to'), 'name' => "accounting_default_map[$business_location->id][purchases][deposit_to]",
									'id' => $business_location->id . '_purchases_deposit_to']); !!}
								</div>
							</div>
						</div>

						<hr>
						<strong>@lang('accounting::lang.purchase_payments')</strong>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('payment_account', __('accounting::lang.payment_account') . ':' ) !!}
									{!! Form::select('payment_account', $account_options($purchase_payments_payment_account_id), $purchase_payments_payment_account_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.payment_account'), 'name' => "accounting_default_map[$business_location->id][purchase_payment][payment_account]",
									'id' => $business_location->id . 'purchase_payments_payment_account']); !!}
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									{!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':' ) !!}
									{!! Form::select('deposit_to', $account_options($purchase_payments_deposit_to_id), $purchase_payments_deposit_to_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.deposit_to'), 'name' => "accounting_default_map[$business_location->id][purchase_payment][deposit_to]",
									'id' => $business_location->id . '_purchase_payments_deposit_to']); !!}
								</div>
							</div>
						</div>
						<hr>
						<div style="background-color: #2dce89 !important; padding:10px">
							<strong>@lang('accounting::lang.expenses')</strong>
							<div class="row m-2">
								<div class="col-md-6">
									<div class="form-group">
										{!! Form::label('payment_account', __('accounting::lang.payment_account') . ':' ) !!}
										{!! Form::select('payment_account', $account_options($expense_payment_account_id), $expense_payment_account_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.payment_account'), 'name' => "accounting_default_map[$business_location->id][expense][payment_account]",
										'id' => $business_location->id . 'expense_payment_account']); !!}
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										{!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':' ) !!}
										{!! Form::select('deposit_to', $account_options($expense_deposit_to_id), $expense_deposit_to_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.deposit_to'), 'name' => "accounting_default_map[$business_location->id][expense][deposit_to]",
										'id' => $business_location->id . '_expense_deposit_to']); !!}
									</div>
								</div>
							</div>
	
							@foreach ($expence_categories as $expence_category)
							@php
								$dynamic_variable_payment_account_id = $default_map['expense_'.$expence_category->id]['payment_account'] ?? null;
							@endphp
							<strong>@lang('accounting::lang.expenses') {{ $expence_category->name }}</strong>
							<div class="row m-2">
								<div class="col-md-6"> 
									<div class="form-group">
										{!! Form::label('payment_account', __('accounting::lang.payment_account') . ':' ) !!}
										{!! Form::select('payment_account', $account_options($dynamic_variable_payment_account_id), $dynamic_variable_payment_account_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.payment_account'), 'name' => "accounting_default_map[$business_location->id][expense_$expence_category->id][payment_account]", 'id' => $business_location->id . 'expense_'.$expence_category->id .'_payment_account']); !!}
									</div>
								</div>
								@php	
									$dynamic_variable_deposit_to_id = $default_map['expense_'.$expence_category->id]['deposit_to'] ?? null;
								@endphp
								<div class="col-md-6">
									<div class="form-group">
										{!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':' ) !!}
										{!! Form::select('deposit_to', $account_options($dynamic_variable_deposit_to_id), $dynamic_variable_deposit_to_id, ['class' => 'form-control accounts-dropdown accounts-dropdown-lazy width-100','placeholder' => __('accounting::lang.deposit_to'), 'name' => "accounting_default_map[$business_location->id][expense_$expence_category->id][deposit_to]",
										'id' => $business_location->id . '_expense_'.$expence_category->id.'_deposit_to']); !!}
									</div>
								</div>
							</div>
						@endforeach
						</div>
						@endcomponent
						@endforeach

						<div class="row">
							<div class="col-md-12 text-center">
								<div class="form-group">
									{{Form::submit(__('messages.update'), ['class'=>"tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-lg"])}}
								</div>
							</div>
						</div>
						{!! Form::close() !!}
					</div>



					<div class="tab-pane" id="sub_type_tab">
						<div class="row">
							<div class="col-md-12">
								<button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"id="add_account_sub_type" >
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
										class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
										<path stroke="none" d="M0 0h24v24H0z" fill="none" />
										<path d="M12 5l0 14" />
										<path d="M5 12l14 0" />
									</svg> @lang('messages.add')
								</button>
							</div>
							<div class="col-md-12">
								<br>
								<table class="table table-bordered table-striped" id="account_sub_type_table">
									<thead>
										<tr>
											<th>
												@lang('accounting::lang.account_sub_type')
											</th>
											<th>
												@lang('accounting::lang.account_type')
											</th>
											<th>
												@lang('messages.action')
											</th>
										</tr>
									</thead>
								</table>
							</div>
						</div>
					</div>
					<div class="tab-pane" id="detail_type_tab">
						<div class="row">
							<div class="col-md-12">
								<button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"id="add_detail_type" >
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
										class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
										<path stroke="none" d="M0 0h24v24H0z" fill="none" />
										<path d="M12 5l0 14" />
										<path d="M5 12l14 0" />
									</svg> @lang('messages.add')
								</button>
							</div>
							<div class="col-md-12">
								<br>
								<table class="table table-striped" id="detail_type_table" style="width: 100%;">
									<thead>
										<tr>
											<th>
												@lang('accounting::lang.detail_type')
											</th>
											<th>
												@lang('accounting::lang.parent_type')
											</th>
											<th>
												@lang('lang_v1.description')
											</th>
											<th>
												@lang('messages.action')
											</th>
										</tr>
									</thead>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
@include('accounting::account_type.create')
<div class="modal fade" id="edit_account_type_modal" tabindex="-1" role="dialog">
</div>
@stop

@section('javascript')

@include('accounting::accounting.common_js')

<script type="text/javascript">
	var account_sub_type_table = null;
	var detail_type_table = null;

	function init_account_sub_type_table() {
		if (account_sub_type_table) {
			return;
		}

		account_sub_type_table = $('#account_sub_type_table').DataTable({
			processing: true,
			serverSide: true,
			ajax: "{{action([\Modules\Accounting\Http\Controllers\AccountTypeController::class, 'index'])}}?account_type=sub_type",
			columnDefs: [{
				targets: [2],
				orderable: false,
				searchable: false,
			}, ],
			columns: [{
					data: 'name',
					name: 'name'
				},
				{
					data: 'account_primary_type',
					name: 'account_primary_type'
				},
				{
					data: 'action',
					name: 'action'
				},
			],
		});
	}

	function init_detail_type_table() {
		if (detail_type_table) {
			return;
		}

		detail_type_table = $('#detail_type_table').DataTable({
			processing: true,
			serverSide: true,
			ajax: "{{action([\Modules\Accounting\Http\Controllers\AccountTypeController::class, 'index'])}}?account_type=detail_type",
			columnDefs: [{
				targets: 3,
				orderable: false,
				searchable: false,
			}, ],
			columns: [{
					data: 'name',
					name: 'name'
				},
				{
					data: 'parent_type',
					name: 'parent_type'
				},
				{
					data: 'description',
					name: 'description'
				},
				{
					data: 'action',
					name: 'action'
				},
			],
		});
	}

	$(document).ready(function() {
		$('a[href="#sub_type_tab"]').one('shown.bs.tab', function() {
			init_account_sub_type_table();
		});

		$('a[href="#detail_type_tab"]').one('shown.bs.tab', function() {
			init_detail_type_table();
		});

		$('#add_account_sub_type').click(function() {
			init_account_sub_type_table();
			$('#account_type').val('sub_type')
			$('#account_type_title').text("{{__('accounting::lang.add_account_sub_type')}}");
			$('#description_div').addClass('hide');
			$('#parent_id_div').addClass('hide');
			$('#account_type_div').removeClass('hide');
			$('#create_account_type_modal').modal('show');
		});

		$('#add_detail_type').click(function() {
			init_detail_type_table();
			$('#account_type').val('detail_type')
			$('#account_type_title').text("{{__('accounting::lang.add_detail_type')}}");
			$('#description_div').removeClass('hide');
			$('#parent_id_div').removeClass('hide');
			$('#account_type_div').addClass('hide');
			$('#create_account_type_modal').modal('show');
		})
	});
	$(document).on('hidden.bs.modal', '#create_account_type_modal', function(e) {
		$('#create_account_type_form')[0].reset();
	})
	$(document).on('submit', 'form#create_account_type_form', function(e) {
		e.preventDefault();
		var form = $(this);
		var data = form.serialize();

		$.ajax({
			method: 'POST',
			url: $(this).attr('action'),
			dataType: 'json',
			data: data,
			success: function(result) {
				if (result.success == true) {
					$('#create_account_type_modal').modal('hide');
					toastr.success(result.msg);
					if (result.data.account_type == 'sub_type') {
						if (account_sub_type_table) {
							account_sub_type_table.ajax.reload();
						}
					} else {
						if (detail_type_table) {
							detail_type_table.ajax.reload();
						}
					}
					$('#create_account_type_form').find('button[type="submit"]').attr('disabled', false);
				} else {
					toastr.error(result.msg);
				}
			},
		});
	});

	$(document).on('submit', 'form#edit_account_type_form', function(e) {
		e.preventDefault();
		var form = $(this);
		var data = form.serialize();

		$.ajax({
			method: 'PUT',
			url: $(this).attr('action'),
			dataType: 'json',
			data: data,
			success: function(result) {
				if (result.success == true) {
					$('#edit_account_type_modal').modal('hide');
					toastr.success(result.msg);
					if (result.data.account_type == 'sub_type') {
						if (account_sub_type_table) {
							account_sub_type_table.ajax.reload();
						}
					} else {
						if (detail_type_table) {
							detail_type_table.ajax.reload();
						}
					}

				} else {
					toastr.error(result.msg);
				}
			},
		});
	});

	$(document).on('click', 'button.delete_account_type_button', function() {
		swal({
			title: LANG.sure,
			icon: 'warning',
			buttons: true,
			dangerMode: true,
		}).then(willDelete => {
			if (willDelete) {
				var href = $(this).data('href');
				var data = $(this).serialize();

				$.ajax({
					method: 'DELETE',
					url: href,
					dataType: 'json',
					data: data,
					success: function(result) {
						if (result.success == true) {
							toastr.success(result.msg);
							if (account_sub_type_table) {
								account_sub_type_table.ajax.reload();
							}
							if (detail_type_table) {
								detail_type_table.ajax.reload();
							}
						} else {
							toastr.error(result.msg);
						}
					},
				});
			}
		});
	});

	$(document).on('click', 'button.accounting_reset_data', function() {
		swal({
			title: LANG.sure,
			icon: 'warning',
			text: "@lang('accounting::lang.reset_help_txt')",
			buttons: true,
			dangerMode: true,
		}).then(willDelete => {
			if (willDelete) {
				var href = $(this).data('href');
				window.location.href = href;
			}
		});
	});
</script>
@endsection
