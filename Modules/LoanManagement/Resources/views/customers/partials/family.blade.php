<div class="row">
    <div class="col-md-6"><div class="form-group"><label>Family Contact Name</label><input class="form-control" name="family_contact_name" value="{{ old('family_contact_name', $customerRow->family_contact_name ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Family Contact Phone</label><input class="form-control" name="family_contact_phone" value="{{ old('family_contact_phone', $customerRow->family_contact_phone ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Spouse Name</label><input class="form-control" name="spouse_name" value="{{ old('spouse_name', $customerRow->spouse_name ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Spouse Phone</label><input class="form-control" name="spouse_phone" value="{{ old('spouse_phone', $customerRow->spouse_phone ?? '') }}"></div></div>
</div>
