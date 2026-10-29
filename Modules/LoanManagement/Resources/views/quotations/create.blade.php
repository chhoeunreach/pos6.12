@extends('loanmanagement::layouts.app')
@section('title', 'Create Installment Quotation')
@section('hide_breadcrumb', '1')

@section('loan_css')
<style>
    .qtn-form-shell {
        font-family: 'Kantumruy Pro', -apple-system, sans-serif;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .qtn-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        padding: 24px;
    }
    .qtn-section-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .calc-box {
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 16px;
    }
    .calc-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 13px;
    }
    .calc-row.total {
        font-weight: 800;
        font-size: 16px;
        color: #0f172a;
        padding-top: 8px;
        border-top: 1px solid #cbd5e1;
    }
</style>
@endsection

@section('content_body')
@php
    $isEditing = isset($quotation);
    $quotation = $quotation ?? null;
    $field = function ($name, $default = '') use ($quotation, $isEditing) {
        $value = $isEditing ? ($quotation->$name ?? $default) : $default;
        if ($value instanceof \DateTimeInterface) { $value = $value->format('Y-m-d'); }
        return old($name, $value);
    };
    $formItems = old('items', $isEditing ? $quotation->items->map(fn ($item) => [
        'product_id' => $item->product_id, 'product_name' => $item->product_name_snapshot,
        'sku' => $item->sku_snapshot, 'serial_number' => $item->serial_number_snapshot,
        'description' => $item->description, 'photo_path' => $item->photo_path,
        'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
        'discount_amount' => $item->discount_amount, 'line_total' => $item->line_total,
    ])->all() : [['product_name' => '', 'quantity' => 1, 'unit_price' => 500, 'line_total' => 500]]);
    $formItems = array_values((array) $formItems);
