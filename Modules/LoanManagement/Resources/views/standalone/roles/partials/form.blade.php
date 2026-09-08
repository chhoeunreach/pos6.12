<style>
    .ultimate-role-form {
        max-width: 100%;
    }
    .ultimate-role-name {
        max-width: 560px;
    }
    .ultimate-permissions-title {
        margin: 18px 0 0;
        color: #374151;
        font-weight: 700;
    }
    .ultimate-permission-row {
        display: grid;
        grid-template-columns: 170px 170px minmax(0, 1fr);
        gap: 20px;
        padding: 22px 0;
        border-bottom: 1px solid #6b7280;
    }
    .ultimate-permission-row:first-of-type {
        padding-top: 12px;
    }
    .ultimate-permission-group {
        color: #111827;
        font-size: 16px;
        font-weight: 700;
    }
    .ultimate-select-all,
    .ultimate-permission-check {
        color: #111827;
        font-weight: 400;
    }
    .ultimate-permission-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(220px, 1fr));
        gap: 16px 44px;
    }
    .ultimate-permission-check {
        display: block;
        min-height: 20px;
        margin: 0;
    }
    .ultimate-permission-check input,
    .ultimate-select-all input {
        margin-right: 6px;
    }
    @media (max-width: 991px) {
        .ultimate-permission-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .ultimate-permission-list {
            grid-template-columns: 1fr;
        }
    }
</style>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="ultimate-role-form">
    <div class="ultimate-role-name">
        <div class="form-group">
            <label>Role Name:*</label>
            <input class="form-control" name="name" value="{{ old('name', $role->name ?? '') }}" required maxlength="191">
        </div>
    </div>

    <div class="ultimate-permissions-title">Permissions:</div>

    @forelse($permissionGroups as $groupName => $permissions)
        <div class="ultimate-permission-row">
            <div class="ultimate-permission-group">{{ $groupName }}</div>
            <label class="ultimate-select-all">
                <input type="checkbox" class="js-permission-group-toggle">
                Select all
            </label>
            <div class="ultimate-permission-list">
                @foreach($permissions as $permission)
                    <label class="ultimate-permission-check">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                            {{ in_array($permission->name, old('permissions', $selectedPermissions ?? []), true) ? 'checked' : '' }}>
                        {{ $permission->display_label ?? $permission->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-muted">No permissions found.</p>
    @endforelse
</div>

<script>
    function refreshGroupToggle(row) {
        var checks = row.querySelectorAll('input[name="permissions[]"]');
        var toggle = row.querySelector('.js-permission-group-toggle');
        var checked = row.querySelectorAll('input[name="permissions[]"]:checked');

        if (!toggle || !checks.length) {
            return;
        }

        toggle.checked = checked.length === checks.length;
        toggle.indeterminate = checked.length > 0 && checked.length < checks.length;
    }

    document.querySelectorAll('.ultimate-permission-row').forEach(function (row) {
        refreshGroupToggle(row);

        var toggle = row.querySelector('.js-permission-group-toggle');
        if (toggle) {
            toggle.addEventListener('change', function () {
                row.querySelectorAll('input[name="permissions[]"]').forEach(function (checkbox) {
                    checkbox.checked = toggle.checked;
                });
                toggle.indeterminate = false;
            });
        }

        row.querySelectorAll('input[name="permissions[]"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                refreshGroupToggle(row);
            });
        });
    });
</script>
