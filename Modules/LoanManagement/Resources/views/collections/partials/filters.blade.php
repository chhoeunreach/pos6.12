<form method="get" class="loan-collection-filters">
    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Filters</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Collection Status</label>
                        <select name="collection_status" class="form-control">
                            <option value="">All</option>
                            @foreach($options['statuses'] ?? [] as $key => $label)
                                <option value="{{ $key }}" {{ ($filters['collection_status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Overdue Bucket</label>
                        <select name="overdue_bucket" class="form-control">
                            <option value="">All</option>
                            @foreach($options['buckets'] ?? [] as $key => $label)
                                <option value="{{ $key }}" {{ ($filters['overdue_bucket'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Collector</label>
                        <select name="collector_id" class="form-control">
                            <option value="">All</option>
                            @foreach($options['collectors'] ?? [] as $key => $label)
                                <option value="{{ $key }}" {{ (string)($filters['collector_id'] ?? '') === (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Business Location</label>
                        <select name="business_location_id" class="form-control">
                            <option value="">All</option>
                            @foreach($options['locations'] ?? [] as $key => $label)
                                <option value="{{ $key }}" {{ (string)($filters['business_location_id'] ?? '') === (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Risk Level</label>
                        <select name="risk_level" class="form-control">
                            <option value="">All</option>
                            @foreach($options['riskLevels'] ?? [] as $key => $label)
                                <option value="{{ $key }}" {{ ($filters['risk_level'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="">All</option>
                            @foreach(['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'confirmed' => 'Confirmed'] as $key => $label)
                                <option value="{{ $key }}" {{ ($filters['payment_status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Skip Level</label>
                        <select name="skip_level" class="form-control">
                            <option value="">All</option>
                            @foreach($options['skipLevels'] ?? [] as $key => $label)
                                <option value="{{ $key }}" {{ ($filters['skip_level'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Legal Status</label>
                        <input type="text" name="legal_status" class="form-control" value="{{ $filters['legal_status'] ?? '' }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-6 text-right" style="padding-top:25px;">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Apply</button>
                    <a href="{{ url()->current() }}" class="btn btn-default">Reset</a>
                </div>
            </div>
        </div>
    </div>
</form>
