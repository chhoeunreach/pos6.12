<div class="row">
    <div class="col-md-12"><div class="form-group"><label>Address</label><textarea class="form-control" name="address">{{ old('address', $customerRow->address ?? '') }}</textarea></div></div>
    <div class="col-md-3"><div class="form-group"><label>Province</label><input class="form-control" name="province" value="{{ old('province', $customerRow->province ?? '') }}"></div></div>
    <div class="col-md-3"><div class="form-group"><label>District</label><input class="form-control" name="district" value="{{ old('district', $customerRow->district ?? '') }}"></div></div>
    <div class="col-md-3"><div class="form-group"><label>Commune</label><input class="form-control" name="commune" value="{{ old('commune', $customerRow->commune ?? '') }}"></div></div>
    <div class="col-md-3"><div class="form-group"><label>Village</label><input class="form-control" name="village" value="{{ old('village', $customerRow->village ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Latitude</label><input id="latitude" class="form-control" name="latitude" value="{{ old('latitude', $customerRow->latitude ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Longitude</label><input id="longitude" class="form-control" name="longitude" value="{{ old('longitude', $customerRow->longitude ?? '') }}"></div></div>
    <div class="col-md-12"><button type="button" id="btn-capture-gps" class="btn btn-default">Map Picker / Capture GPS</button></div>
</div>
