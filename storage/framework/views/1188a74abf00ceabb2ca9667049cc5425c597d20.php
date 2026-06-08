<?php
    $loanUser = auth()->user();
    $locationName = null;
    $headerBadgeCounts = $loanBadgeCounts ?? \Modules\LoanManagement\Helpers\LoanMenuHelper::badgeCounts();
    $unreadChatCount = (int) ($headerBadgeCounts['unread_chat'] ?? 0);
    $pendingVisitCount = (int) ($headerBadgeCounts['pending_visits'] ?? 0);
    $overdueCount = (int) ($headerBadgeCounts['overdue'] ?? 0);
    $notificationCount = $unreadChatCount + $pendingVisitCount + $overdueCount;

    try {
        $locationName = session('user.business_location_name')
            ?? session('business.name')
            ?? optional(session('user'))->business_location_name;
    } catch (\Throwable $e) {
        $locationName = null;
    }

    $backToPosUrl = Route::has('products.index') ? route('products.index') : url('/products');
?>

<header class="lm-header sticky-top" id="loanManagementHeader">
    <div class="lm-header-left">
        <button type="button" class="lm-sidebar-toggle" id="loanSidebarToggle" aria-label="Toggle sidebar">
            <i class="fa fa-bars"></i>
        </button>
        <div>
            <h1 class="lm-title">Loan Management</h1>
            <p class="lm-subtitle">Dedicated loan operation workspace</p>
        </div>
    </div>

    <div class="lm-header-right">
        <?php if(Route::has('loan-management.loans.calculator') && loan_user_can('loan_management.create_from_sell|loan_management.loans.create|loan_management.create')): ?>
            <a href="<?php echo e(route('loan-management.loans.calculator', ['_lm_modal' => 1]), false); ?>"
               class="btn btn-default btn-sm lm-header-action js-loan-calculator-modal"
               data-title="Loan Calculator">
                <i class="fa fa-calculator"></i> Loan Calculator
            </a>
        <?php endif; ?>

        <?php if(Route::has('loan-management.chat.index') && loan_user_can('loan_management.chat.view')): ?>
            <a href="<?php echo e(route('loan-management.chat.index'), false); ?>" class="btn btn-default btn-sm lm-header-action">
                <i class="fa fa-comments"></i> Chat
                <?php if($unreadChatCount > 0): ?>
                    <span class="lm-badge lm-header-badge"><?php echo e($unreadChatCount, false); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if(Route::has('loan-management.communication.page') && loan_user_can('loan_management.view')): ?>
            <a href="<?php echo e(route('loan-management.communication.page', ['page' => 'notifications']), false); ?>" class="btn btn-default btn-sm lm-header-action">
                <i class="fa fa-bell"></i> Notifications
                <?php if($notificationCount > 0): ?>
                    <span class="lm-badge lm-header-badge"><?php echo e($notificationCount, false); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <div class="lm-user-meta">
            <span class="lm-user-name"><?php echo e($loanUser->username ?? $loanUser->first_name ?? 'Staff', false); ?></span>
            <?php if(!empty($locationName)): ?>
                <span class="lm-location"><?php echo e($locationName, false); ?></span>
            <?php endif; ?>
        </div>

        <a href="<?php echo e($backToPosUrl, false); ?>" class="btn btn-primary btn-sm lm-btn-back">
            <i class="fa fa-arrow-left"></i> Back to Main
        </a>

        <?php if(Route::has('logout')): ?>
            <a href="<?php echo e(route('logout'), false); ?>" class="btn btn-default btn-sm"
               onclick="event.preventDefault(); document.getElementById('loanLogoutForm').submit();">
                <i class="fa fa-sign-out"></i> Logout
            </a>
            <form id="loanLogoutForm" action="<?php echo e(route('logout'), false); ?>" method="POST" style="display:none;">
                <?php echo csrf_field(); ?>
            </form>
        <?php endif; ?>
    </div>
</header>
<?php /**PATH C:\xampp\htdocs\apply like facebook\pos6.12\Modules\LoanManagement\Providers/../Resources/views/layouts/header.blade.php ENDPATH**/ ?>