@if(($__is_repair_enabled ?? false) || (Module::has('Repair') && Module::find('Repair')->isEnabled()))
	@can("repair.create")
		<a 
		class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-bg-white hover:tw-bg-white/60 tw-cursor-pointer tw-border-2 tw-w-auto tw-h-auto tw-py-1 tw-px-4 active:tw-scale-95 tw-transition-transform tw-rounded-md pull-right"
		href="{{ repair_route('pos.create', ['sub_type' => 'repair']) }}" title="{{ __('repair::lang.add_repair') }}" data-toggle="tooltip" data-placement="bottom">
			<strong class="tw-inline-flex tw-items-center tw-gap-1.5">
				<svg aria-hidden="true" class="tw-text-[#646EE4]" width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
					stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
					stroke-linejoin="round">
					<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
					<path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5"></path>
				</svg>
				@lang('repair::lang.repair')
			</strong>
		</a>
	@endcan
@endif
