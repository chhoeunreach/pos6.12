<div class="row">
    <div class="col-md-4"><div class="form-group"><label>Customer Code</label><input class="form-control" name="customer_code" value="{{ old('customer_code', $customerRow->customer_code ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Name *</label><input class="form-control" name="name" required value="{{ old('name', $customerRow->name ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Khmer Name</label><input class="form-control" name="khmer_name" value="{{ old('khmer_name', $customerRow->khmer_name ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Phone *</label><input class="form-control" name="phone" required value="{{ old('phone', $customerRow->phone ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Alternative Phone</label><input class="form-control" name="alternate_phone" value="{{ old('alternate_phone', $customerRow->alternate_phone ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Email</label><input class="form-control" name="email" value="{{ old('email', $customerRow->email ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Gender</label><input class="form-control" name="gender" value="{{ old('gender', $customerRow->gender ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Date of Birth</label><input type="date" class="form-control" name="date_of_birth" value="{{ old('date_of_birth', $customerRow->date_of_birth ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Customer Type</label><input class="form-control" name="customer_type" value="{{ old('customer_type', $customerRow->customer_type ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Status</label>@php $st = old('status', $customerRow->status ?? 'active'); @endphp<select class="form-control" name="status"><option value="active" {{ $st==='active'?'selected':'' }}>Active</option><option value="inactive" {{ $st==='inactive'?'selected':'' }}>Inactive</option></select></div></div>
</div>
