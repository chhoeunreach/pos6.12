@if(! empty($assetGallery))
    <div class="loan-asset-gallery-grid">
        @foreach($assetGallery as $asset)
            <button type="button" class="loan-asset-gallery-item" data-path="{{ $asset['path'] }}" data-url="{{ $asset['url'] }}">
                <img src="{{ $asset['url'] }}" alt="{{ $asset['name'] }}" loading="lazy" onerror="this.style.display='none';">
                <span class="loan-asset-gallery-name" title="{{ $asset['name'] }}">{{ $asset['name'] }}</span>
                <span class="loan-asset-gallery-date">{{ $asset['modified'] }}</span>
            </button>
        @endforeach
    </div>
@else
    <div class="alert alert-info" style="margin-bottom:0;">No existing images found yet.</div>
@endif
