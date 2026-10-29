<div class="lm-partner-row">
    <div class="lm-partner-fields">
        <label>Brand / Partner Name
            <input class="lm-input" type="text" data-field="name" name="home_cms[brands_items][{{ $index }}][name]" maxlength="100" required value="{{ $partner['name'] ?? '' }}">
        </label>
        <label>Logo URL
            <input class="lm-input" type="url" data-field="logo_url" name="home_cms[brands_items][{{ $index }}][logo_url]" maxlength="2048" pattern="https?://.*" value="{{ $partner['logo_url'] ?? '' }}">
        </label>
        <label>Website URL
            <input class="lm-input" type="url" data-field="website_url" name="home_cms[brands_items][{{ $index }}][website_url]" maxlength="2048" pattern="https?://.*" value="{{ $partner['website_url'] ?? '' }}">
        </label>
    </div>
    <div class="lm-partner-actions">
        <input type="hidden" name="home_cms[brands_items][{{ $index }}][enabled]" value="0">
        <label><input type="checkbox" data-field="enabled" name="home_cms[brands_items][{{ $index }}][enabled]" value="1" @checked($partner['enabled'] ?? true)> Visible</label>
        <button type="button" class="btn btn-default" data-action="up" title="Move up" aria-label="Move up"><i class="fa fa-arrow-up"></i></button>
        <button type="button" class="btn btn-default" data-action="down" title="Move down" aria-label="Move down"><i class="fa fa-arrow-down"></i></button>
        <button type="button" class="btn btn-default" data-action="remove" title="Remove brand or partner" aria-label="Remove brand or partner"><i class="fa fa-trash"></i></button>
    </div>
</div>
