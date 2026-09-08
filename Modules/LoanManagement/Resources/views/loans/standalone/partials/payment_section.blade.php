@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

<div class="lm-step-card lm-step-card-emerald" id="sectionPayment">
    <div class="lm-step-card-header">
        <div class="lm-step-card-title-wrap">
            <span class="lm-step-badge">4</span>
            <div>
                <h3 class="lm-step-title"><i class="fa fa-money text-success"></i> {{ $lmText('Down Payment & Initial Settlement', 'ប្រាក់កក់ដំបូង') }}</h3>
                <p class="lm-step-subtitle">{{ $lmText('Record upfront down payment. Leave 0 for 100% financing.', 'កត់ត្រាប្រាក់កក់ដំបូង។ បើគ្មាន សូមទុក 0') }}</p>
            </div>
        </div>
        <div class="lm-step-header-actions">
            <button type="button" class="btn btn-default btn-xs lm-btn-clean" id="btnToggleBankDetails" style="font-size:10px; padding:2px 7px;">
                <i class="fa fa-university text-muted"></i> {{ $lmText('Bank Details', 'ព័ត៌មានធនាគារ') }}
            </button>
        </div>
    </div>

    <div class="lm-step-card-body">
        <div class="row" style="margin-left:-4px; margin-right:-4px;">
            <!-- Primary Row: Down Payment, Date, Method, Ref # -->
            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <div class="lm-label-with-shortcuts">
                        <label class="lm-field-label">{{ $lmText('Down Payment', 'ប្រាក់កក់ដំបូង') }}</label>
                        <div class="lm-quick-presets" id="downPaymentPercentPresets">
                            <button type="button" class="lm-preset-btn active" data-pct="0">0%</button>
                            <button type="button" class="lm-preset-btn" data-pct="10">10%</button>
                            <button type="button" class="lm-preset-btn" data-pct="20">20%</button>
                            <button type="button" class="lm-preset-btn" data-pct="30">30%</button>
                            <button type="button" class="lm-preset-btn" data-pct="50">50%</button>
                        </div>
                    </div>
                    <input type="number" step="0.01" id="payment_amount_input" name="payment[amount]" class="form-control lm-input-styled lm-input-emerald" value="0" min="0">
                    <input type="hidden" id="down_payment_hidden" name="down_payment" value="0">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Received Date', 'កាលបរិច្ឆេទទទួល') }}</label>
                    <input type="date" name="payment[paid_date]" class="form-control lm-input-styled" value="{{ date('Y-m-d') }}">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Payment Method', 'វិធីសាស្ត្រទូទាត់') }}</label>
                    {!! Form::select('payment[method]', $paymentTypes ?? [], $defaultPaymentMethod ?? 'cash', ['class' => 'form-control lm-input-styled select2', 'style' => 'width:100%;']) !!}
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Ref / Slip Number', 'លេខយោងបង្កាន់ដៃ') }}</label>
                    <input name="payment[reference_number]" class="form-control lm-input-styled" placeholder="Ref #">
                </div>
            </div>

            <!-- Second Row: Status, Officer, Currency, Note -->
            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Payment Status', 'ស្ថានភាពទូទាត់') }}</label>
                    <select name="payment[status]" class="form-control lm-input-styled">
                        <option value="completed" selected>{{ $lmText('Completed (ទូទាត់រួច)', 'ទូទាត់រួច (Completed)') }}</option>
                        <option value="pending">{{ $lmText('Pending (រង់ចាំ)', 'រង់ចាំ (Pending)') }}</option>
                    </select>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Received By', 'អ្នកទទួល') }}</label>
                    <input class="form-control lm-input-styled" value="{{ trim((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? '')) }}" readonly>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Currency', 'រូបិយប័ណ្ណ') }}</label>
                    <select name="payment[currency]" class="form-control lm-input-styled">
                        <option value="USD" selected>USD ($)</option>
                        <option value="KHR">KHR (៛)</option>
                    </select>
                    <input type="hidden" name="payment[exchange_rate]" value="1">
                </div>
            </div>

            <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                <div class="form-group lm-form-group">
                    <label class="lm-field-label">{{ $lmText('Payment Note', 'ចំណាំការទូទាត់') }}</label>
                    <input name="payment[note]" class="form-control lm-input-styled" placeholder="{{ $lmText('Down payment memo...', 'ចំណាំ...') }}">
                </div>
            </div>

            <!-- Optional Extra Bank Details Accordion -->
            <div class="col-xs-12" id="lmBankDetailsCollapse" style="display:none; margin-top:4px; border-top:1px dashed #cbd5e1; padding-top:6px;">
                <div class="row" style="margin-left:-4px; margin-right:-4px;">
                    <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                        <div class="form-group lm-form-group">
                            <label class="lm-field-label">{{ $lmText('Account Name', 'ឈ្មោះគណនី') }}</label>
                            <input name="payment[account_name]" class="form-control lm-input-styled" placeholder="ABA/ACLEDA">
                        </div>
                    </div>
                    <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                        <div class="form-group lm-form-group">
                            <label class="lm-field-label">{{ $lmText('Account Number', 'លេខគណនី') }}</label>
                            <input name="payment[account_number]" class="form-control lm-input-styled" placeholder="000 123 456">
                        </div>
                    </div>
                    <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                        <div class="form-group lm-form-group">
                            <label class="lm-field-label">{{ $lmText('Bank Trx ID', 'លេខប្រតិបត្តិការ') }}</label>
                            <input name="payment[transaction_id]" class="form-control lm-input-styled" placeholder="TRX-987654">
                        </div>
                    </div>
                    <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                        <div class="form-group lm-form-group">
                            <label class="lm-field-label">{{ $lmText('Channel', 'បណ្តាញ') }}</label>
                            <input name="payment[channel]" class="form-control lm-input-styled" placeholder="Bakong / ABA">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
