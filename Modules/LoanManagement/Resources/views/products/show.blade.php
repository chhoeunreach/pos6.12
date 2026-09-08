@extends('loanmanagement::layouts.app')
@section('title', 'Product Details - ' . $product->name)

@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
    $meta = is_array($product->meta_json) ? $product->meta_json : (json_decode((string) $product->meta_json, true) ?: []);
    $allowedDurations = $meta['allowed_durations'] ?? [3, 6, 12, 24];
    $qty = (int) ($product->qty_available ?? 0);
    $sellingPrice = (float) $product->selling_price;
    $costPrice = (float) ($product->cost_price ?? 0);
    $margin = $sellingPrice - $costPrice;
    $marginPercent = $sellingPrice > 0 ? ($margin / $sellingPrice) * 100 : 0;
    $items = $product->items ?? collect();
@endphp

@section('loan_css')
<style>
    .product-profile-header {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 20px;
        display: flex;
        gap: 24px;
        align-items: center;
        box-shadow: 0 4px 12px rgba(15,23,42,0.03);
        flex-wrap: wrap;
    }
    .product-profile-img {
        width: 120px;
        height: 120px;
        border-radius: 12px;
        object-fit: cover;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }
    .product-profile-img-fallback {
        width: 120px;
        height: 120px;
        border-radius: 12px;
        background: #f1f5f9;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        flex-shrink: 0;
    }
    .spec-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-top: 14px;
    }
    .spec-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
    }
    .spec-item label {
        display: block;
        margin: 0;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 700;
    }
    .spec-item .spec-val {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        margin-top: 2px;
    }
    .calc-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 18px;
    }
    .term-pill {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        font-weight: 700;
        cursor: pointer;
        margin: 0 4px 6px 0;
        font-size: 12px;
        transition: all .15s ease;
    }
    .term-pill.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }
</style>
@endsection

@section('content_body')
<div class="content-header" style="margin-bottom: 15px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="margin:0; font-size: 22px; font-weight: 800; color: #0f172a;">
                <i class="fa fa-cube text-primary" style="margin-right: 8px;"></i>
                {{ $lmText('Product Details', 'ព័ត៌មានលម្អិតទំនិញ') }}
            </h1>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('loan-management.loans.create', ['product_id' => $product->id, 'product_name' => $product->name, 'principal_amount' => $product->selling_price]) }}" class="btn btn-success btn-flat" style="border-radius: 6px; font-weight: 700;">
                <i class="fa fa-plus-circle" style="margin-right: 5px;"></i> {{ $lmText('Create Installment for this Product', 'បង្កើតកម្ចីសម្រាប់ទំនិញនេះ') }}
            </a>
            <a href="{{ route('loan-management.products.edit', $product->id) }}" class="btn btn-primary btn-flat" style="border-radius: 6px; font-weight: 700;">
                <i class="fa fa-pencil" style="margin-right: 5px;"></i> {{ $lmText('Edit', 'កែប្រែ') }}
            </a>
            <a href="{{ route('loan-management.products.index') }}" class="btn btn-default btn-flat" style="border-radius: 6px; font-weight: 700;">
                <i class="fa fa-arrow-left" style="margin-right: 5px;"></i> {{ $lmText('Back to Products', 'ត្រឡប់ក្រោយ') }}
            </a>
        </div>
    </div>
</div>

{{-- Product Profile Card --}}
<div class="product-profile-header">
    @if($product->image_url)
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-profile-img">
    @else
        <div class="product-profile-img-fallback"><i class="fa fa-cube"></i></div>
    @endif
    <div style="flex: 1; min-width: 250px;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <h2 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a;">{{ $product->name }}</h2>
            @if($qty > 5)
                <span class="label label-success" style="font-size: 12px; font-weight: 700;">In Stock ({{ $qty }} units)</span>
            @elseif($qty > 0)
                <span class="label label-warning" style="font-size: 12px; font-weight: 700;">Low Stock ({{ $qty }} units)</span>
            @else
                <span class="label label-danger" style="font-size: 12px; font-weight: 700;">Out of Stock</span>
            @endif
        </div>
        <div style="margin-top: 6px; color: #64748b; font-size: 13px;">
            <strong>SKU:</strong> <code>{{ $product->sku ?: '-' }}</code> &nbsp;|&nbsp;
            <strong>Brand:</strong> {{ $product->brand ?: '-' }} &nbsp;|&nbsp;
            <strong>Category:</strong> {{ $product->category ?: '-' }} &nbsp;|&nbsp;
            <strong>Location:</strong> {{ $product->location->name ?? 'All Branches' }}
        </div>
        @if($product->description)
            <p style="margin: 10px 0 0; color: #334155; font-size: 13px; line-height: 1.5;">{{ $product->description }}</p>
        @endif
    </div>
    <div style="text-align: right; min-width: 180px;">
        <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Installment Cash Price</div>
        <div style="font-size: 32px; font-weight: 800; color: #0f172a; line-height: 1.1;">${{ number_format($sellingPrice, 2) }}</div>
        <div style="font-size: 12px; color: #16a34a; font-weight: 700; margin-top: 4px;">
            Profit Margin: ${{ number_format($margin, 2) }} ({{ number_format($marginPercent, 1) }}%)
        </div>
        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
            Cost: ${{ number_format($costPrice, 2) }}
        </div>
    </div>
