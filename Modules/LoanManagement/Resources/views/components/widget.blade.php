<div class="box {{ $class ?? '' }}">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $title ?? '' }}</h3>
        @isset($tool)
            {!! $tool !!}
        @endisset
    </div>
    <div class="box-body">
        {{ $slot }}
    </div>
</div>
