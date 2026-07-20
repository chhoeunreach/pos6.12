@php
    $isEdit = isset($userRow);
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name"
                   value="{{ old('name', $userRow->name ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="username">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="username" name="username"
                   value="{{ old('username', $userRow->username ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="{{ old('email', $userRow->email ?? '') }}">
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" class="form-control" id="phone" name="phone"
                   value="{{ old('phone', $userRow->phone ?? '') }}">
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label for="password">Password {{ $isEdit ? '(leave blank to keep current)' : '' }} <span class="{{ $isEdit ? '' : 'text-danger' }}">*</span></label>
            <input type="password" class="form-control" id="password" name="password"
                   {{ $isEdit ? '' : 'required' }} minlength="6">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                   {{ $isEdit ? '' : 'required' }} minlength="6">
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status">
                <option value="active" {{ old('status', $userRow->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $userRow->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>
</div>
