<div class="row">
    <div class="col-md-4"><div class="form-group"><label>Can Login</label>@php $cl = (int) old('can_login', $customerRow->can_login ?? 0); @endphp<select class="form-control" name="can_login"><option value="0" {{ $cl===0?'selected':'' }}>No</option><option value="1" {{ $cl===1?'selected':'' }}>Yes</option></select></div></div>
    <div class="col-md-4"><div class="form-group"><label>Username</label><input class="form-control" name="username" value="{{ old('username', $customerRow->username ?? '') }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Login Phone</label><input class="form-control" name="login_phone" value="{{ old('login_phone', $customerRow->login_phone ?? '') }}"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Password</label><input type="password" class="form-control" name="password"></div></div>
    <div class="col-md-6"><div class="form-group"><label>Confirm Password</label><input type="password" class="form-control" name="password_confirmation"></div></div>
    @if(isset($customerRow))
    <div class="col-md-6"><div class="form-group"><label>Last Login</label><input readonly class="form-control" value="{{ $customerRow->last_login_at ?? '-' }}"></div></div>
    @endif
</div>
