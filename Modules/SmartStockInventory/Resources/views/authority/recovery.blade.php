@extends('smartstockinventory::layouts.master')
@section('page_title', 'Recover Transfer Receiving Lots')
@section('module_content')
<div class="box box-warning">
    <div class="box-header with-border"><h3 class="box-title">Recover receiving lots — {{ $transfer->ref_no }}</h3></div>
    <div class="box-body">
        <p>Use an original SQL backup made after this transfer was created and before its receiving lots were damaged. Recovery restores original receipt IDs and serials, reconnects later movements, and includes a full business reconciliation preview.</p>
        <table class="table table-bordered"><thead><tr><th>SKU</th><th>Sent</th><th>Receiving quantity recorded</th><th>Missing quantity</th></tr></thead><tbody>
        @foreach($outgoing as $row)
            <tr><td>{{ $row->sub_sku }}</td><td>{{ $row->quantity }}</td><td>{{ $incoming->get($row->variation_id)->quantity ?? 0 }}</td>
                <td>{{ max(0, $row->quantity - ($incoming->get($row->variation_id)->quantity ?? 0)) }}</td></tr>
        @endforeach
        </tbody></table>
        <p>Transfer date: {{ $transfer->transaction_date }}. Outgoing document #{{ $transfer->id }}; receiving document #{{ $receipt->id }}.</p>
        @if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" enctype="multipart/form-data" action="{{ ssi_route('ssi.authority.recovery_preview', ['id' => $transfer->id]) }}">
            @csrf
            <div class="form-group"><label for="recovery_backup">Original database backup (.sql)</label>
                <input type="file" id="recovery_backup" name="backup" accept=".sql,.txt" required>
                <p class="help-block">The file must include transactions and purchase_lines with their column definitions or explicit INSERT column names. SQL is read as data and is never executed. Files are not saved to public storage. Your server upload limit also applies.</p></div>
            <div class="form-group"><label for="recovery_reason">Repair reason and evidence</label>
                <textarea class="form-control" id="recovery_reason" name="reason" minlength="10" maxlength="500" required>{{ old('reason') }}</textarea></div>
            <div class="checkbox"><label><input type="checkbox" name="verified" value="1" required> I verified that this is the original backup containing the correct receiving lots for this transfer.</label></div>
            <button class="btn btn-warning">Preview receipt recovery and full repair</button>
            <a class="btn btn-default" href="{{ ssi_route('ssi.authority.index', ['transfer_id' => $transfer->id]) }}">Back to Authority</a>
        </form>
    </div>
</div>
@endsection
