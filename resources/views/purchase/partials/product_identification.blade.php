    @if(empty($is_purchase_order))
    <details class="purchase-product-details">
        <summary class="purchase-details-toggle">{{ __('product.product_details') }}</summary>
        <div class="purchase-details-fields">
            @foreach([
                'model_number' => 'model_number_full',
                'qa_number' => 'reference_sku_number',
                'country_of_manufacture' => 'country_of_manufacture_full',
                'network_connectivity' => 'network_connectivity_full',
            ] as $field => $label)
                @php
                    $saved_value = isset($identification_purchase_line) ? $identification_purchase_line->$field : null;
                    $field_value = $saved_value ?? ($identification_product->$field ?? '');
                    $field_value = old('purchases.' . $identification_row_index . '.' . $field, $field_value);
                @endphp
                <label class="purchase-identification-label purchase-product-{{ $field }}">
                    <span class="purchase-detail-label">{{ __('product.' . $label) }}</span>
                    <input type="text" name="purchases[{{ $identification_row_index }}][{{ $field }}]"
                        class="form-control input-sm" maxlength="255"
                        value="{{ $field_value }}" placeholder="{{ __('product.' . $label) }}">
                </label>
            @endforeach
        </div>
    </details>
    @endif
