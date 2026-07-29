@extends('hrsellmanagement::layouts.master')
@section('page_title', 'HR Sell Settings')
@section('module_content')
<div class="box box-primary"><div class="box-body">
<form method="post" action="{{ route('hr-sell.settings.update') }}">@csrf
<div class="row">
<div class="col-md-3"><label>Default Commission Type</label><select name="commission_type" class="form-control"><option value="percent" @selected($setting->commission_type=='percent')>Percent</option><option value="fixed" @selected($setting->commission_type=='fixed')>Fixed</option></select></div>
<div class="col-md-3"><label>Default Commission</label><input name="commission_value" type="number" step="0.0001" class="form-control" value="{{ $setting->commission_value }}"></div>
<div class="col-md-3"><label>Approval Levels</label><input name="approval_levels" class="form-control" value="{{ implode(',', $setting->approval_levels ?: ['supervisor','manager']) }}"></div>
<div class="col-md-3"><label>Require Approval</label><div><input type="checkbox" name="require_approval" value="1" @checked($setting->require_approval)> Yes</div></div>
</div>
<button class="btn btn-primary" style="margin-top:10px;">Save Settings</button>
</form>
</div></div>
@endsection
