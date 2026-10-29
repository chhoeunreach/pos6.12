<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoanFeatureRouteTest extends TestCase
{
    public function test_telegram_settings_link_redirects_to_the_existing_notifications_tab(): void
    {
        $route = Route::getRoutes()->getByName('loan-management.settings.telegram');
        $this->assertNotNull($route);
        $this->assertSame('/loan-management/settings/telegram', route('loan-management.settings.telegram', [], false));
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('loan.permission:loan_management.settings.view|loan_management.setting|loan_management.view', $route->middleware());
        $response = ($route->getAction('uses'))();
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('loan-management.settings.business').'#tab-notifications', $response->getTargetUrl());
    }

    public function test_feature_menu_routes_have_working_controller_actions_and_authentication(): void
    {
        foreach ([
            'quotations.index', 'quotations.create', 'quotations.store', 'quotations.preview-schedule',
            'quotations.edit', 'quotations.update', 'quotations.show', 'quotations.print', 'quotations.convert',
            'quotations.duplicate', 'quotations.status', 'quotations.destroy', 'loans.contract',
            'loans.settlement.modal', 'loans.settlement.process', 'loans.reschedule.modal',
            'loans.reschedule.process', 'loans.ptp.modal', 'loans.ptp.store', 'payments.receipt',
            'reports.portfolio-at-risk', 'reports.cbc-export', 'settings.business.stamp', 'guarantors.index',
        ] as $name) {
            $route = Route::getRoutes()->getByName('loan-management.'.$name);
            $this->assertNotNull($route, $name);
            [$controller, $action] = explode('@', $route->getActionName());
            $this->assertTrue(method_exists($controller, $action), $name);
            $this->assertContains('auth', $route->middleware(), $name);
            $this->assertTrue(collect($route->middleware())->contains(fn ($middleware) => str_starts_with($middleware, 'loan.permission:')), $name);
        }
        $this->assertTrue(view()->exists('loanmanagement::quotations.index'));
        $this->assertTrue(view()->exists('loanmanagement::reports.portfolio_at_risk'));
        $this->assertTrue(view()->exists('loanmanagement::reports.cbc_export'));
        $this->assertTrue(view()->exists('loanmanagement::payments.print_receipt'));
    }
}
