@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

<div class="lm-step-card lm-step-card-amber" id="sectionTerms">
    <div class="lm-step-card-header">
        <div class="lm-step-card-title-wrap">
            <span class="lm-step-badge">3</span>
            <div>
                <h3 class="lm-step-title"><i class="fa fa-sliders text-warning"></i> {{ $lmText('Installment Terms & Financing Structure', 'លក្ខខណ្ឌកម្ចី និងការប្រាក់') }}</h3>
                <p class="lm-step-subtitle">{{ $lmText('Configure principal, interest, duration, and repayment schedule.', 'កំណត់ប្រាក់ខ្ចី ការប្រាក់ និងរយៈពេលបង់') }}</p>
            </div>
        </div>
        <div class="lm-step-header-actions">
            <span class="lm-badge-pill lm-badge-pill-amber" style="font-size:10px; padding:2px 8px;">
                <i class="fa fa-shield"></i> {{ $lmText('Terms', 'លក្ខខណ្ឌ') }}
            </span>
        </div>
    </div>

    <div class="lm-step-card-body">
        <div class="row" style="margin-left:-4px; margin-right:-4px;">
            <!-- Primary Row: Principal, Rate, Duration, Method -->
            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Principal Financed', 'ប្រាក់ដើមត្រូវបង់') }} <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" id="principal_amount_input" name="principal_amount" class="form-control lm-input-styled lm-input-highlight" min="0.01" required placeholder="0.00">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <div class="lm-label-with-shortcuts">
                        <label class="lm-field-label">{{ $lmText('Interest (%)', 'ការប្រាក់ (%)') }}</label>
                        <div class="lm-quick-presets" id="interestRatePresets">
                            <button type="button" class="lm-preset-btn" data-val="0">0%</button>
                            <button type="button" class="lm-preset-btn" data-val="2.5">2.5%</button>
                            <button type="button" class="lm-preset-btn active" data-val="4">4%</button>
                            <button type="button" class="lm-preset-btn" data-val="5">5%</button>
                        </div>
                    </div>
                    <input type="number" step="0.01" name="interest_rate" id="interest_rate_input" class="form-control lm-input-styled" value="{{ old('interest_rate', 4) }}" min="0">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <div class="lm-label-with-shortcuts">
                        <label class="lm-field-label">{{ $lmText('Duration', 'រយៈពេល (ខែ)') }} <span class="text-danger">*</span></label>
                        <div class="lm-quick-presets" id="durationPresets">
                            <button type="button" class="lm-preset-btn" data-val="6">6m</button>
                            <button type="button" class="lm-preset-btn active" data-val="12">12m</button>
                            <button type="button" class="lm-preset-btn" data-val="24">24m</button>
                        </div>
                    </div>
                    <input type="number" name="duration_months" id="duration_months_input" class="form-control lm-input-styled" min="1" max="360" value="12" required>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Calculation Method', 'វិធីគណនាការប្រាក់') }} <span class="text-danger">*</span></label>
                    <select name="interest_type" id="interest_type_select" class="form-control lm-input-styled">
                        <option value="flat" selected>{{ $lmText('Flat Rate (បង់ថេរ)', 'បង់ថេរ (Flat)') }}</option>
                        <option value="reducing_balance">{{ $lmText('Reducing (បង់ថយ)', 'បង់ថយ (Reducing)') }}</option>
                    </select>
                </div>
            </div>

            <!-- Second Row: Dates, Frequency, Branch -->
            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Start Date', 'ថ្ងៃចាប់ផ្តើម') }} <span class="text-danger">*</span></label>
                    <input type="date" name="loan_date" class="form-control lm-input-styled" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('First Due Date', 'ថ្ងៃត្រូវបង់ដំបូង') }} <span class="text-danger">*</span></label>
                    <input type="date" name="first_due_date" id="first_due_date_input" class="form-control lm-input-styled" value="{{ Carbon\Carbon::today()->addMonth()->format('Y-m-d') }}" required>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Frequency', 'ប្រេកង់នៃការបង់') }} <span class="text-danger">*</span></label>
                    <select name="payment_frequency" id="payment_frequency_select" class="form-control lm-input-styled">
                        <option value="monthly" selected>{{ $lmText('Monthly', 'ប្រចាំខែ (Monthly)') }}</option>
                        <option value="weekly">{{ $lmText('Weekly', 'ប្រចាំសប្តាហ៍') }}</option>
                        <option value="daily">{{ $lmText('Daily', 'ប្រចាំថ្ងៃ') }}</option>
                    </select>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Branch Location', 'សាខាអាជីវកម្ម') }}</label>
                    <select name="business_location_id" class="form-control lm-input-styled select2" style="width:100%">
                        <option value="">{{ $lmText('-- Branch --', '-- សាខា --') }}</option>
                        @foreach($locations as $id => $name)
                            <option value="{{ $id }}" {{ (string) $id === (string) ($defaultLocationId ?? '') ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Third Row: Currency, Officer, Ref, Note -->
            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Currency', 'រូបិយប័ណ្ណ') }} <span class="text-danger">*</span></label>
                    <select name="currency" class="form-control lm-input-styled">
                        <option value="USD" selected>USD ($)</option>
                        <option value="KHR">KHR (៛)</option>
                    </select>
                    <input type="hidden" name="exchange_rate" value="1">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Loan Officer / Collector', 'បុគ្គលិកទទួលបន្ទុក') }}</label>
                    <select name="assigned_collector_id" class="form-control lm-input-styled select2" style="width:100%">
                        <option value="">{{ $lmText('-- Collector --', '-- បុគ្គលិក --') }}</option>
                        @foreach($collectors as $c)
                            <option value="{{ $c->id }}" {{ (string) $c->id === (string) ($defaultCollectorId ?? '') ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Installment # / Ref', 'លេខកូដកម្ចី') }}</label>
                    <input type="text" name="loan_number" class="form-control lm-input-styled" placeholder="{{ $lmText('Auto if blank', 'ស្វ័យប្រវត្ត') }}">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Installment Note / Memo', 'ចំណាំកម្ចី') }}</label>
                    <input name="note" class="form-control lm-input-styled" placeholder="{{ $lmText('Note / conditions...', 'កំណត់ចំណាំ...') }}">
                    <input type="hidden" name="penalty_type" value="fixed">
                    <input type="hidden" name="penalty_amount" value="0">
                </div>
            </div>
        </div>
    </div>
</div>
