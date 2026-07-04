
<div class="item-link-module-container">
    <div class="kn-links">
        @foreach ($kneayerng_links as $link)
            <a href="{{ $link['url'] }}" target="_blank" class="kneayerng-links-item" title="{{ $link['name'] }}">
                @if(isset($link['icon']) && $link['icon'])
                    <i class="{{ $link['icon'] }}"></i>
                @endif
                <span>{{ $link['name'] }}</span>
            </a>
        @endforeach
    </div>
</div>

