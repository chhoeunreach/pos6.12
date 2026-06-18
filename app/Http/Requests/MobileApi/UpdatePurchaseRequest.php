<?php

namespace App\Http\Requests\MobileApi;

class UpdatePurchaseRequest extends CreatePurchaseRequest
{
    public function authorize()
    {
        return auth()->user()->can('purchase.update');
    }
}
