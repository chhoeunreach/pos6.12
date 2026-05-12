<div class="row">
    <div class="col-md-4"><div class="form-group"><label>Blacklist Status</label>@php $bl=(int) old('blacklist_status', $customerRow->blacklist_status ?? 0); @endphp<select class="form-control" name="blacklist_status"><option value="0" {{ $bl===0?'selected':'' }}>No</option><option value="1" {{ $bl===1?'selected':'' }}>Yes</option></select></div></div>
    <div class="col-md-8"><div class="form-group"><label>Blacklist Reason</label><input class="form-control" name="blacklist_reason" value="{{ old('blacklist_reason', $customerRow->blacklist_reason ?? '') }}"></div></div>
    @if(isset($customerRow))
    <div class="col-md-6"><div class="form-group"><label>Blacklist Date</label><input readonly class="form-control" value="{{ $customerRow->blacklist_date ?? '-' }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Blacklisted By</label><input readonly class="form-control" value="{{ $customerRow->blacklist_by ?? '-' }}"></div></div>
    @endif
</div>
