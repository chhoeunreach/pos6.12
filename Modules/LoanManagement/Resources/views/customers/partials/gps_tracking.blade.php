<div class="row">
    <div class="col-md-4"><div class="form-group"><label>Allow GPS Tracking</label>@php $ag=(int) old('allow_gps_tracking', $customerRow->allow_gps_tracking ?? 0); @endphp<select class="form-control" name="allow_gps_tracking"><option value="0" {{ $ag===0?'selected':'' }}>No</option><option value="1" {{ $ag===1?'selected':'' }}>Yes</option></select></div></div>
    <div class="col-md-8"><div class="form-group"><label>Tracking Note</label><input class="form-control" name="gps_tracking_note" value="{{ old('gps_tracking_note', $customerRow->gps_tracking_note ?? '') }}"></div></div>
    @if(!empty($latestLocation))
    <div class="col-md-6"><div class="form-group"><label>Latest Location</label><input readonly class="form-control" value="{{ $latestLocation->latitude }}, {{ $latestLocation->longitude }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Last Update</label><input readonly class="form-control" value="{{ $latestLocation->recorded_at }}"></div></div>
    @endif
</div>
