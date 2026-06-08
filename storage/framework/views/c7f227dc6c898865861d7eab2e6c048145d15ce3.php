<?php
    $location = $location ?? null;
?>

<div class="row">
    <div class="col-sm-12">
        <div class="form-group">
            <label>Name:*</label>
            <input type="text" name="name" class="form-control" required placeholder="Name" value="<?php echo e(old('name', $location->name ?? ''), false); ?>">
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Location ID:</label>
            <input type="text" name="location_code" class="form-control" placeholder="Location ID" value="<?php echo e(old('location_code', $location->location_code ?? ''), false); ?>">
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Loan Invoice Prefix:</label>
            <input type="text" name="loan_invoice_prefix" class="form-control" maxlength="50" placeholder="LN" value="<?php echo e(old('loan_invoice_prefix', $location->loan_invoice_prefix ?? ''), false); ?>">
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Phone:</label>
            <input type="text" name="phone" class="form-control" placeholder="Phone" value="<?php echo e(old('phone', $location->phone ?? ''), false); ?>">
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Status:</label>
            <?php $status = old('status', $location->status ?? 'active'); ?>
            <select name="status" class="form-control">
                <option value="active" <?php echo e($status === 'active' ? 'selected' : '', false); ?>>Active</option>
                <option value="inactive" <?php echo e($status === 'inactive' ? 'selected' : '', false); ?>>Inactive</option>
            </select>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-sm-12">
        <div class="form-group">
            <label>Address:</label>
            <textarea name="address" class="form-control" rows="3" placeholder="Address"><?php echo e(old('address', $location->address ?? ''), false); ?></textarea>
        </div>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/locations/partials/form.blade.php ENDPATH**/ ?>