@php
    $navigationSections = \Modules\LoanManagement\Helpers\LoanMenuHelper::navigationSections($loanBadgeCounts ?? \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts());
@endphp
@foreach($navigationSections as $section)
    @php $sectionActive = collect($section['items'])->contains(fn ($item) => \Modules\LoanManagement\Helpers\LoanMenuHelper::navigationItemActive($item)); @endphp
    <li class="treeview installment-section section-{{ $section['key'] }} {{ $sectionActive ? 'active menu-open' : '' }}" data-section="{{ $section['key'] }}">
        <a href="#"><span>{{ $section['label'] }}</span><i class="fa fa-angle-left pull-right" aria-hidden="true"></i></a>
        <ul class="treeview-menu" style="display:{{ $sectionActive ? 'block' : 'none' }};">
            @foreach($section['items'] as $item)
                @php $itemActive = \Modules\LoanManagement\Helpers\LoanMenuHelper::navigationItemActive($item); @endphp
                <li class="{{ $itemActive ? 'active' : '' }}">
                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" title="{{ $item['label'] }}" @if($itemActive) aria-current="page" @endif>
                        <i class="{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                        @if(!empty($item['badge'])) <span class="label label-danger pull-right">{{ number_format($item['badge']) }}</span> @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@endforeach
