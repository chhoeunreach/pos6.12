@php
    $displayTitle = $title ?? '';
    if (empty($displayTitle) || $displayTitle === 'report.filters' || $displayTitle === 'report.filter') {
        $displayTitle = session('user.language', config('app.locale')) === 'km' ? 'តម្រងស្វែងរក' : 'Filters';
    }
    $filterId = 'collapseFilter_' . ($id ?? md5($displayTitle . uniqid()));
    $isClosed = isset($closed) ? (bool) $closed : true;
@endphp
<div class="box @if(!empty($class)) {{ $class }} @else box-solid @endif lm-pos-filter-component" id="accordion_{{ $filterId }}" style="border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">
    <div class="box-header with-border js-filter-header" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; user-select: none; background: #ffffff; border-radius: 8px 8px 0 0;" data-toggle="collapse" data-target="#{{ $filterId }}" aria-expanded="{{ $isClosed ? 'false' : 'true' }}">
        <h3 class="box-title" style="font-size: 14.5px; font-weight: 700; color: #1e293b; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
            <a href="#{{ $filterId }}" data-toggle="collapse" style="color: #1e293b; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                @if(!empty($icon))
                    {!! $icon !!}
                @else
                    <i class="fa fa-filter text-primary" aria-hidden="true" style="color: #0284c7;"></i>
                @endif
                <span>{{ $displayTitle }}</span>
            </a>
        </h3>
        <div class="box-tools pull-right" style="display: flex; align-items: center; gap: 8px;">
            {{ $tool ?? '' }}
            <button type="button" class="btn btn-box-tool" style="color: #64748b; font-size: 12px; padding: 2px 6px;">
                <i class="fa {{ $isClosed ? 'fa-chevron-down' : 'fa-chevron-up' }} filter-chevron"></i>
            </button>
        </div>
    </div>
    <div id="{{ $filterId }}" class="panel-collapse collapse {{ $isClosed ? '' : 'in' }}" aria-expanded="{{ $isClosed ? 'false' : 'true' }}" style="transition: height 0.25s ease;">
        <div class="box-body" style="padding: 16px; background: #ffffff; border-top: 1px solid #f1f5f9; border-radius: 0 0 8px 8px;">
            {{ $slot }}
        </div>
    </div>
</div>

<script>
    if (typeof window.lmFilterCollapseBound === 'undefined') {
        window.lmFilterCollapseBound = true;
        document.addEventListener('DOMContentLoaded', function() {
            if (window.$) {
                $(document).on('show.bs.collapse', '.lm-pos-filter-component .panel-collapse', function() {
                    $(this).closest('.lm-pos-filter-component').find('.filter-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                });
                $(document).on('hide.bs.collapse', '.lm-pos-filter-component .panel-collapse', function() {
                    $(this).closest('.lm-pos-filter-component').find('.filter-chevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                });
            }
        });
    }
</script>
