
@if ($is_installed)
    <div class="item-link-module-container">
        <div class="kn-links">
            @foreach ($kneayerng_links as $link)
                <a href="{{ $link['url'] }}" target="_blank" class="kneayerng-links-item" title="{{ $link['name'] }}">
                    <i class="{{ $link['icon'] }}"></i>
                    <span>{{ $link['name'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
