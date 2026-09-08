@php
    $loanLanguage = session('user.language', config('app.locale'));
    $lmIsKhmer = $loanLanguage === 'km';
    $lmText = fn ($en, $km) => $lmIsKhmer ? $km : $en;
@endphp

<div class="lm-step-card lm-step-card-blue" id="sectionCustomer">
    <div class="lm-step-card-header">
        <div class="lm-step-card-title-wrap">
            <span class="lm-step-badge">1</span>
            <div>
                <h3 class="lm-step-title"><i class="fa fa-user-circle text-primary"></i> {{ $lmText('Customer Information & Identity', 'ព័ត៌មានអតិថិជន និងអត្តសញ្ញាណ') }}</h3>
                <p class="lm-step-subtitle">{{ $lmText('Search customer or scan ID card to auto-fill.', 'ស្វែងរក ឬស្កេនអត្តសញ្ញាណប័ណ្ណដើម្បីបំពេញស្វ័យប្រវត្តិ') }}</p>
            </div>
        </div>
        <div class="lm-step-header-actions" style="display:flex; gap:6px;">
            <button type="button" class="btn btn-default btn-xs lm-btn-clean" id="btnManualCustomerEntry" title="{{ $lmText('Enter customer manually', 'បញ្ចូលដោយដៃ') }}">
                <i class="fa fa-pencil text-primary"></i> {{ $lmText('Manual Entry', 'បញ្ចូលដោយដៃ') }}
            </button>
            <button type="button" class="btn btn-default btn-xs lm-btn-clean" id="btnClearCustomer" title="{{ $lmText('Clear Customer', 'សម្អាតអតិថិជន') }}">
                <i class="fa fa-refresh"></i> {{ $lmText('Reset', 'សម្អាត') }}
            </button>
        </div>
    </div>

    <div class="lm-step-card-body">
        <div class="row" style="margin-bottom: 6px;">
            <!-- Customer Search & OCR Trigger -->
            <div class="col-xs-12 col-sm-6 col-md-6" style="padding-right: 6px;">
                <div class="form-group lm-form-group" style="margin-bottom:4px;">
                    <label class="lm-field-label"><i class="fa fa-search text-muted"></i> {{ $lmText('Search Existing Customer', 'ស្វែងរកអតិថិជនចាស់') }}</label>
                    <div class="lm-customer-search-wrap">
                        <input type="text" id="customerSearchInput" class="form-control lm-input-styled" placeholder="{{ $lmText('Name, phone, or ID card...', 'វាយបញ្ចូលឈ្មោះ លេខទូរស័ព្ទ ឬអត្តសញ្ញាណប័ណ្ណ...') }}" autocomplete="off">
                        <div class="lm-customer-search-results"></div>
                    </div>
                </div>
            </div>

            <div class="col-xs-12 col-sm-6 col-md-6" style="padding-left: 6px;">
                <div class="form-group lm-form-group" style="margin-bottom:4px;">
                    <label class="lm-field-label"><i class="fa fa-id-card text-muted"></i> {{ $lmText('ID Card Smart OCR & Capture', 'ស្កេនអត្តសញ្ញាណប័ណ្ណ') }}</label>
                    <div class="lm-ocr-action-bar" style="margin-bottom:0; display:flex; gap:6px;">
                        <label class="btn btn-default btn-xs lm-ocr-btn" for="customer_id_card_camera_input" style="flex:1; justify-content:center; padding:4px 8px;">
                            <i class="fa fa-camera text-primary"></i> <span>{{ $lmText('Take Photo', 'ថតរូប') }}</span>
                        </label>
                        <label class="btn btn-default btn-xs lm-ocr-btn" for="customer_id_card_photo_input" style="flex:1; justify-content:center; padding:4px 8px;">
                            <i class="fa fa-upload text-success"></i> <span>{{ $lmText('Upload ID', 'បញ្ចូលរូប') }}</span>
                        </label>
                    </div>

                    <input type="file" id="customer_id_card_camera_input" accept="image/*" capture="environment" style="display:none;">
                    <input type="file" id="customer_id_card_photo_input" accept="image/*" style="display:none;">
                    <input type="hidden" name="id_card_ocr_raw_text" id="id_card_ocr_raw_text_input">
                    <input type="hidden" name="id_card_ocr_fields[id_card_number]" id="id_card_ocr_number_input">
                    <input type="hidden" name="id_card_ocr_fields[khmer_name]" id="id_card_ocr_khmer_name_input">
                    <input type="hidden" name="id_card_ocr_fields[english_name]" id="id_card_ocr_english_name_input">
                    <input type="hidden" name="id_card_ocr_fields[address]" id="id_card_ocr_address_input">

                    <div id="customer_id_card_photo_preview" class="lm-id-preview-box" style="display:none; max-width:180px; margin-top:4px;">
                        <img src="" alt="ID Card">
                    </div>
                    <p id="id_card_ocr_status" class="help-block lm-ocr-status-text" style="margin:2px 0 0; font-size:11px;"></p>
                </div>
            </div>
        </div>

        <input type="hidden" name="customer_id" id="customer_id_input" value="">

        <!-- Customer Detailed Profile Fields -->
        <div id="customer_info_fields" class="lm-customer-profile-block" style="display:none; margin-top:4px;">
            <div class="row" style="margin-left:-4px; margin-right:-4px;">
                <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                    <div class="form-group lm-form-group">
                        <label class="lm-field-label">{{ $lmText('Khmer Name', 'ឈ្មោះខ្មែរ') }} <span class="text-danger">*</span></label>
                        <input type="text" name="customer_khmer_name" id="customer_khmer_name_input" class="form-control lm-input-styled" required placeholder="{{ $lmText('Khmer name', 'ឈ្មោះខ្មែរ') }}">
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                    <div class="form-group lm-form-group">
                        <label class="lm-field-label">{{ $lmText('English Name', 'ឈ្មោះអង់គ្លេស') }} <span class="text-danger">*</span></label>
                        <input type="text" name="customer_english_name" id="customer_english_name_input" class="form-control lm-input-styled" required placeholder="{{ $lmText('English name', 'ឈ្មោះអង់គ្លេស') }}">
                        <input type="hidden" name="customer_name" id="customer_name_input">
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                    <div class="form-group lm-form-group">
                        <label class="lm-field-label">{{ $lmText('Phone Number', 'លេខទូរស័ព្ទ') }}</label>
                        <div class="input-group lm-input-group">
                            <input type="text" name="customer_phone" id="customer_phone_input" class="form-control lm-input-styled" placeholder="012 345 678">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default lm-btn-addon" id="btnShowAlternatePhone" title="{{ $lmText('Add secondary phone', 'បន្ថែមលេខទូរស័ព្ទទី២') }}" style="height:29px; padding:0 8px;">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3" style="padding-left:4px; padding-right:4px;">
                    <div class="form-group lm-form-group">
                        <label class="lm-field-label">{{ $lmText('ID Card / Passport', 'លេខអត្តសញ្ញាណប័ណ្ណ') }}</label>
                        <input type="text" name="id_card_number" id="customer_id_card_input" class="form-control lm-input-styled" placeholder="012345678">
                    </div>
                </div>

                <div class="col-xs-6 col-sm-3" id="alternate_phone_group" style="display:none; padding-left:4px; padding-right:4px;">
                    <div class="form-group lm-form-group">
                        <label class="lm-field-label">{{ $lmText('Alternate Phone', 'លេខទូរស័ព្ទទី២') }}</label>
                        <input type="text" name="alternate_phone" id="alternate_phone_input" class="form-control lm-input-styled" placeholder="{{ $lmText('Secondary phone', 'លេខទូរស័ព្ទបន្ថែម') }}">
                    </div>
                </div>
                <input type="hidden" name="customer_group_name" value="រំលស់">
                <input type="hidden" name="customer_address" id="customer_address_input">
            </div>

            <!-- Standalone Live Duplicate Customer Alert Banner -->
            <div id="fullpageCustomerDuplicateAlert" style="display: none; margin-top: 6px; margin-bottom: 6px; padding: 6px 10px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 6px; flex: 1;">
                        <i class="fa fa-exclamation-triangle" style="color: #d97706; font-size: 13px;"></i>
                        <div style="font-size: 11px; font-weight: 700; color: #92400e;" id="fullpageCustomerDuplicateTitle">
                            {{ $lmText('Existing Customer Found!', 'បានរកឃើញអតិថិជនមានស្រាប់!') }}
                        </div>
                        <div style="font-size: 11px; color: #b45309;" id="fullpageCustomerDuplicateDesc"></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <button type="button" class="btn btn-warning btn-xs" id="fullpageBtnLinkDuplicateCustomer" style="font-weight: 600; border-radius: 4px; padding: 2px 8px; font-size: 10.5px;">
                            <i class="fa fa-link"></i> <span>{{ $lmText('Link This Customer', 'ភ្ជាប់អតិថិជននេះ') }}</span>
                        </button>
                        <button type="button" class="btn btn-default btn-xs" id="fullpageBtnDismissDuplicateAlert" style="border-radius: 4px; padding: 2px 6px;" title="Dismiss">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KYC Documents & Extra Attachments (Collapsible Accordion to save space) -->
        <div class="lm-doc-section" style="margin-top:6px;">
            <div class="lm-doc-toggle-strip" id="btnToggleDocs" style="cursor:pointer; display:flex; align-items:center; justify-content:space-between; padding:4px 8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; transition:background .15s;">
                <span style="font-size:11px; font-weight:700; color:#475569;">
                    <i class="fa fa-paperclip text-primary"></i> {{ $lmText('KYC Documents & Extra Attachments', 'ឯកសារភ្ជាប់ & KYC') }}
                    <span class="badge" id="lmDocCountBadge" style="background:#2563eb; font-size:9.5px; padding:2px 6px; margin-left:4px;">0</span>
                </span>
                <span style="font-size:11px; color:#64748b; font-weight:600;">
                    <i class="fa fa-chevron-down" id="lmDocChevron"></i>
                </span>
            </div>

            <div id="lmDocSectionBody" style="display:none; margin-top:8px; border:1px solid #e2e8f0; border-radius:6px; padding:8px; background:#fcfdfe;">
                <div class="lm-doc-grid" id="lmDocGrid" style="margin-top:0;">
                    <label class="lm-doc-add" for="lmDocInput" style="min-height:70px; padding:4px;">
                        <i class="fa fa-cloud-upload" style="font-size:18px;"></i>
                        <span style="font-size:10px;">{{ $lmText('Upload File', 'បញ្ចូលឯកសារ') }}</span>
                    </label>
                </div>
                <input type="file" id="lmDocInput" accept="image/*,.pdf,.txt,.csv,.doc,.docx" multiple style="display:none;">

                <div class="row" style="margin-top:8px;">
                    <div class="col-sm-12 col-md-6" style="padding-right:6px;">
                        <label class="lm-field-label-sub" style="font-size:10.5px;">{{ $lmText('Notes / Memo', 'កំណត់សម្គាល់ឯកសារ') }}</label>
                        <textarea name="document_text" class="form-control lm-textarea-styled" rows="2" placeholder="{{ $lmText('Write notes, guarantor details...', 'កំណត់ចំណាំ...') }}" style="height:52px; font-size:11.5px;"></textarea>
                    </div>
                    <div class="col-sm-12 col-md-6" style="padding-left:6px;">
                        <label class="lm-field-label-sub" style="font-size:10.5px;">{{ $lmText('Cloud Links', 'តំណភ្ជាប់ឯកសារ Drive/Cloud') }}</label>
                        <div id="lmDocumentLinks">
                            <div class="input-group lm-input-group" style="margin-bottom:4px;">
                                <input type="url" name="document_links[]" class="form-control lm-input-styled" placeholder="https://drive.google.com/..." style="height:27px; font-size:11px;">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default lm-btn-addon" id="btnAddDocumentLink" title="{{ $lmText('Add another link', 'បន្ថែមតំណភ្ជាប់') }}" style="height:27px; padding:0 8px;">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lm-doc-paste-hint" id="lmDocPasteHint" style="margin-top:6px; padding:4px 8px; font-size:11px;">
                    <i class="fa fa-keyboard-o"></i>
                    <span>{{ $lmText('Tip: Paste screenshots directly with Ctrl+V', 'គន្លឹះ៖ ចុច Ctrl+V ដើម្បីបិទភ្ជាប់រូបភាពភ្លាមៗ') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
