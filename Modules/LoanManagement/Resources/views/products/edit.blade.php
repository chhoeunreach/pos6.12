@extends('loanmanagement::layouts.app')
@section('title', 'Edit Installment Product')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $meta = is_array($product->meta_json) ? $product->meta_json : (json_decode((string) $product->meta_json, true) ?: []);
    $allowedDurations = $meta['allowed_durations'] ?? [3, 6, 12, 24];
@endphp

@section('loan_css')
<style>
    .form-section-title {
        font-size: 14px;
        font-weight: 800;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 8px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .photo-dropzone {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all .2s ease;
    }
    .photo-dropzone:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .img-preview-box {
        margin-top: 12px;
        text-align: center;
    }
    .img-preview-box img {
        max-height: 140px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
</style>
@endsection

@section('content_body')
<div class="content-header" style="margin-bottom: 15px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="margin:0; font-size: 22px; font-weight: 800; color: #0f172a;">
                <i class="fa fa-pencil text-primary" style="margin-right: 8px;"></i>
                {{ $lmText('Edit Product', 'កែប្រែទំនិញបង់រំលស់') }}: <span class="text-primary">{{ $product->name }}</span>
            </h1>
            <p style="margin: 4px 0 0; color: #64748b; font-size: 13px;">
                {{ $lmText('Update pricing, stock quantity, branch, and installment conditions.', 'កែប្រែតម្លៃ ចំនួនស្តុក សាខា និងលក្ខខណ្ឌបង់រំលស់។') }}
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('loan-management.products.show', $product->id) }}" class="btn btn-info btn-flat" style="border-radius: 6px; font-weight: 700;">
                <i class="fa fa-eye" style="margin-right: 5px;"></i> {{ $lmText('View Details', 'មើលព័ត៌មានលម្អិត') }}
            </a>
            <a href="{{ route('loan-management.products.index') }}" class="btn btn-default btn-flat" style="border-radius: 6px; font-weight: 700;">
                <i class="fa fa-arrow-left" style="margin-right: 5px;"></i> {{ $lmText('Back to Products', 'ត្រឡប់ទៅបញ្ជីទំនិញ') }}
            </a>
        </div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible" style="border-radius: 8px;">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <i class="fa fa-exclamation-triangle"></i> {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ route('loan-management.products.update', $product->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="row">
        {{-- Left Column: Main Information & Pricing --}}
        <div class="col-md-8">
            <div class="box box-primary" style="border-radius: 8px;">
                <div class="box-body" style="padding: 20px;">
                    
                    {{-- Basic Information --}}
                    <div class="form-section-title">
                        <i class="fa fa-info-circle text-primary"></i>
                        {{ $lmText('Basic Information', 'ព័ត៌មានទូទៅ') }}
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">
                            {{ $lmText('Product Name', 'ឈ្មោះទំនិញ') }} <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $product->name) }}">
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('SKU / Code', 'កូដទំនិញ (SKU)') }}</label>
                                <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Brand', 'ម៉ាកយីហោ') }}</label>
                                <input type="text" name="brand" class="form-control" value="{{ old('brand', $product->brand) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Category', 'ប្រភេទ') }}</label>
                                <input type="text" name="category" class="form-control" value="{{ old('category', $product->category) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Color / Variant', 'ពណ៌') }}</label>
                                <input type="text" name="color" class="form-control" value="{{ old('color', $meta['color'] ?? '') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Storage / Capacity / Spec', 'ទំហំផ្ទុក / លក្ខណៈពិសេស') }}</label>
                                <input type="text" name="storage" class="form-control" value="{{ old('storage', $meta['storage'] ?? '') }}">
                            </div>
                        </div>
                    </div>

                    {{-- Pricing & Installment Terms --}}
                    <div class="form-section-title" style="margin-top: 20px;">
                        <i class="fa fa-dollar text-success"></i>
                        {{ $lmText('Pricing & Installment Terms', 'តម្លៃ និងលក្ខខណ្ឌបង់រំលស់') }}
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">
                                    {{ $lmText('Selling / Installment Price ($)', 'តម្លៃលក់ / តម្លៃកម្ចី ($)') }} <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-addon" style="font-weight: 800;">$</span>
                                    <input type="number" step="0.01" min="0" name="selling_price" class="form-control input-lg" required style="font-weight: 800; color: #0f172a;" value="{{ old('selling_price', $product->selling_price) }}">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Cost Price ($)', 'តម្លៃដើម ($)') }}</label>
                                <div class="input-group">
                                    <span class="input-group-addon">$</span>
                                    <input type="number" step="0.01" min="0" name="cost_price" class="form-control input-lg" value="{{ old('cost_price', $product->cost_price) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Min Down Payment (%)', 'កក់ប្រាក់ទាបបំផុត (%)') }}</label>
                                <div class="input-group">
                                    <input type="number" step="1" min="0" max="100" name="min_down_payment_percent" class="form-control" value="{{ old('min_down_payment_percent', $product->min_down_payment_percent) }}">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Allowed Durations (Months)', 'រយៈពេលបង់រំលស់អនុញ្ញាត (ខែ)') }}</label>
                                <div style="display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap;">
                                    @foreach([3, 6, 12, 18, 24, 36] as $mo)
                                        <label style="font-weight: 600; cursor: pointer;">
                                            <input type="checkbox" name="allowed_durations[]" value="{{ $mo }}" {{ in_array($mo, old('allowed_durations', $allowedDurations)) ? 'checked' : '' }}>
                                            {{ $mo }} {{ $lmText('Mo', 'ខែ') }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="form-group" style="margin-top: 15px;">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Description & Details', 'ការពិពណ៌នា និងលក្ខណៈពិសេស') }}</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $product->description) }}</textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- Right Column: Media, Stock & Location --}}
        <div class="col-md-4">
            
            {{-- Product Photo --}}
            <div class="box box-default" style="border-radius: 8px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-size: 14px; font-weight: 800;">
                        <i class="fa fa-image text-primary"></i> {{ $lmText('Product Photo', 'រូបភាពទំនិញ') }}
                    </h3>
                </div>
                <div class="box-body" style="padding: 16px;">
                    @php $currentImg = $product->image_url; @endphp
                    @if($currentImg)
                        <div class="img-preview-box" id="currentImageBox" style="display: block; margin-bottom: 12px;">
                            <img src="{{ $currentImg }}" alt="{{ $product->name }}" style="max-height: 140px; border-radius: 8px;">
                            <div style="margin-top: 6px;">
                                <label style="color: #dc2626; font-size: 12px; font-weight: 600; cursor: pointer;">
                                    <input type="checkbox" name="remove_image" value="1"> {{ $lmText('Remove current image', 'លុបរូបភាពចាស់') }}
                                </label>
                            </div>
                        </div>
                    @endif

                    <div class="photo-dropzone" onclick="document.getElementById('productImageInput').click();">
                        <i class="fa fa-cloud-upload" style="font-size: 28px; color: #94a3b8; margin-bottom: 4px;"></i>
                        <div style="font-weight: 700; font-size: 12px; color: #334155;">{{ $lmText('Change photo', 'ផ្លាស់ប្តូររូបភាពថ្មី') }}</div>
                        <div style="font-size: 11px; color: #94a3b8;">JPG, PNG, WEBP max 5MB</div>
                    </div>
                    <input type="file" name="product_image" id="productImageInput" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    
                    <div class="img-preview-box" id="newImagePreviewBox" style="display: none;">
                        <img id="newPreviewImgElement" src="" alt="New Preview">
                    </div>
                </div>
            </div>

            {{-- Inventory & Location --}}
            <div class="box box-default" style="border-radius: 8px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-size: 14px; font-weight: 800;">
                        <i class="fa fa-building-o text-primary"></i> {{ $lmText('Stock & Branch', 'ស្តុក & សាខា') }}
                    </h3>
                </div>
                <div class="box-body" style="padding: 16px;">
                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Available Stock Quantity', 'ចំនួនស្តុកដែលមាន') }}</label>
                        <input type="number" min="0" name="qty_available" class="form-control input-lg" style="font-weight: 800;" value="{{ old('qty_available', $product->qty_available) }}">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Branch Location', 'សាខាអាជីវកម្ម') }}</label>
                        <select name="loan_business_location_id" class="form-control">
                            <option value="">{{ $lmText('-- All Branches --', '-- គ្រប់សាខាទាំងអស់ --') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ (string) old('loan_business_location_id', $product->loan_business_location_id) === (string) $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Primary IMEI / Serial', 'លេខ IMEI ឬ Serial ចម្បង') }}</label>
                        <input type="text" name="imei" class="form-control" value="{{ old('imei', $product->imei) }}">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Add More Serials / IMEIs (Optional)', 'បន្ថែមលេខ Serial/IMEI ថ្មី') }}</label>
                        <textarea name="serial_numbers" rows="2" class="form-control" placeholder="IMEI-004&#10;IMEI-005"></textarea>
                    </div>
                </div>
            </div>

            {{-- Submit CTA --}}
            <div class="box box-default" style="border-radius: 8px;">
                <div class="box-body" style="padding: 16px;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="font-weight: 800; border-radius: 6px;">
                        <i class="fa fa-save"></i> {{ $lmText('Update Installment Product', 'រក្សាទុកការកែប្រែ') }}
                    </button>
                    <a href="{{ route('loan-management.products.index') }}" class="btn btn-default btn-block" style="margin-top: 8px; font-weight: 700;">
                        {{ $lmText('Cancel', 'បោះបង់') }}
                    </a>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('newPreviewImgElement').src = e.target.result;
            document.getElementById('newImagePreviewBox').style.display = 'block';
            if (document.getElementById('currentImageBox')) {
                document.getElementById('currentImageBox').style.opacity = '0.4';
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
