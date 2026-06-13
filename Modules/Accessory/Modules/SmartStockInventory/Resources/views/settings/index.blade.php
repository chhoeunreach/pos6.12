@extends('smartstockinventory::layouts.master')
@section('page_title', 'Smart Stock Settings')
@section('module_content')
<form method="post" action="{{ route('ssi.settings.update') }}">@csrf
<div class="box box-primary"><div class="box-body row">
<div class="col-md-3"><label><input type="checkbox" name="telegram_enabled" value="1" {{ $setting->telegram_enabled ? 'checked':'' }}> Enable Telegram Alert</label></div>
<div class="col-md-4"><label>Bot Token</label><input class="form-control" name="telegram_bot_token" value="{{ $setting->telegram_bot_token }}"></div>
<div class="col-md-3"><label>Chat ID</label><input class="form-control" name="telegram_chat_id" value="{{ $setting->telegram_chat_id }}"></div>
<div class="col-md-2"><label><input type="checkbox" name="allow_negative_adjustment" value="1" {{ !empty($setting->allow_negative_adjustment) ? 'checked':'' }}> Allow Negative Adj.</label></div>
<div class="col-md-2"><label><input type="checkbox" name="require_approval" value="1" {{ !empty($setting->require_approval) ? 'checked':'' }}> Require Approval</label></div>
<div class="col-md-2"><label><input type="checkbox" name="blind_count_default" value="1" {{ !empty($setting->blind_count_default) ? 'checked':'' }}> Blind Default</label></div>
<div class="col-md-2"><label><input type="checkbox" name="freeze_sell_during_count" value="1" {{ !empty($setting->freeze_sell_during_count) ? 'checked':'' }}> Freeze Sell</label></div>
<div class="col-md-2"><label><input type="checkbox" name="auto_generate_adjustment" value="1" {{ !empty($setting->auto_generate_adjustment) ? 'checked':'' }}> Auto Adj.</label></div>
<div class="col-md-2"><label><input type="checkbox" name="auto_close_session" value="1" {{ !empty($setting->auto_close_session) ? 'checked':'' }}> Auto Close</label></div>
<div class="col-md-2"><label><input type="checkbox" name="require_imei_validation" value="1" {{ !empty($setting->require_imei_validation) ? 'checked':'' }}> IMEI Validate</label></div>
<div class="col-md-2"><label>Mismatch Threshold</label><input class="form-control" name="mismatch_threshold" value="{{ $setting->mismatch_threshold ?? 0 }}"></div>
<div class="col-md-2"><label>Recount Threshold</label><input class="form-control" name="recount_threshold" value="{{ $setting->recount_threshold ?? 5 }}"></div>
<div class="col-md-3"><label>Reason</label><input class="form-control" name="reason" required></div>
<div class="col-md-2" style="margin-top:24px;"><button class="btn btn-primary">Save</button></div>
<div class="col-md-5" style="margin-top:24px;">
    <button formaction="{{ route('ssi.settings.test_telegram') }}" formmethod="post" class="btn btn-info">Test Telegram</button>
    <button formaction="{{ route('ssi.settings.reset_default') }}" formmethod="post" class="btn btn-warning">Reset Default</button>
    <a class="btn btn-success" href="{{ route('ssi.settings.export') }}">Export Settings</a>
</div>
</div></div>
</form>
@endsection
