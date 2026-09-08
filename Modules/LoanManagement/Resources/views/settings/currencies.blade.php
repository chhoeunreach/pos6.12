@extends('loanmanagement::layouts.app')
@section('title', 'Currencies')

@section('content_body')
<section class="content-header">
    <h1>Currencies <small>Configure loan currencies and exchange rates</small></h1>
</section>

<section class="content">
    @if(session('status.msg'))
        <div class="alert alert-{{ !empty(session('status.success')) ? 'success' : 'danger' }}">
            {{ session('status.msg') }}
        </div>
    @endif

    <form method="POST" action="{{ route('loan-management.settings.currencies.update') }}">
        @csrf
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title">Currency List</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Code</th>
                            <th>Name</th>
                            <th style="width: 180px;">Exchange Rate</th>
                            <th style="width: 120px;">Default</th>
                            <th style="width: 120px;">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currencies as $currency)
                            <tr>
                                <td><input type="text" name="currencies[{{ $currency->id }}][code]" class="form-control input-sm" value="{{ $currency->code }}" required></td>
                                <td><input type="text" name="currencies[{{ $currency->id }}][name]" class="form-control input-sm" value="{{ $currency->name ?? $currency->code }}" required></td>
                                <td><input type="number" step="0.000001" min="0.000001" name="currencies[{{ $currency->id }}][exchange_rate]" class="form-control input-sm" value="{{ $currency->exchange_rate ?? 1 }}" required></td>
                                <td class="text-center"><input type="radio" name="default_currency" value="{{ $currency->code }}" {{ !empty($currency->is_default) ? 'checked' : '' }}></td>
                                <td class="text-center">
                                    <label style="font-weight:400;">
                                        <input type="checkbox" name="currencies[{{ $currency->id }}][is_active]" value="1" {{ !empty($currency->is_active) ? 'checked' : '' }}>
                                        Enabled
                                    </label>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No currencies found.</td></tr>
                        @endforelse
                        <tr>
                            <td><input type="text" name="new_currency[code]" class="form-control input-sm" placeholder="THB"></td>
                            <td><input type="text" name="new_currency[name]" class="form-control input-sm" placeholder="Add new currency"></td>
                            <td><input type="number" step="0.000001" min="0.000001" name="new_currency[exchange_rate]" class="form-control input-sm" value="1"></td>
                            <td colspan="2" class="text-muted">Leave code blank if you do not want to add a new currency.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Currencies</button>
            </div>
        </div>
    </form>
</section>
@endsection
