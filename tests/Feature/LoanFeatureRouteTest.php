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

    public function test_static_create_routes_are_not_shadowed_and_chat_file_and_folder_routes_are_protected(): void
    {
        $router = app('router')->getRoutes();

        $cases = [
            ['GET', '/loan-management/customers/create', 'loan-management.customers.create', 'LoanCustomerController@create'],
            ['GET', '/loan-management/customers/clone-from-pos', 'loan-management.customers.clone-from-pos', 'LoanCustomerController@cloneFromUltimatePos'],
            ['GET', '/loan-management/products/create', 'loan-management.products.create', 'LoanProductController@create'],
            ['GET', '/loan-management/tools/import/12/progress', 'loan-management.import.progress', 'LoanImportExportController@importProgress'],
            ['GET', '/loan-management/chat-api/folders', 'loan-management.chat-api.folders.index', 'ChatFolderController@index'],
            ['POST', '/loan-management/locations/1/telegram-test', 'loan-management.locations.telegram-test', 'LoanLocationController@testTelegram'],
            ['GET', '/loan-management/chat-files/1', 'loan-management.chat-files.show', 'LoanChatController@serveFile'],
        ];

        foreach ($cases as [$method, $uri, $expectedName, $expectedControllerAction]) {
            $route = $router->match(\Illuminate\Http\Request::create($uri, $method));
            $this->assertSame($expectedName, $route->getName(), $uri);
            $this->assertStringEndsWith($expectedControllerAction, $route->getActionName(), $uri);
            $this->assertContains('auth', $route->middleware(), $uri);
        }

        $apiStaffFile = $router->match(\Illuminate\Http\Request::create('/api/loan-management/telegram/chat-files/1', 'GET'));
        $this->assertContains('auth:api', $apiStaffFile->middleware());

        $apiCustomerFile = $router->match(\Illuminate\Http\Request::create('/api/loan-management/customer/telegram/chat-files/1', 'GET'));
        $this->assertContains('auth:customer_loan_api', $apiCustomerFile->middleware());
    }
}
