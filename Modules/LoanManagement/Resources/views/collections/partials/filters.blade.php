@php
    $lmIsKhmer = session('user.language', config('app.locale')) === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

@component('components.filters', ['title' => $lmText('Filters', 'តម្រងស្វែងរក')])
    <form method="GET" action="{{ url()->current() }}" id="loanCollectionFiltersForm">
        <div class="lm-pos-filter-grid">
            {{-- 1. Business Location --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Business Location:', 'ទីតាំងសាខា:') }}</label>
                <select name="business_location_id" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach($options['locations'] ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ (string)($filters['business_location_id'] ?? '') === (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 2. Collector --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Collector / Staff:', 'អ្នកប្រមូលប្រាក់:') }}</label>
                <select name="collector_id" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach($options['collectors'] ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ (string)($filters['collector_id'] ?? '') === (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Payment Status --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Payment Status:', 'ស្ថានភាពបង់ប្រាក់:') }}</label>
                <select name="payment_status" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach(['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'confirmed' => 'Confirmed'] as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['payment_status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 4. Collection Status --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Collection Status:', 'ស្ថានភាពតាមដាន:') }}</label>
                <select name="collection_status" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach($options['statuses'] ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['collection_status'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 5. Overdue Bucket --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Overdue Bucket:', 'កម្រិតហួសកំណត់:') }}</label>
                <select name="overdue_bucket" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach($options['buckets'] ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['overdue_bucket'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 6. Risk Level --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Risk Level:', 'កម្រិតហានិភ័យ:') }}</label>
                <select name="risk_level" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach($options['riskLevels'] ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['risk_level'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 7. Skip Level --}}
            <div class="lm-pos-filter-field">
                <label>{{ $lmText('Skip Level:', 'កម្រិតគេចវេស:') }}</label>
                <select name="skip_level" class="form-control" onchange="this.form.submit()">
                    <option value="">{{ $lmText('All', 'ទាំងអស់') }}</option>
                    @foreach($options['skipLevels'] ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['skip_level'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 8. Action Buttons --}}
            <div class="lm-pos-filter-field">
                <label>&nbsp;</label>
                <div class="lm-pos-filter-actions">
                    <button type="submit" class="lm-btn-pos-filter">
                        <i class="fa fa-filter"></i> {{ $lmText('Filter', 'ចម្រាញ់') }}
                    </button>
                    <a href="{{ url()->current() }}" class="lm-btn-pos-reset">
                        <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'កំណត់ឡើងវិញ') }}
                    </a>
                </div>
            </div>
        </div>
    </form>
@endcomponent
