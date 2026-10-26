@foreach(['model_number', 'qa_number', 'country_of_manufacture', 'network_connectivity'] as $field)
    <div class="col-sm-3">
        <div class="form-group @error($field) has-error @enderror">
            {!! Form::label($field, __('product.' . $field) . ':') !!}
            {!! Form::text($field, old($field, $identification_product->$field ?? null), [
                'class' => 'form-control',
                'maxlength' => 255,
                'placeholder' => __('product.' . $field),
            ]) !!}
            @error($field)
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
@endforeach
