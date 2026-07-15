<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <form method="POST"
              action="{{ route('loan-management.loans.workflow.update', ['loan' => $loanRow->id] + (request()->boolean('_lm_modal') ? ['_lm_modal' => 1] : [])) }}"
              id="loanWorkflowEditForm">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="fa fa-sitemap"></i> Source &amp; Collection Workflow
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Type</label>
                            <input type="text" name="source_type" class="form-control" value="{{ old('source_type', $loanRow->source_type ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Source Created At</label>
                            <input type="date" name="source_created_at" class="form-control" value="{{ old('source_created_at', !empty($loanRow->source_created_at) ? \Carbon\Carbon::parse($loanRow->source_created_at)->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Collection Status</label>
                            <select name="collection_status" class="form-control">
                                <option value="">Select</option>
                                @foreach($collectionStatuses as $status)
                                    <option value="{{ $status }}" {{ old('collection_status', $loanRow->collection_status ?? '') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Risk Level</label>
                            <select name="risk_level" class="form-control">
                                <option value="">Select</option>
                                @foreach($riskLevels as $risk)
                                    <option value="{{ $risk }}" {{ old('risk_level', $loanRow->risk_level ?? '') === $risk ? 'selected' : '' }}>{{ ucfirst($risk) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Collection Priority</label><input type="number" min="0" name="collection_priority" class="form-control" value="{{ old('collection_priority', $loanRow->collection_priority ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Date</label><input type="date" name="ptp_date" class="form-control" value="{{ old('ptp_date', !empty($loanRow->ptp_date) ? \Carbon\Carbon::parse($loanRow->ptp_date)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>PTP Amount</label><input type="number" step="0.01" min="0" name="ptp_amount" class="form-control" value="{{ old('ptp_amount', $loanRow->ptp_amount ?? 0) }}"></div></div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>PTP Status</label>
                            <select name="ptp_status" class="form-control">
                                <option value="">Select</option>
                                @foreach($ptpStatuses as $status)
                                    <option value="{{ $status }}" {{ old('ptp_status', $loanRow->ptp_status ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Broken PTP Count</label><input type="number" min="0" name="broken_ptp_count" class="form-control" value="{{ old('broken_ptp_count', $loanRow->broken_ptp_count ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Contact At</label><input type="date" name="last_contact_at" class="form-control" value="{{ old('last_contact_at', !empty($loanRow->last_contact_at) ? \Carbon\Carbon::parse($loanRow->last_contact_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Next Followup At</label><input type="date" name="next_followup_at" class="form-control" value="{{ old('next_followup_at', !empty($loanRow->next_followup_at) ? \Carbon\Carbon::parse($loanRow->next_followup_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3">
                        <div class="checkbox" style="margin-top: 28px;">
                            <label><input type="checkbox" name="stock_already_deducted" value="1" {{ old('stock_already_deducted', $loanRow->stock_already_deducted ?? 0) ? 'checked' : '' }}> Stock already deducted</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="field_visit_required" value="1" {{ old('field_visit_required', $loanRow->field_visit_required ?? 0) ? 'checked' : '' }}> Field visit required</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Skip Level</label>
                            <select name="skip_level" class="form-control">
                                <option value="">Select</option>
                                @foreach($skipLevels as $skip)
                                    <option value="{{ $skip }}" {{ old('skip_level', $loanRow->skip_level ?? '') === $skip ? 'selected' : '' }}>{{ ucfirst($skip) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3"><div class="form-group"><label>Legal Stage</label><input type="text" name="legal_stage" class="form-control" value="{{ old('legal_stage', $loanRow->legal_stage ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Stage</label><input type="text" name="recovery_stage" class="form-control" value="{{ old('recovery_stage', $loanRow->recovery_stage ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Repossession Status</label><input type="text" name="repossession_status" class="form-control" value="{{ old('repossession_status', $loanRow->repossession_status ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Assigned Team</label><input type="text" name="assigned_collection_team" class="form-control" value="{{ old('assigned_collection_team', $loanRow->assigned_collection_team ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Days Past Due</label><input type="number" min="0" name="days_past_due" class="form-control" value="{{ old('days_past_due', $loanRow->days_past_due ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Overdue Bucket</label><input type="text" name="overdue_bucket" class="form-control" value="{{ old('overdue_bucket', $loanRow->overdue_bucket ?? '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Contact Attempts</label><input type="number" min="0" name="contact_attempt_count" class="form-control" value="{{ old('contact_attempt_count', $loanRow->contact_attempt_count ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Recovery Score</label><input type="number" min="0" name="recovery_score" class="form-control" value="{{ old('recovery_score', $loanRow->recovery_score ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Date</label><input type="date" name="last_payment_date" class="form-control" value="{{ old('last_payment_date', !empty($loanRow->last_payment_date) ? \Carbon\Carbon::parse($loanRow->last_payment_date)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Last Payment Amount</label><input type="number" step="0.01" min="0" name="last_payment_amount" class="form-control" value="{{ old('last_payment_amount', $loanRow->last_payment_amount ?? 0) }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Blacklisted At</label><input type="date" name="blacklisted_at" class="form-control" value="{{ old('blacklisted_at', !empty($loanRow->blacklisted_at) ? \Carbon\Carbon::parse($loanRow->blacklisted_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>Written Off At</label><input type="date" name="written_off_at" class="form-control" value="{{ old('written_off_at', !empty($loanRow->written_off_at) ? \Carbon\Carbon::parse($loanRow->written_off_at)->format('Y-m-d') : '') }}"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>Last Contact Result</label><input type="text" name="last_contact_result" class="form-control" value="{{ old('last_contact_result', $loanRow->last_contact_result ?? '') }}"></div></div>
                    <div class="col-md-6"><div class="form-group"><label>PTP Note</label><textarea name="ptp_note" class="form-control" rows="2">{{ old('ptp_note', $loanRow->ptp_note ?? '') }}</textarea></div></div>
                </div>
                <div class="alert alert-danger" id="loanWorkflowError" style="display:none;margin-bottom:0;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Save Workflow
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function ($) {
        $('#loanWorkflowEditForm').on('submit', function (event) {
            event.preventDefault();

            var form = $(this);
            var button = form.find('button[type="submit"]');
            var errorBox = $('#loanWorkflowError');

            errorBox.hide().text('');
            button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function (res) {
                    $('.view_modal').modal('hide');
                    if (window.toastr) {
                        toastr.success(res.message || 'Workflow updated successfully.');
                    }
                },
                error: function (xhr) {
                    var message = 'Unable to update workflow.';
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                        message = xhr.responseJSON.errors[firstKey][0] || message;
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    errorBox.text(message).show();
                },
                complete: function () {
                    button.prop('disabled', false).html('<i class="fa fa-save"></i> Save Workflow');
                }
            });
        });
    })(jQuery);
</script>
