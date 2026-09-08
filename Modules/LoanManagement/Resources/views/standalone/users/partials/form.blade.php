@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@php
    $currentRole = !empty($userRow) && $userRow->relationLoaded('roles') ? $userRow->roles->first() : null;
    $selectedRole = old('role', $currentRole->id ?? null);
@endphp

<div class="pos-form-grid">
    <div>
        <div class="form-group">
            <label>First Name</label>
            <input class="form-control" name="first_name" value="{{ old('first_name', $userRow->first_name ?? '') }}" required>
        </div>
    </div>
    <div>
        <div class="form-group">
            <label>Last Name</label>
            <input class="form-control" name="last_name" value="{{ old('last_name', $userRow->last_name ?? '') }}">
        </div>
    </div>
    <div>
        <div class="form-group">
            <label>Username</label>
            <input class="form-control" name="username" value="{{ old('username', $userRow->username ?? '') }}" required>
        </div>
    </div>
    <div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" value="{{ old('email', $userRow->email ?? '') }}" required>
        </div>
    </div>
    @if(empty($userRow))
        <div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" class="form-control" name="password" required minlength="6">
            </div>
        </div>
        <div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" class="form-control" name="password_confirmation" required minlength="6">
            </div>
        </div>
    @else
        <div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" class="form-control" name="password" minlength="6">
            </div>
        </div>
        <div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" class="form-control" name="password_confirmation" minlength="6">
            </div>
        </div>
    @endif
    <div>
        <div class="form-group">
            <label>Business ID</label>
            <input type="number" min="1" class="form-control" name="business_id" value="{{ old('business_id', $userRow->business_id ?? (auth()->user()->business_id ?? 1)) }}">
        </div>
    </div>
    <div>
        <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
                <option value="active" {{ old('status', $userRow->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $userRow->status ?? 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>
    <div>
        <div class="form-group">
            <label>Role</label>
            <select class="form-control" name="role">
                <option value="">No Role</option>
                @foreach($roles as $id => $name)
                    <option value="{{ $id }}" {{ (string) $selectedRole === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="pos-form-full pos-form-check">
        <label>
            <input type="checkbox" name="allow_login" value="1" {{ old('allow_login', $userRow->allow_login ?? 1) ? 'checked' : '' }}>
            Allow login
        </label>
    </div>
</div>
