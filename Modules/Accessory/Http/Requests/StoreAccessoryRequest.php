<?php

namespace Modules\Accessory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $business_id = auth()->user()->business_id ?? session('user.business_id');
        $uniqueSku = Rule::unique('accessories', 'sku')->where(function ($query) use ($business_id) {
            return $query->where('business_id', $business_id);
        });

        return [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:255', $uniqueSku],
            'model' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ];
    }
}
