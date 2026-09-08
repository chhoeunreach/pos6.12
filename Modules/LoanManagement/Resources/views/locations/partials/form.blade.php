@php
    $location = $location ?? null;
@endphp

<div class="row">
    <div class="col-sm-12">
        <div class="form-group">
            <label>Name:*</label>
            <input type="text" name="name" class="form-control" required placeholder="Name" value="{{ old('name', $location->name ?? '') }}">
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Location ID:</label>
            <input type="text" name="location_code" class="form-control" placeholder="Location ID" value="{{ old('location_code', $location->location_code ?? '') }}">
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Installment Invoice Prefix:</label>
            <input type="text" name="loan_invoice_prefix" class="form-control" maxlength="50" placeholder="LN" value="{{ old('loan_invoice_prefix', $location->loan_invoice_prefix ?? '') }}">
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Phone:</label>
            <input type="text" name="phone" class="form-control" placeholder="Phone" value="{{ old('phone', $location->phone ?? '') }}">
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Telegram Number:</label>
            <input type="text" name="telegram_number" class="form-control" placeholder="Telegram number" value="{{ old('telegram_number', $location->telegram_number ?? '') }}">
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Status:</label>
            @php $status = old('status', $location->status ?? 'active'); @endphp
            <select name="status" class="form-control">
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-12">
        <div class="form-group">
            <label>Address:</label>
            <textarea name="address" class="form-control" rows="3" placeholder="Address">{{ old('address', $location->address ?? '') }}</textarea>
        </div>
    </div>
</div>
