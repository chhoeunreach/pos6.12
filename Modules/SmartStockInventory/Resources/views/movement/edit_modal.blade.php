<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Transaction</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:18px; color:#223; }
        .card { background:#fff; border:1px solid #e3e8f0; border-radius:10px; box-shadow:0 2px 10px rgba(20,34,66,.06); max-width:920px; }
        .card-header { padding:14px 18px; border-bottom:1px solid #eef2f7; font-size:22px; font-weight:700; color:#22335b; }
        .card-body { padding:18px; }
        .status-ok { background:#e8f7ee; color:#146c43; border:1px solid #bfe6cf; padding:10px 12px; border-radius:8px; margin-bottom:14px; }
        .row { display:flex; gap:12px; margin-bottom:12px; }
        .col { flex:1; min-width:0; }
        label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334; }
        input, select, textarea { width:100%; box-sizing:border-box; border:1px solid #cfd7e6; border-radius:7px; padding:9px 10px; font-size:14px; background:#fff; }
        input[readonly] { background:#f0f3f9; color:#57617a; }
        textarea { min-height:110px; resize:vertical; }
        .actions { margin-top:14px; display:flex; gap:8px; }
        .btn { border:0; border-radius:8px; padding:10px 14px; font-size:14px; cursor:pointer; }
        .btn-primary { background:#1677ff; color:#fff; }
        .btn-primary:hover { background:#0f63d8; }
    </style>
</head>
<body style="padding:16px;">
    <div class="card">
    <div class="card-header">Edit Transaction</div>
    <div class="card-body">
    @if(session('status') && session('status.success'))
        <div class="status-ok">
            {{ session('status.msg') }}
        </div>
    @endif
    <h4 style="margin-top:0; margin-bottom:14px;">Transaction #{{ $tx->id }}</h4>
    <form method="post" action="{{ route('ssi.movement.update_modal', ['transaction' => $tx->id]) }}">
        @csrf
        <div class="row">
            <div class="col">
                <div>
                    <label>Type</label>
                    <input type="text" class="form-control" value="{{ $tx->type }}" readonly>
                </div>
            </div>
            <div class="col">
                <div>
                    <label>Location ID</label>
                    <input type="text" class="form-control" value="{{ $tx->location_id }}" readonly>
                </div>
            </div>
        </div>
        <div style="margin-bottom:12px;">
            <label>Reference No</label>
            <input type="text" name="ref_no" class="form-control" value="{{ old('ref_no', $tx->ref_no) }}">
        </div>
        <div class="row">
            <div class="col">
                <div>
                    <label>Transaction Date</label>
                    @php($txDateValue = old('transaction_date', \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d\TH:i')))
                    <input type="datetime-local" name="transaction_date" class="form-control"
                        value="{{ $txDateValue }}">
                </div>
            </div>
            <div class="col">
                <div>
                    <label>Status</label>
                    <select name="status" class="form-control">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ old('status', $tx->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div style="margin-bottom:12px;">
            <label>Additional Notes</label>
            <textarea name="additional_notes" class="form-control" rows="4">{{ old('additional_notes', $tx->additional_notes) }}</textarea>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Update</button>
        </div>
    </form>
    </div>
    </div>
</body>
</html>
