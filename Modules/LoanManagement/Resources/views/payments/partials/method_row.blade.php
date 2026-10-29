<div class="lm-method-row" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
    @if(!empty($methodRow['id']))
        <input type="hidden" name="payment_details[{{ $index }}][id]" value="{{ $methodRow['id'] }}">
    @endif
    <div class="lm-form-row">
        <div class="lm-field">
            <label class="lm-label">{{ $lmText('Payment Method', 'វិធីទូទាត់') }}</label>
            <select name="payment_details[{{ $index }}][method]" class="lm-control lm-method-select" required>
                @php
                    $currentMethod = (string) ($methodRow['method'] ?? 'cash');
                    $currentMethodShown = false;
                @endphp
                @foreach($methods as $key => $label)
                    @php
                        $selected = $currentMethod === (string) $key || $currentMethod === (string) $label;
                        $currentMethodShown = $currentMethodShown || $selected;
                    @endphp
                    <option value="{{ $key }}" {{ $selected ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
                @if($currentMethod !== '' && !$currentMethodShown)
                    <option value="{{ $currentMethod }}" selected>{{ $currentMethod }}</option>
                @endif
            </select>
        </div>
        <div class="lm-field">
            <label class="lm-label">{{ $lmText('Amount', 'ទឹកប្រាក់') }} ({{ $loanCurrency }})</label>
            <input type="number" name="payment_details[{{ $index }}][amount]" class="lm-control lm-method-amount" min="0.01" step="0.01" value="{{ is_numeric($methodRow['amount'] ?? null) ? number_format((float) $methodRow['amount'], 2, '.', '') : '' }}" required>
        </div>
    </div>
    <div class="lm-field">
        <label class="lm-label">{{ $lmText('Reference / Transaction Code', 'លេខយោងប្រតិបត្តិការ') }}</label>
        <input type="text" name="payment_details[{{ $index }}][reference_number]" class="lm-control" maxlength="191" value="{{ $methodRow['reference_number'] ?? '' }}">
    </div>
    <button type="button" class="lm-remove-method lm-btn-action lm-btn-cancel" style="margin-top: 8px;">{{ $lmText('Remove', 'លុប') }}</button>
</div>
