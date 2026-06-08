<?php $__env->startSection('title', 'Loan Locations'); ?>

<?php $__env->startSection('loan_css'); ?>
    <style>
        .loan-location-thumb { max-height: 44px; max-width: 90px; }
        .loan-location-qr { max-height: 54px; max-width: 54px; }
        .loan-location-actions .btn { margin: 0 3px 4px 0; }
        .text-ellipsis {
            display: block;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .loan-asset-field { display: flex; gap: 6px; align-items: center; }
        .loan-asset-field input[type="file"] { flex: 1; min-width: 0; }
        .loan-asset-preview { margin-top: 8px; min-height: 48px; }
        .loan-asset-preview img { max-width: 96px; max-height: 64px; border: 1px solid #ddd; border-radius: 4px; padding: 2px; background: #fff; }
        .loan-asset-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
            gap: 10px;
            max-height: 60vh;
            overflow: auto;
        }
        .loan-asset-gallery-item {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #fff;
            padding: 6px;
            text-align: left;
        }
        .loan-asset-gallery-item:hover { border-color: #3c8dbc; }
        .loan-asset-gallery-item img {
            width: 100%;
            height: 86px;
            object-fit: contain;
            background: #f7f7f7;
            border-radius: 4px;
        }
        .loan-asset-gallery-name {
            display: block;
            margin-top: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
        }
        .loan-asset-gallery-date { display: block; color: #777; font-size: 11px; }
    </style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content_body'); ?>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Loan Locations
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Manage loan branches, invoice prefixes, print assets, and Telegram routing</small>
    </h1>
</section>

<section class="content">
    <?php if(session('status')): ?>
        <?php $status = session('status'); ?>
        <div class="alert alert-<?php echo e(!empty($status['success']) ? 'success' : 'danger', false); ?>">
            <?php echo e($status['msg'] ?? '', false); ?>

        </div>
    <?php endif; ?>

    <?php if(isset($errors) && $errors->any()): ?>
        <div class="alert alert-danger">
            <ul style="margin-bottom:0;">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error, false); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php $__env->startComponent('components.filters', ['title' => __('report.filters')]); ?>
        <form method="GET" action="<?php echo e(route('loan-management.locations.index'), false); ?>" id="loan_location_filter_form">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo e($filters['name'] ?? '', false); ?>" placeholder="Location name">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Location ID</label>
                        <input type="text" name="location_code" class="form-control" value="<?php echo e($filters['location_code'] ?? '', false); ?>" placeholder="Location ID">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo e($filters['phone'] ?? '', false); ?>" placeholder="Phone">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All</option>
                            <option value="active" <?php echo e(($filters['status'] ?? '') === 'active' ? 'selected' : '', false); ?>>Active</option>
                            <option value="inactive" <?php echo e(($filters['status'] ?? '') === 'inactive' ? 'selected' : '', false); ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 text-right">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply</button>
                    <a href="<?php echo e(route('loan-management.locations.index'), false); ?>" class="btn btn-default">Reset</a>
                </div>
            </div>
        </form>
    <?php echo $__env->renderComponent(); ?>

    <?php $__env->startComponent('components.widget', ['class' => 'box-primary', 'title' => 'All Loan Locations']); ?>
        <?php $__env->slot('tool'); ?>
            <div class="box-tools">
                <a href="<?php echo e(route('loan-management.locations.template'), false); ?>"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-info pull-right tw-mb-2 tw-mr-2">
                    <i class="fa fa-download"></i> Template
                </a>
                <a href="<?php echo e(route('loan-management.locations.export'), false); ?>"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-success pull-right tw-mb-2 tw-mr-2">
                    <i class="fa fa-file-excel-o"></i> Export
                </a>
                <button type="button"
                    class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-warning pull-right tw-mb-2 tw-mr-2"
                    data-toggle="modal" data-target="#loan_location_import_modal">
                    <i class="fa fa-upload"></i> Import
                </button>
                <button type="button"
                    class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right tw-mb-2"
                    data-toggle="modal" data-target="#loan_location_add_modal">
                    <i class="fa fa-plus"></i> Add
                </button>
            </div>
        <?php $__env->endSlot(); ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="loan_location_table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location ID</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Loan Invoice Prefix</th>
                        <th>Assets</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $invoicePrefixExample = rtrim(trim((string) ($location->loan_invoice_prefix ?? '')), '-/');
                            $invoicePrefixExample = $invoicePrefixExample !== '' ? $invoicePrefixExample : 'LN';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo e($location->name ?? '-', false); ?></strong>
                                <?php if(! empty($location->main_location_id)): ?>
                                    <br><small class="text-muted">POS Location #<?php echo e($location->main_location_id, false); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($location->location_code ?? '-', false); ?></td>
                            <td><span class="text-ellipsis" title="<?php echo e($location->address ?? '', false); ?>"><?php echo e($location->address ?? '-', false); ?></span></td>
                            <td><?php echo e($location->phone ?? '-', false); ?></td>
                            <td>
                                <?php echo e($location->loan_invoice_prefix ?? '-', false); ?><br>
                                <small class="text-muted"><?php echo e($invoicePrefixExample, false); ?>-<?php echo e(date('Ymd'), false); ?>-000001</small>
                            </td>
                            <td>
                                <?php if(! empty($location->logo_asset_url)): ?>
                                    <img src="<?php echo e($location->logo_asset_url, false); ?>" alt="Logo" class="loan-location-thumb" onerror="this.style.display='none';">
                                <?php endif; ?>
                                <?php if(! empty($location->payment_qr_asset_url)): ?>
                                    <img src="<?php echo e($location->payment_qr_asset_url, false); ?>" alt="Payment QR" class="loan-location-qr" onerror="this.style.display='none';">
                                <?php endif; ?>
                                <?php if(! empty($location->telegram_qr_asset_url)): ?>
                                    <img src="<?php echo e($location->telegram_qr_asset_url, false); ?>" alt="Telegram QR" class="loan-location-qr" onerror="this.style.display='none';">
                                <?php endif; ?>
                                <?php if(empty($location->logo_asset_url) && empty($location->payment_qr_asset_url) && empty($location->telegram_qr_asset_url)): ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="label label-<?php echo e(($location->status ?? 'active') === 'active' ? 'success' : 'default', false); ?>">
                                    <?php echo e(ucfirst($location->status ?? 'active'), false); ?>

                                </span>
                            </td>
                            <td class="loan-location-actions">
                                <button type="button"
                                    class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary loan-location-edit-btn"
                                    data-toggle="modal"
                                    data-target="#loan_location_edit_modal"
                                    data-id="<?php echo e($location->id, false); ?>"
                                    data-name="<?php echo e($location->name ?? '', false); ?>"
                                    data-location_code="<?php echo e($location->location_code ?? '', false); ?>"
                                    data-loan_invoice_prefix="<?php echo e($location->loan_invoice_prefix ?? '', false); ?>"
                                    data-phone="<?php echo e($location->phone ?? '', false); ?>"
                                    data-status="<?php echo e($location->status ?? 'active', false); ?>"
                                    data-address="<?php echo e($location->address ?? '', false); ?>">
                                    <i class="glyphicon glyphicon-edit"></i> Edit
                                </button>
                                <button type="button"
                                    class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info loan-location-assets-btn"
                                    data-toggle="modal"
                                    data-target="#loan_location_assets_modal"
                                    data-id="<?php echo e($location->id, false); ?>"
                                    data-name="<?php echo e($location->name ?? '', false); ?>"
                                    data-logo_path="<?php echo e($location->logo_path ?? '', false); ?>"
                                    data-logo_url="<?php echo e($location->logo_asset_url ?? '', false); ?>"
                                    data-payment_qr_path="<?php echo e($location->payment_qr_path ?? '', false); ?>"
                                    data-payment_qr_url="<?php echo e($location->payment_qr_asset_url ?? '', false); ?>"
                                    data-telegram_qr_path="<?php echo e($location->telegram_qr_path ?? '', false); ?>"
                                    data-telegram_qr_url="<?php echo e($location->telegram_qr_asset_url ?? '', false); ?>"
                                    data-telegram_payment_chat_id="<?php echo e($location->telegram_payment_chat_id ?? '', false); ?>"
                                    data-telegram_installment_chat_id="<?php echo e($location->telegram_installment_chat_id ?? '', false); ?>"
                                    data-telegram_notify_payment="<?php echo e(! empty($location->telegram_notify_payment) ? 1 : 0, false); ?>"
                                    data-telegram_notify_installment="<?php echo e(! empty($location->telegram_notify_installment) ? 1 : 0, false); ?>">
                                    <i class="fa fa-image"></i> Assets
                                </button>
                                <form method="POST" action="<?php echo e(route('loan-management.locations.destroy', $location->id), false); ?>" onsubmit="return confirm('Delete this location?');" style="display:inline-block;">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No locations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php echo $__env->renderComponent(); ?>

    <div class="modal fade" id="loan_location_add_modal" tabindex="-1" role="dialog" aria-labelledby="loanLocationAddModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="<?php echo e(route('loan-management.locations.store'), false); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="loanLocationAddModalLabel">Add Loan Location</h4>
                    </div>
                    <div class="modal-body">
                        <?php echo $__env->make('loanmanagement::locations.partials.form', ['location' => null], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save</button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loan_location_import_modal" tabindex="-1" role="dialog" aria-labelledby="loanLocationImportModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="<?php echo e(route('loan-management.locations.import'), false); ?>" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="loanLocationImportModalLabel">Import Loan Locations</h4>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            Required column: <code>name</code><br>
                            Optional columns: <code>location_code</code>, <code>loan_invoice_prefix</code>, <code>address</code>, <code>phone</code>, <code>status</code>
                        </div>
                        <div class="form-group">
                            <label>Import File</label>
                            <input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx" required>
                            <p class="help-block">Use the template file for best results.</p>
                        </div>
                        <div class="form-group">
                            <label>Duplicate Records</label>
                            <select name="duplicate_mode" class="form-control">
                                <option value="skip">Skip duplicate</option>
                                <option value="replace">Replace existing</option>
                            </select>
                            <p class="help-block">Existing records match by <code>location_code</code> first, then by <code>name</code>.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Import</button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loan_location_edit_modal" tabindex="-1" role="dialog" aria-labelledby="loanLocationEditModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="" id="loan_location_edit_form">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="loanLocationEditModalLabel">Edit Loan Location</h4>
                    </div>
                    <div class="modal-body">
                        <?php echo $__env->make('loanmanagement::locations.partials.form', ['location' => null], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save</button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loan_location_assets_modal" tabindex="-1" role="dialog" aria-labelledby="loanLocationAssetsModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <?php echo Form::open(['url' => '', 'method' => 'post', 'files' => true, 'id' => 'loan_location_assets_form']); ?>

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="loanLocationAssetsModalLabel">Location Assets & Telegram</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Logo</label>
                                <div class="loan-asset-field">
                                    <input type="file" name="logo" class="form-control" accept="image/*">
                                    <button type="button" class="btn btn-default loan-asset-gallery-open" title="Choose from gallery" data-target-input="logo_existing_shared" data-preview="logo_preview_shared">
                                        <i class="fa fa-picture-o"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="logo_existing" id="logo_existing_shared">
                                <div class="loan-asset-preview" id="logo_preview_shared"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Payment QR Code</label>
                                <div class="loan-asset-field">
                                    <input type="file" name="payment_qr" class="form-control" accept="image/*">
                                    <button type="button" class="btn btn-default loan-asset-gallery-open" title="Choose from gallery" data-target-input="payment_qr_existing_shared" data-preview="payment_qr_preview_shared">
                                        <i class="fa fa-picture-o"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="payment_qr_existing" id="payment_qr_existing_shared">
                                <div class="loan-asset-preview" id="payment_qr_preview_shared"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Telegram QR Code</label>
                                <div class="loan-asset-field">
                                    <input type="file" name="telegram_qr" class="form-control" accept="image/*">
                                    <button type="button" class="btn btn-default loan-asset-gallery-open" title="Choose from gallery" data-target-input="telegram_qr_existing_shared" data-preview="telegram_qr_preview_shared">
                                        <i class="fa fa-picture-o"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="telegram_qr_existing" id="telegram_qr_existing_shared">
                                <div class="loan-asset-preview" id="telegram_qr_preview_shared"></div>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <hr>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Payment Telegram Chat ID</label>
                                <input type="text" name="telegram_payment_chat_id" class="form-control" value="" placeholder="-100xxxxxxxxxx">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="telegram_notify_payment" value="1">
                                        Send Telegram when payment is received
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Installment Telegram Chat ID</label>
                                <input type="text" name="telegram_installment_chat_id" class="form-control" value="" placeholder="-100xxxxxxxxxx">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="telegram_notify_installment" value="1">
                                        Send Telegram when installment loan is created
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">Save</button>
                    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">Close</button>
                </div>
                <?php echo Form::close(); ?>

            </div>
        </div>
    </div>

    <div class="modal fade" id="loan_asset_gallery_modal" tabindex="-1" role="dialog" aria-labelledby="loanAssetGalleryModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="loanAssetGalleryModalLabel">Choose Existing Image</h4>
                </div>
                <div class="modal-body" id="loanAssetGalleryModalBody">
                    <div class="text-center text-muted" style="padding: 24px 0;">
                        <i class="fa fa-spinner fa-spin"></i> Loading image gallery...
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('loan_js'); ?>
    <script>
        (function($) {
            var galleryTargetInput = null;
            var galleryPreview = null;
            var galleryFileInput = null;
            var locationBaseUrl = "<?php echo e(url('loan-management/locations'), false); ?>";
            var galleryUrl = "<?php echo e(route('loan-management.locations.asset-gallery'), false); ?>";
            var galleryLoaded = false;

            function setPreview(container, url, altText) {
                if (!container || !container.length) {
                    return;
                }

                container.html(url ? '<img src="' + url + '" alt="' + (altText || 'Preview') + '">' : '');
            }

            $(function() {
                if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#loan_location_table')) {
                    $('#loan_location_table').DataTable({
                        pageLength: parseInt(window.__default_datatable_page_entries || 25, 10),
                        order: [[0, 'asc']],
                        columnDefs: [
                            { targets: [5, 7], orderable: false }
                        ]
                    });
                }
            });

            $(document).on('click', '.loan-location-edit-btn', function() {
                var button = $(this);
                var form = $('#loan_location_edit_form');

                form.attr('action', locationBaseUrl + '/' + button.data('id'));
                form.find('input[name="name"]').val(button.data('name') || '');
                form.find('input[name="location_code"]').val(button.data('location_code') || '');
                form.find('input[name="loan_invoice_prefix"]').val(button.data('loan_invoice_prefix') || '');
                form.find('input[name="phone"]').val(button.data('phone') || '');
                form.find('select[name="status"]').val(button.data('status') || 'active');
                form.find('textarea[name="address"]').val(button.data('address') || '');
                $('#loanLocationEditModalLabel').text('Edit Loan Location: ' + (button.data('name') || ''));
            });

            $(document).on('click', '.loan-location-assets-btn', function() {
                var button = $(this);
                var form = $('#loan_location_assets_form');

                form.attr('action', locationBaseUrl + '/' + button.data('id') + '/assets');
                form.find('input[type="file"]').val('');
                form.find('input[name="logo_existing"]').val(button.data('logo_path') || '');
                form.find('input[name="payment_qr_existing"]').val(button.data('payment_qr_path') || '');
                form.find('input[name="telegram_qr_existing"]').val(button.data('telegram_qr_path') || '');
                form.find('input[name="telegram_payment_chat_id"]').val(button.data('telegram_payment_chat_id') || '');
                form.find('input[name="telegram_installment_chat_id"]').val(button.data('telegram_installment_chat_id') || '');
                form.find('input[name="telegram_notify_payment"]').prop('checked', Number(button.data('telegram_notify_payment')) === 1);
                form.find('input[name="telegram_notify_installment"]').prop('checked', Number(button.data('telegram_notify_installment')) === 1);

                setPreview($('#logo_preview_shared'), button.data('logo_url') || '', 'Logo');
                setPreview($('#payment_qr_preview_shared'), button.data('payment_qr_url') || '', 'Payment QR Code');
                setPreview($('#telegram_qr_preview_shared'), button.data('telegram_qr_url') || '', 'Telegram QR Code');
                $('#loanLocationAssetsModalLabel').text('Location Assets & Telegram: ' + (button.data('name') || ''));
            });

            $(document).on('click', '.loan-asset-gallery-open', function() {
                galleryTargetInput = $('#' + $(this).data('target-input'));
                galleryPreview = $('#' + $(this).data('preview'));
                galleryFileInput = $(this).closest('.loan-asset-field').find('input[type="file"]');
                if (!galleryLoaded) {
                    $('#loanAssetGalleryModalBody').html('<div class="text-center text-muted" style="padding: 24px 0;"><i class="fa fa-spinner fa-spin"></i> Loading image gallery...</div>');
                    $.get(galleryUrl, function(result) {
                        $('#loanAssetGalleryModalBody').html(result);
                        galleryLoaded = true;
                    }).fail(function() {
                        $('#loanAssetGalleryModalBody').html('<div class="alert alert-warning" style="margin-bottom:0;">Unable to load the image gallery right now.</div>');
                    });
                }
                $('#loan_asset_gallery_modal').modal('show');
            });

            $(document).on('click', '.loan-asset-gallery-item', function() {
                var path = $(this).data('path');
                var url = $(this).data('url');

                if (galleryTargetInput && galleryTargetInput.length) {
                    galleryTargetInput.val(path);
                }
                if (galleryPreview && galleryPreview.length) {
                    galleryPreview.html('<img src="' + url + '" alt="Selected image">');
                }
                if (galleryFileInput && galleryFileInput.length) {
                    galleryFileInput.val('');
                }

                $('#loan_asset_gallery_modal').modal('hide');
            });

            $(document).on('change', '.loan-asset-field input[type="file"]', function() {
                $(this).closest('.form-group').find('input[type="hidden"]').val('');
            });
        })(jQuery);
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('loanmanagement::layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/locations/index.blade.php ENDPATH**/ ?>