@extends('loanmanagement::layouts.app')
@section('title', 'Add Installment Product')

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
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
        padding: 24px;
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
        display: none;
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
                <i class="fa fa-plus-circle text-primary" style="margin-right: 8px;"></i>
                {{ $lmText('Add Installment Product', 'បន្ថែមទំនិញបង់រំលស់ថ្មី') }}
            </h1>
            <p style="margin: 4px 0 0; color: #64748b; font-size: 13px;">
                {{ $lmText('Register a product in the catalog for customer installment loan applications.', 'បញ្ចូលទំនិញទៅក្នុងកាតាឡុកសម្រាប់អតិថិជនស្នើសុំបង់រំលស់។') }}
            </p>
        </div>
        <div>
            <a href="{{ route('loan-management.products.index') }}" class="btn btn-default btn-flat" style="border-radius: 6px; font-weight: 700;">
                <i class="fa fa-arrow-left" style="margin-right: 5px;"></i> {{ $lmText('Back to Products', 'ត្រឡប់ទៅបញ្ជីទំនិញ') }}
            </a>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('loan-management.products.store') }}" enctype="multipart/form-data">
    @csrf
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
                        <input type="text" name="name" class="form-control" required placeholder="{{ $lmText('e.g. iPhone 15 Pro Max 256GB, Honda Wave 110...', 'ឧ. iPhone 15 Pro Max 256GB') }}" value="{{ old('name') }}">
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('SKU / Code', 'កូដទំនិញ (SKU)') }}</label>
                                <input type="text" name="sku" class="form-control" value="{{ old('sku', $suggestedSku) }}" placeholder="INS-1001">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Brand', 'ម៉ាកយីហោ') }}</label>
                                <input type="text" name="brand" class="form-control" value="{{ old('brand') }}" placeholder="Apple, Samsung, Honda...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Category', 'ប្រភេទ') }}</label>
                                <input type="text" name="category" class="form-control" value="{{ old('category') }}" placeholder="Smartphones, Motorcycles, Laptops...">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Color / Variant', 'ពណ៌') }}</label>
                                <input type="text" name="color" class="form-control" value="{{ old('color') }}" placeholder="Natural Titanium, Black, Blue...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Storage / Capacity / Spec', 'ទំហំផ្ទុក / លក្ខណៈពិសេស') }}</label>
                                <input type="text" name="storage" class="form-control" value="{{ old('storage') }}" placeholder="128GB, 256GB, 512GB, 125cc...">
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
                                    <input type="number" step="0.01" min="0" name="selling_price" id="sellingPriceInput" class="form-control input-lg" required style="font-weight: 800; color: #0f172a;" value="{{ old('selling_price') }}" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Cost Price ($)', 'តម្លៃដើម ($)') }}</label>
                                <div class="input-group">
                                    <span class="input-group-addon">$</span>
                                    <input type="number" step="0.01" min="0" name="cost_price" class="form-control input-lg" value="{{ old('cost_price') }}" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Min Down Payment (%)', 'កក់ប្រាក់ទាបបំផុត (%)') }}</label>
                                <div class="input-group">
                                    <input type="number" step="1" min="0" max="100" name="min_down_payment_percent" class="form-control" value="{{ old('min_down_payment_percent', 0) }}" placeholder="0">
                                    <span class="input-group-addon">%</span>
                                </div>
                                <small class="text-muted">{{ $lmText('0% = No down payment required.', '0% = មិនតម្រូវឱ្យមានប្រាក់កក់។') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 700; color: #334155;">{{ $lmText('Allowed Durations (Months)', 'រយៈពេលបង់រំលស់អនុញ្ញាត (ខែ)') }}</label>
                                <div style="display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap;">
                                    @foreach([3, 6, 12, 18, 24, 36] as $mo)
                                        <label style="font-weight: 600; cursor: pointer;">
                                            <input type="checkbox" name="allowed_durations[]" value="{{ $mo }}" {{ in_array($mo, old('allowed_durations', [3, 6, 12, 24])) ? 'checked' : '' }}>
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
                        <textarea name="description" rows="3" class="form-control" placeholder="{{ $lmText('Optional description, warranties, product condition...', 'កំណត់ចំណាំ ការធានា ឬស្ថានភាពទំនិញ...') }}">{{ old('description') }}</textarea>
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
                    <div class="photo-dropzone" onclick="document.getElementById('productImageInput').click();">
                        <i class="fa fa-cloud-upload" style="font-size: 32px; color: #94a3b8; margin-bottom: 6px;"></i>
                        <div style="font-weight: 700; font-size: 13px; color: #334155;">{{ $lmText('Click or drag photo here', 'ចុច ឬអូសរូបភាពមកទីនេះ') }}</div>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">JPG, PNG, WEBP max 5MB</div>
                    </div>
                    <input type="file" name="product_image" id="productImageInput" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    
                    <div class="img-preview-box" id="imagePreviewBox">
                        <img id="previewImgElement" src="" alt="Preview">
                        <div style="margin-top: 6px;">
                            <button type="button" class="btn btn-xs btn-danger" onclick="clearPreview()"><i class="fa fa-trash"></i> {{ $lmText('Remove', 'ដករូបភាព') }}</button>
                        </div>
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
                        <input type="number" min="0" name="qty_available" class="form-control input-lg" style="font-weight: 800;" value="{{ old('qty_available', 1) }}">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Branch Location', 'សាខាអាជីវកម្ម') }}</label>
                        <select name="loan_business_location_id" class="form-control">
                            <option value="">{{ $lmText('-- All Branches --', '-- គ្រប់សាខាទាំងអស់ --') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ (string) old('loan_business_location_id') === (string) $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Primary IMEI / Serial (Optional)', 'លេខ IMEI ឬ Serial ចម្បង') }}</label>
                        <input type="text" name="imei" class="form-control" value="{{ old('imei') }}" placeholder="356890123456789">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; color: #334155;">{{ $lmText('Multiple Serials / IMEIs (One per line)', 'លេខ Serial/IMEI ច្រើនគ្រឿង (មួយជួរមួយ)') }}</label>
                        <textarea name="serial_numbers" rows="3" class="form-control" placeholder="IMEI-001&#10;IMEI-002&#10;IMEI-003">{{ old('serial_numbers') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Submit CTA --}}
            <div class="box box-default" style="border-radius: 8px;">
                <div class="box-body" style="padding: 16px;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="font-weight: 800; border-radius: 6px;">
                        <i class="fa fa-check-circle"></i> {{ $lmText('Save Installment Product', 'រក្សាទុកទំនិញបង់រំលស់') }}
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
            document.getElementById('previewImgElement').src = e.target.result;
            document.getElementById('imagePreviewBox').style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
function clearPreview() {
    document.getElementById('productImageInput').value = '';
    document.getElementById('imagePreviewBox').style.display = 'none';
}
</script>
@endsection