@endphp
<div class="qtn-form-shell">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h2 style="margin:0;font-size:20px;font-weight:800;color:#0f172a;">
                <i class="fa {{ $isEditing ? 'fa-pencil' : 'fa-plus-circle' }} text-primary"></i> {{ $isEditing ? 'Edit Quotation: '.$quotationNo : 'New Installment Quotation / បង្កើតសម្រង់តម្លៃថ្មី' }}
            </h2>
            <p style="margin:4px 0 0 0;color:#64748b;font-size:13px;">Create an installment offer for customer before converting to an active loan contract.</p>
        </div>
        <div>
            <a href="{{ route('loan-management.quotations.index') }}" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $isEditing ? route('loan-management.quotations.update', $quotation->id) : route('loan-management.quotations.store') }}" id="quotationForm">
        @csrf
        @if($isEditing) @method('PUT') @endif

        <div class="row">
            <!-- LEFT COLUMN: CUSTOMER & PRODUCT ITEMS -->
            <div class="col-md-7">
                <!-- CUSTOMER SECTION -->
                <div class="qtn-card" style="margin-bottom:20px;">
                    <div class="qtn-section-title">
                        <i class="fa fa-user text-primary"></i> 1. Customer Information / ព័ត៌មានអតិថិជន
                    </div>

                    <div class="form-group">
                        <label>Existing Customer / ជ្រើសរើសអតិថិជនមានស្រាប់</label>
                        <select name="customer_id" id="customer_id" class="form-control">
                            <option value="">-- Type New Customer Or Select Below --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected((string) $field('customer_id') === (string) $c->id) data-name="{{ $c->name }}" data-phone="{{ $c->phone }}" data-address="{{ $c->address }}">
                                    {{ $c->name }} ({{ $c->phone }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name_snapshot" id="customer_name" class="form-control" value="{{ $field('customer_name_snapshot') }}" required placeholder="Full Name">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="customer_phone_snapshot" id="customer_phone" class="form-control" value="{{ $field('customer_phone_snapshot') }}" required placeholder="e.g. 012 345 678">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Address / អាសយដ្ឋាន</label>
                        <textarea name="customer_address_snapshot" id="customer_address" class="form-control" rows="2" placeholder="Customer address...">{{ $field('customer_address_snapshot') }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Branch / Location</label>
                                <select name="business_location_id" class="form-control">
                                    @foreach($locations as $loc)
                                        <option value="{{ $loc->id }}" @selected((string) $field('business_location_id') === (string) $loc->id)>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                <label>Quotation Date</label>
                                <input type="date" name="quotation_date" class="form-control" value="{{ $field('quotation_date', date('Y-m-d')) }}" required>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                <label>Valid Until</label>
                                <input type="date" name="valid_until" class="form-control" value="{{ $field('valid_until', date('Y-m-d', strtotime('+30 days'))) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ITEMS SECTION -->
                <div class="qtn-card">
                    <div class="qtn-section-title" style="justify-content:space-between;">
                        <span><i class="fa fa-cubes text-primary"></i> 2. Quotation Items / ទំនិញ ឬកម្ចីស្នើសុំ</span>
                        <button type="button" class="btn btn-default btn-xs" id="btnAddItem"><i class="fa fa-plus"></i> Add Row</button>
                    </div>

                    <div class="table-responsive">
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr style="background:#f8fafc;font-size:12px;">
                                <th>Product / Description</th>
                                <th style="width:100px;">Qty</th>
                                <th style="width:140px;">Unit Price ($)</th>
                                <th style="width:140px;">Line Total ($)</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($formItems as $itemIndex => $item)
                            <tr>
                                <td>
                                    @foreach(['product_id', 'sku', 'serial_number', 'description', 'photo_path', 'discount_amount'] as $metadata)
                                        <input type="hidden" name="items[{{ $itemIndex }}][{{ $metadata }}]" value="{{ $item[$metadata] ?? '' }}" @if($metadata === 'discount_amount') class="item-discount" @endif>
                                    @endforeach
                                    <input type="text" name="items[{{ $itemIndex }}][product_name]" class="form-control item-name" value="{{ $item['product_name'] ?? '' }}" placeholder="Item Name / Model" required>
                                </td>
                                <td>
                                    <input type="number" name="items[{{ $itemIndex }}][quantity]" class="form-control item-qty" value="{{ $item['quantity'] ?? 1 }}" min="1" step="0.01">
                                </td>
                                <td>
                                    <input type="number" name="items[{{ $itemIndex }}][unit_price]" class="form-control item-price" value="{{ $item['unit_price'] ?? 0 }}" min="0" step="0.01">
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $itemIndex }}][line_total]" class="form-control item-total" value="{{ $item['line_total'] ?? 0 }}" readonly>
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn btn-danger btn-xs btnRemoveItem"><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: INSTALLMENT CALCULATION -->
            <div class="col-md-5">
                <div class="qtn-card">
                    <div class="qtn-section-title">
                        <i class="fa fa-calculator text-primary"></i> 3. Installment Terms & Calculation
                    </div>

                    <div class="form-group">
                        <label>Total Price ($)</label>
                        <input type="number" name="total_amount" id="total_amount" class="form-control" value="{{ $field('total_amount', 500) }}" step="0.01" readonly style="font-weight:700;font-size:16px;">
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Down Payment ($)</label>
                                <input type="number" name="down_payment" id="down_payment" class="form-control" value="{{ $field('down_payment', 0) }}" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Loan Amount ($) <span class="text-danger">*</span></label>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control" value="{{ $field('loan_amount', 500) }}" min="0" step="0.01" required style="font-weight:700;color:var(--lm-primary);">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Interest Rate (% / month)</label>
                                <input type="number" name="interest_rate" id="interest_rate" class="form-control" value="{{ $field('interest_rate', 1.5) }}" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Interest Type</label>
                                <select name="interest_type" id="interest_type" class="form-control">
                                    <option value="flat_rate" @selected($field('interest_type', 'flat_rate') === 'flat_rate')>Flat Rate (ការប្រាក់ថេរ)</option>
                                    <option value="declining_balance" @selected($field('interest_type') === 'declining_balance')>Declining (ការប្រាក់ថយចុះ)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Duration (Months)</label>
                                <input type="number" name="duration_months" id="duration_months" class="form-control" value="{{ $field('duration_months', 12) }}" min="1" max="600" step="1" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Frequency</label>
                                <select name="payment_frequency" id="payment_frequency" class="form-control">
                                    <option value="monthly" @selected($field('payment_frequency', 'monthly') === 'monthly')>Monthly (ប្រចាំខែ)</option>
                                    <option value="weekly" @selected($field('payment_frequency') === 'weekly')>Weekly (ប្រចាំសប្តាហ៍)</option>
                                    <option value="daily" @selected($field('payment_frequency') === 'daily')>Daily (ប្រចាំថ្ងៃ)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>First Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="first_due_date" id="first_due_date" class="form-control" value="{{ $field('first_due_date', date('Y-m-d', strtotime('+1 month'))) }}" required>
                    </div>

                    <!-- CALCULATION PREVIEW BOX -->
                    <div class="calc-box" style="margin-top:15px;">
                        <div class="calc-row">
                            <span>Principal:</span>
                            <span id="txtPrincipal">$500.00</span>
                        </div>
                        <div class="calc-row">
                            <span>Estimated Interest:</span>
                            <span id="txtInterest">$90.00</span>
                        </div>
                        <div class="calc-row">
                            <span>Monthly Installment:</span>
                            <span id="txtInstallment" style="color:#16a34a;font-weight:700;">$49.17 / mo</span>
                        </div>
                        <div class="calc-row total">
                            <span>Total Payable:</span>
                            <span id="txtPayable" class="text-primary">$590.00</span>
                        </div>
                    </div>

                    <!-- Hidden fields for calculated values -->
                    <input type="hidden" name="installment_amount" id="installment_amount" value="{{ $field('installment_amount', 49.17) }}">
                    <input type="hidden" name="total_interest" id="total_interest" value="{{ $field('total_interest', 90) }}">
                    <input type="hidden" name="total_payable" id="total_payable" value="{{ $field('total_payable', 590) }}">

                    <div class="form-group" style="margin-top:15px;">
                        <label>Terms & Conditions / លក្ខខណ្ឌ</label>
                        <textarea name="terms" class="form-control" rows="2">{{ $field('terms', '1. Valid for 30 days. 2. Down payment must be completed before delivery. 3. Monthly payment on due date.') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note" class="form-control" rows="2">{{ $field('note') }}</textarea>
                    </div>

                    <div style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary btn-block btn-lg" style="font-weight:700;border-radius:8px;">
                            <i class="fa fa-save"></i> Save Quotation / រក្សាទុកសម្រង់តម្លៃ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('loan_js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Select customer autofill
    var customerSelect = document.getElementById('customer_id');
    if (customerSelect) {
        customerSelect.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (opt && opt.value) {
                document.getElementById('customer_name').value = opt.getAttribute('data-name') || '';
                document.getElementById('customer_phone').value = opt.getAttribute('data-phone') || '';
                document.getElementById('customer_address').value = opt.getAttribute('data-address') || '';
            }
        });
    }

    // Add item row
    var itemIndex = {{ count($formItems) }};
    document.getElementById('btnAddItem').addEventListener('click', function () {
        var tbody = document.querySelector('#itemsTable tbody');
        var tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="items[${itemIndex}][product_name]" class="form-control item-name" placeholder="Item Name / Model" required></td>
            <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control item-qty" value="1" min="1" step="1"></td>
            <td><input type="number" name="items[${itemIndex}][unit_price]" class="form-control item-price" value="0.00" min="0" step="0.01"></td>
            <td><input type="text" name="items[${itemIndex}][line_total]" class="form-control item-total" value="0.00" readonly></td>
            <td style="text-align:center;"><button type="button" class="btn btn-danger btn-xs btnRemoveItem"><i class="fa fa-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
        itemIndex++;
        bindItemEvents();
    });

    function bindItemEvents() {
        document.querySelectorAll('.btnRemoveItem').forEach(function (btn) {
            btn.onclick = function () {
                var row = this.closest('tr');
                if (document.querySelectorAll('#itemsTable tbody tr').length > 1) {
                    row.remove();
                    calcTotals();
                }
            };
        });

        document.querySelectorAll('.item-qty, .item-price').forEach(function (input) {
            input.oninput = function () {
                var row = this.closest('tr');
                var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                var price = parseFloat(row.querySelector('.item-price').value) || 0;
                var discount = parseFloat(row.querySelector('.item-discount')?.value) || 0;
                row.querySelector('.item-total').value = Math.max(0, qty * price - discount).toFixed(2);
                calcTotals();
            };
        });
    }

    function calcTotals() {
        var subtotal = 0;
        document.querySelectorAll('.item-total').forEach(function (inp) {
            subtotal += parseFloat(inp.value) || 0;
        });

        subtotal = Math.max(0, subtotal - {{ (float) ($quotation->discount_amount ?? 0) }} + {{ (float) ($quotation->tax_amount ?? 0) }});
        document.getElementById('total_amount').value = subtotal.toFixed(2);

        var down = parseFloat(document.getElementById('down_payment').value) || 0;
        var loanAmt = Math.max(0, subtotal - down);
        document.getElementById('loan_amount').value = loanAmt.toFixed(2);

        recalculateSchedule();
    }

    function recalculateSchedule() {
        var loanAmt = parseFloat(document.getElementById('loan_amount').value) || 0;
        var rate = parseFloat(document.getElementById('interest_rate').value) || 0;
        var duration = parseInt(document.getElementById('duration_months').value, 10) || 1;
        var type = document.getElementById('interest_type').value;

        var monthlyRate = (rate / 100);
        var totalInterest = 0;
        var installment = 0;
        var totalPayable = 0;

        if (type === 'flat_rate') {
            totalInterest = loanAmt * monthlyRate * duration;
            totalPayable = loanAmt + totalInterest;
            installment = duration > 0 ? (totalPayable / duration) : 0;
        } else {
            // declining
            var monthlyPrincipal = duration > 0 ? (loanAmt / duration) : 0;
            var remaining = loanAmt;
            for (var i = 0; i < duration; i++) {
                totalInterest += (remaining * monthlyRate);
                remaining -= monthlyPrincipal;
            }
            totalPayable = loanAmt + totalInterest;
            installment = duration > 0 ? (totalPayable / duration) : 0;
        }

        document.getElementById('txtPrincipal').innerText = '$' + loanAmt.toFixed(2);
        document.getElementById('txtInterest').innerText = '$' + totalInterest.toFixed(2);
        document.getElementById('txtInstallment').innerText = '$' + installment.toFixed(2) + ' / mo';
        document.getElementById('txtPayable').innerText = '$' + totalPayable.toFixed(2);

        document.getElementById('installment_amount').value = installment.toFixed(2);
        document.getElementById('total_interest').value = totalInterest.toFixed(2);
        document.getElementById('total_payable').value = totalPayable.toFixed(2);
    }

    document.getElementById('down_payment').addEventListener('input', function () {
        var total = parseFloat(document.getElementById('total_amount').value) || 0;
        var down = parseFloat(this.value) || 0;
        document.getElementById('loan_amount').value = Math.max(0, total - down).toFixed(2);
        recalculateSchedule();
    });

    document.getElementById('loan_amount').addEventListener('input', recalculateSchedule);
    document.getElementById('interest_rate').addEventListener('input', recalculateSchedule);
    document.getElementById('interest_type').addEventListener('change', recalculateSchedule);
    document.getElementById('duration_months').addEventListener('input', recalculateSchedule);
    document.getElementById('payment_frequency').addEventListener('change', recalculateSchedule);

    bindItemEvents();
    recalculateSchedule();
});
</script>
@endsection
