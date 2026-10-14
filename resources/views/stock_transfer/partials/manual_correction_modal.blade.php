<style>
    #manual_stock_correction_modal { color: #243247; }
    #manual_stock_correction_modal .modal-dialog { width: calc(100% - 32px); max-width: 1120px; margin: 24px auto; }
    #manual_stock_correction_modal .modal-content { border: 0; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 70px rgba(15,23,42,.2); max-height: calc(100vh - 48px); display: flex; flex-direction: column; }
    #manual_stock_correction_modal .modal-header { padding: 22px 24px 18px; border-color: #e6eaf0; flex-shrink: 0; }
    #manual_stock_correction_modal .modal-title { font-size: 21px; font-weight: 600; color: #17263c; }
    #manual_stock_correction_context { margin: 7px 0 0; font-size: 13px; color: #718096; }
    #manual_stock_correction_modal .modal-body { padding: 20px 24px; font-size: 13px; overflow-y: auto; min-height: 0; background: #f7f9fc; }
    #manual_stock_correction_modal .manual-section { padding: 18px; background: #fff; border: 1px solid #e3e9f1; border-radius: 10px; margin-bottom: 16px; }
    #manual_stock_correction_modal .manual-section h5 { font-weight: 600; font-size: 14px; margin: 0 0 7px; }
    #manual_stock_correction_modal .manual-help { color: #718096; font-size: 12px; line-height: 1.5; margin: 6px 0 0; }
    #manual_stock_correction_modal .manual-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin: 14px 0; }
    #manual_stock_correction_modal .manual-toolbar label { font-size: 12px; font-weight: 400; margin: 0; }
    #manual_stock_correction_modal .manual-records-table { table-layout: fixed; width: 100%; margin: 0; border: 1px solid #e6eaf0; }
    #manual_stock_correction_modal .table > thead > tr > th { background: #f3f6fa; border-bottom: 1px solid #e3e9f1; color: #627187; font-size: 12px; font-weight: 600; padding: 10px 12px; }
    #manual_stock_correction_modal .table > tbody > tr > td { border-color: #e6eaf0; padding: 14px 12px; vertical-align: top; overflow-wrap: anywhere; }
    #manual_stock_correction_modal .manual-line-label { display: flex; align-items: flex-start; gap: 7px; margin: 0 0 8px; font-size: 12px; }
    #manual_stock_correction_modal .manual-serial { display: block; font-size: 14px; color: #17263c; margin-bottom: 3px; }
    #manual_stock_correction_modal .manual-line-meta { font-size: 12px; color: #718096; }
    #manual_stock_correction_modal .manual-evidence-row > td { background: #f8fafc; padding: 16px; }
    #manual_stock_correction_modal .manual-candidate { background: #fff; border: 1px solid #e3e9f1; border-radius: 8px; padding: 14px; height: 100%; }
    #manual_stock_correction_modal .manual-candidate p, #manual_stock_correction_modal .manual-candidate ul { font-size: 12px; line-height: 1.6; margin: 7px 0; }
    #manual_stock_correction_modal .manual-candidate ul { padding-left: 18px; }
    #manual_stock_correction_modal .select2-container { min-width: 0; max-width: 100%; }
    #manual_stock_correction_modal .select2-selection--single { min-height: 68px; height: auto; border: 1px solid #dbe2ec; border-radius: 7px; background: #fff; }
    #manual_stock_correction_modal .select2-selection__rendered { white-space: normal; line-height: 19px; font-size: 12px; padding: 9px 25px 9px 10px; }
    #manual_stock_correction_modal .select2-selection__arrow { top: 20px; }
    #manual_stock_correction_modal .form-control { border: 1px solid #dbe2ec; border-radius: 7px; box-shadow: none; font-size: 13px; }
    #manual_stock_correction_modal textarea.form-control { resize: vertical; min-height: 100px; line-height: 1.6; }
    #manual_stock_correction_modal input[type=checkbox] { accent-color: #2563eb; }
    #manual_stock_correction_modal .manual-verification { display: flex; align-items: flex-start; gap: 8px; padding: 13px 15px; border: 1px solid #dbe7f5; background: #f1f6fd; border-radius: 8px; font-weight: 500; margin: 0; }
    #manual_stock_correction_error { background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; color: #b4233b; }
    #manual_stock_correction_error:empty { display: none; }
    #manual_stock_correction_error p:last-child { margin-bottom: 0; }
    #manual_stock_corrected_date[readonly]:not([disabled]) { background: #fff; cursor: pointer; }
    #manual_stock_correction_preview { padding: 18px; background: #fff; border: 1px solid #dbe7f5; border-radius: 10px; margin-top: 16px; }
    #manual_stock_correction_preview h5 { font-size: 15px; font-weight: 600; margin: 0 0 12px; }
    #manual_stock_correction_modal .modal-footer { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 15px 24px; background: #fff; border-color: #e6eaf0; flex-shrink: 0; }
    #manual_stock_correction_stage { flex: 1 1 160px; text-align: left; font-size: 12px; color: #627187; }
    #manual_stock_correction_modal .btn { border-radius: 7px; font-size: 12px; padding: 9px 13px; }
    #manual_stock_correction_modal .modal-footer .btn + .btn { margin-left: 0; }
    #manual_stock_correction_modal .btn-primary { background: #2563eb; border-color: #2563eb; }
    #manual_stock_correction_modal .btn[disabled] { opacity: .45; }
    @media (max-width: 767px) {
        #manual_stock_correction_modal .modal-dialog { width: calc(100% - 16px); margin: 8px auto; }
        #manual_stock_correction_modal .modal-content { max-height: calc(100vh - 16px); }
        #manual_stock_correction_modal .modal-header, #manual_stock_correction_modal .modal-body, #manual_stock_correction_modal .modal-footer { padding: 14px; }
        #manual_stock_correction_modal .manual-section { padding: 12px; }
        #manual_stock_correction_modal .manual-records-table thead, #manual_stock_correction_modal .manual-records-table colgroup { display: none; }
        #manual_stock_correction_modal .manual-records-table, #manual_stock_correction_modal .manual-records-table tbody, #manual_stock_correction_modal .manual-records-table tr, #manual_stock_correction_modal .manual-records-table td { display: block; width: 100%; }
        #manual_stock_correction_modal .manual-records-table td[data-label]::before { content: attr(data-label); display: block; font-size: 11px; font-weight: 600; color: #718096; margin-bottom: 7px; }
        #manual_stock_correction_modal .manual-toolbar { align-items: flex-start; flex-direction: column; }
        #manual_stock_correction_stage { flex-basis: 100%; }
        #manual_stock_correction_modal .modal-footer .btn { flex: 1 1 auto; }
    }