</div>

{{-- Specs & Metadata Grid --}}
<div class="box box-default" style="border-radius: 8px; margin-bottom: 20px;">
    <div class="box-body" style="padding: 16px;">
        <div class="spec-grid">
            <div class="spec-item">
                <label>{{ $lmText('SKU / Code', 'កូដទំនិញ') }}</label>
                <div class="spec-val">{{ $product->sku ?: '-' }}</div>
            </div>
            <div class="spec-item">
                <label>{{ $lmText('Primary IMEI / Serial', 'លេខ IMEI / Serial') }}</label>
                <div class="spec-val">{{ $product->imei ?: '-' }}</div>
            </div>
            <div class="spec-item">
                <label>{{ $lmText('Color / Variant', 'ពណ៌') }}</label>
                <div class="spec-val">{{ $meta['color'] ?? '-' }}</div>
            </div>
            <div class="spec-item">
                <label>{{ $lmText('Storage / Specs', 'ទំហំផ្ទុក / លក្ខណៈ') }}</label>
                <div class="spec-val">{{ $meta['storage'] ?? '-' }}</div>
            </div>
            <div class="spec-item">
                <label>{{ $lmText('Min Down Payment', 'ប្រាក់កក់ទាបបំផុត') }}</label>
                <div class="spec-val text-warning">{{ $product->min_down_payment_percent }}% (${{ number_format($sellingPrice * ($product->min_down_payment_percent / 100), 2) }})</div>
            </div>
            <div class="spec-item">
                <label>{{ $lmText('Stock Units', 'ចំនួនក្នុងស្តុក') }}</label>
                <div class="spec-val text-primary">{{ $qty }} units</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Left: Installment Simulator --}}
    <div class="col-md-7">
        <div class="box box-primary" style="border-radius: 8px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: 800; font-size: 15px;">
                    <i class="fa fa-calculator text-primary"></i> {{ $lmText('Installment Simulator', 'ម៉ាស៊ីនគណនាប្រាក់បង់រំលស់ប្រចាំខែ') }}
                </h3>
            </div>
            <div class="box-body" style="padding: 20px;">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label style="font-weight: 700; font-size: 12px;">{{ $lmText('Down Payment ($)', 'ប្រាក់កក់ ($)') }}</label>
                            <input type="number" id="simDownPayment" class="form-control" value="{{ round($sellingPrice * ($product->min_down_payment_percent / 100), 2) }}" min="0" max="{{ $sellingPrice }}" step="1" oninput="calculateSimulator()">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label style="font-weight: 700; font-size: 12px;">{{ $lmText('Monthly Interest Rate (%)', 'អត្រាការប្រាក់ (% / ខែ)') }}</label>
                            <input type="number" id="simInterestRate" class="form-control" value="1.5" step="0.1" min="0" oninput="calculateSimulator()">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-weight: 700; font-size: 12px;">{{ $lmText('Select Duration (Months)', 'ជ្រើសរើសរយៈពេលបង់ (ខែ)') }}</label>
                    <div id="simDurationContainer">
                        @foreach([3, 6, 12, 18, 24, 36] as $dur)
                            <div class="term-pill {{ $dur === 12 ? 'active' : '' }}" onclick="selectTerm({{ $dur }}, this)">
                                {{ $dur }} {{ $lmText('Months', 'ខែ') }}
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Simulation Results --}}
                <div class="calc-box" style="margin-top: 15px;">
                    <div class="row" style="text-align: center;">
                        <div class="col-xs-4" style="border-right: 1px solid #e2e8f0;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">{{ $lmText('Financed Amount', 'ប្រាក់កម្ចីសុទ្ធ') }}</div>
                            <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 4px;" id="simFinancedVal">$0.00</div>
                        </div>
                        <div class="col-xs-4" style="border-right: 1px solid #e2e8f0;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">{{ $lmText('Total Interest', 'ការប្រាក់សរុប') }}</div>
                            <div style="font-size: 18px; font-weight: 800; color: #d97706; margin-top: 4px;" id="simInterestVal">$0.00</div>
                        </div>
                        <div class="col-xs-4">
                            <div style="font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase;">{{ $lmText('Monthly Due', 'បង់ប្រចាំខែ') }}</div>
                            <div style="font-size: 22px; font-weight: 800; color: #2563eb; margin-top: 2px;" id="simMonthlyVal">$0.00</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 16px; text-align: right;">
                    <a id="simCreateLoanBtn" href="{{ route('loan-management.loans.create', ['product_id' => $product->id, 'product_name' => $product->name, 'principal_amount' => $product->selling_price]) }}" class="btn btn-success btn-block btn-lg" style="font-weight: 800; border-radius: 6px;">
                        <i class="fa fa-arrow-right"></i> {{ $lmText('Proceed to Create Loan with these Terms', 'បន្តបង្កើតកម្ចីជាមួយលក្ខខណ្ឌនេះ') }}
                    </a>
                </div>

            </div>
        </div>

        {{-- Serial Numbers Tracking (if any) --}}
        @if($items->count() > 0)
            <div class="box box-default" style="border-radius: 8px;">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-weight: 800; font-size: 14px;">
                        <i class="fa fa-barcode text-primary"></i> Tracked Serials / IMEIs ({{ $items->count() }})
                    </h3>
                </div>
                <div class="box-body no-padding table-responsive">
                    <table class="table table-striped" style="margin:0; font-size: 12px;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Serial / IMEI</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $item)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td><code>{{ $item->serial_no ?: $item->imei }}</code></td>
                                    <td>
                                        <span class="label {{ $item->status === 'available' ? 'label-success' : 'label-warning' }}">
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Right: Linked Installments / Contracts --}}
    <div class="col-md-5">
        <div class="box box-default" style="border-radius: 8px;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-weight: 800; font-size: 14px;">
                    <i class="fa fa-history text-muted"></i> {{ $lmText('Recent Installments for this Product', 'កម្ចីថ្មីៗសម្រាប់ទំនិញនេះ') }}
                </h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-striped" style="margin:0; font-size: 12px;">
                    <thead>
                        <tr>
                            <th>Installment #</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLoans as $rLoan)
                            <tr>
                                <td>
                                    <a href="{{ route('loan-management.loans.view', $rLoan->id) }}" style="font-weight: 700;">
                                        {{ $rLoan->loan_number ?? '#' . $rLoan->id }}
                                    </a>
                                </td>
                                <td style="font-weight: 700;">${{ number_format($rLoan->principal_amount ?? 0, 2) }}</td>
                                <td><span class="label label-info">{{ ucfirst($rLoan->status ?? 'active') }}</span></td>
                                <td style="color: #64748b;">{{ substr((string) ($rLoan->created_at ?? ''), 0, 10) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">
                                    {{ $lmText('No loan applications linked yet.', 'មិនទាន់មានកម្ចីសម្រាប់ទំនិញនេះនៅឡើយទេ។') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
var selectedDuration = 12;
var productPrice = {{ $sellingPrice }};
var baseCreateUrl = "{{ route('loan-management.loans.create', ['product_id' => $product->id, 'product_name' => $product->name, 'principal_amount' => $product->selling_price]) }}";

function selectTerm(dur, el) {
    selectedDuration = dur;
    document.querySelectorAll('.term-pill').forEach(function(p) { p.classList.remove('active'); });
    el.classList.add('active');
    calculateSimulator();
}

function calculateSimulator() {
    var dp = parseFloat(document.getElementById('simDownPayment').value) || 0;
    var rate = (parseFloat(document.getElementById('simInterestRate').value) || 0) / 100;
    var financed = Math.max(0, productPrice - dp);
    var monthlyPrincipal = selectedDuration > 0 ? (financed / selectedDuration) : 0;
    var monthlyInterest = financed * rate;
    var monthlyDue = monthlyPrincipal + monthlyInterest;
    var totalInterest = monthlyInterest * selectedDuration;

    document.getElementById('simFinancedVal').innerText = '$' + financed.toFixed(2);
    document.getElementById('simInterestVal').innerText = '$' + totalInterest.toFixed(2);
    document.getElementById('simMonthlyVal').innerText = '$' + monthlyDue.toFixed(2) + ' / mo';

    var updatedUrl = baseCreateUrl + '&down_payment=' + dp + '&duration_months=' + selectedDuration + '&interest_rate=' + (rate * 100);
    var btn = document.getElementById('simCreateLoanBtn');
    if (btn) btn.href = updatedUrl;
}

document.addEventListener('DOMContentLoaded', calculateSimulator);
</script>
@endsection