</style>
<div class="modal fade" id="manual_stock_correction_modal" tabindex="-1" role="dialog" aria-labelledby="manual_stock_correction_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="manual_stock_correction_title">Verified Serial Correction</h4>
                <p id="manual_stock_correction_context" class="text-muted"></p>
                <span id="manual_stock_overselling_badge" class="label label-info" style="display:none">Overselling enabled</span>
            </div>
            <div class="modal-body">
                <div id="manual_stock_correction_error" role="alert" tabindex="-1"></div>
                <div class="manual-toolbar">
                    <strong id="manual_stock_correction_summary" role="status"></strong>
                    <label id="manual_matching_lines_toggle" ><input type="checkbox" id="show_matching_stock_lines"> Show matching records</label>
                </div>
                <form id="manual_stock_correction_form">
                    <div class="manual-section"><h5>1. Choose the records</h5><p class="manual-help">Defaults use this transfer’s recorded serials. Open Details to compare other receipts.</p>
                    <div>
                        <table class="table manual-records-table">
                            <colgroup><col style="width:20%"><col style="width:32%"><col style="width:30%"><col style="width:18%"></colgroup>
                            <thead><tr><th>Serial</th><th>Source receipt</th><th>Receiving batch</th><th>Review</th></tr></thead>
                            <tbody id="manual_stock_correction_lines"></tbody>
                        </table>
                    </div>
                    </div>
                    <div class="manual-section"><h5>2. Verify the correction</h5>
                    <div class="row" style="margin-top:14px">
                    <div class="col-sm-8 form-group">
                        <label for="manual_stock_correction_evidence">Correction notes</label>
                        <textarea id="manual_stock_correction_evidence" class="form-control" rows="4" aria-describedby="manual_stock_notes_hint" minlength="10" maxlength="2000" required></textarea>
                        <p id="manual_stock_notes_hint" class="manual-help">A record summary is filled for you. Add the delivery document or scan details you used to verify it.</p>
                    </div>
                    <div class="col-sm-4 form-group">
                        <label><input type="checkbox" id="manual_stock_date_verified"> Change delivery date</label>
                        <div class="input-group manual-date-control" style="position:relative">
                            <input type="text" id="manual_stock_corrected_date" class="form-control" aria-label="Verified delivery date" readonly disabled autocomplete="off">
                            <span class="input-group-btn">
                                <button type="button" id="choose_manual_stock_date" class="btn btn-default" title="Choose verified delivery date and time" aria-label="Choose verified delivery date and time" disabled><i class="fa fa-calendar" aria-hidden="true"></i></button>
                            </span>
                        </div>
                        <p id="manual_stock_date_bounds" class="text-muted" style="margin-top:6px"></p>
                        <p class="manual-help">The recorded date is filled by default. Enable this only when delivery records confirm a different date.</p>
                    </div>
                    </div>
                    <label class="manual-verification"><input type="checkbox" id="manual_stock_correction_verified" required> <span>I checked the physical serial and the supporting delivery records.</span></label>
                    </div>
                </form>
                <div id="manual_stock_correction_preview" style="display:none">
                    <h5>3. Review proposed changes</h5>
                    <p id="manual_stock_remaining_issues" class="text-warning"></p>
                    <p id="manual_stock_date_change"></p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead><tr><th>Line</th><th>Source Batch Before</th><th>Source Batch After</th><th>Received Serial Before</th><th>Received Serial After</th></tr></thead>
                            <tbody id="manual_stock_correction_changes"></tbody>
                        </table>
                    </div>
                    <div id="manual_stock_shortage_preview" class="alert alert-warning" style="display:none"><strong id="manual_stock_shortage_title">Source stock balances</strong><div id="manual_stock_shortage_changes"></div></div>
                    <div id="manual_stock_related_preview" style="display:none"><strong>Related allocation repairs included</strong><ul id="manual_stock_related_changes"></ul></div>
                    <div class="table-responsive" id="manual_stock_counter_preview">
                        <table class="table table-bordered table-condensed">
                            <thead><tr><th>Source Batch</th><th>Recorded Consumed Before</th><th>Verified Consumed After</th></tr></thead>
                            <tbody id="manual_stock_counter_changes"></tbody>
                        </table>
                    </div>
                    <p>No changes have been saved. Product quantities, location quantities, prices and payments will remain unchanged.</p>
                    <label><input type="checkbox" id="confirm_manual_stock_correction"> I approve these exact changes.</label>
                </div>
            </div>
            <div class="modal-footer">
                <span id="manual_stock_correction_stage" class="pull-left text-muted" role="status">Awaiting Selection</span>
                <button type="button" class="btn btn-default" id="download_manual_stock_correction" disabled title="Download verified correction preview"><i class="fa fa-download" aria-hidden="true"></i> Download Preview</button>
                <button type="button" class="btn btn-default" id="preview_manual_stock_correction"><i class="fa fa-search" aria-hidden="true"></i> Preview correction</button>
                <button type="button" class="btn btn-primary" id="apply_manual_stock_correction" disabled><i class="fa fa-check" aria-hidden="true"></i> Apply correction</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
