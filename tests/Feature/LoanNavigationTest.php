<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\LoanManagement\Helpers\LoanMenuHelper;
use Tests\TestCase;

class LoanNavigationTest extends TestCase
{
    private function signInWith(array $permissions, int $id): void
    {
        $user = new class(['id' => $id, 'permissions' => $permissions]) extends GenericUser {
            public function can($ability, $arguments = []): bool { return in_array($ability, $this->permissions, true); }
        };
        $this->app['auth']->setUser($user);
    }

    public function test_sidebar_only_lists_routes_available_to_the_current_user(): void
    {
        $this->signInWith(['loan_management.loans.view'], 701);
        $items = collect(LoanMenuHelper::navigationSections())->pluck('items')->flatten(1);
        $this->assertContains('loan-management.loans', $items->pluck('route')->all());
        $this->assertNotContains('loan-management.settings.business', $items->pluck('route')->all());
        $this->assertNotContains('loan-management.loans.create', $items->pluck('route')->all());
        foreach ($items as $item) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($item['route']));
        }
    }

    public function test_roles_only_user_gets_roles_navigation_and_tabs(): void
    {
        $this->signInWith(['roles.view'], 702);
        $items = collect(LoanMenuHelper::navigationSections())->pluck('items')->flatten(1);
        $this->assertContains('loan-management.roles.index', $items->pluck('route')->all());
        $tabs = LoanMenuHelper::workspaceTabs('users');
        $this->assertSame(['loan-management.roles.index'], array_column($tabs, 'route'));
    }

    public function test_missing_and_unconfigured_workspaces_are_safe(): void
    {
        $this->signInWith(['loan_management.view'], 703);
        $this->assertSame([], LoanMenuHelper::workspaceTabs('missing'));
        foreach (LoanMenuHelper::navigationSections(['overdue' => 7]) as $section) {
            foreach ($section['items'] as $item) {
                if (($item['badge_key'] ?? '') === 'overdue') $this->assertSame(7, $item['badge']);
                $this->assertTrue(\Illuminate\Support\Facades\Route::has($item['route']));
            }
        }
    }

    public function test_active_collection_group_uses_the_page_parameter(): void
    {
        $request = Request::create('/loan-management/collection/overdue-accounts');
        $route = new Route('GET', 'loan-management/collection/{page}', ['as' => 'loan-management.collection.page']);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        $overdue = ['route' => 'loan-management.collection.page', 'active_pages' => ['overdue-accounts', 'delinquent-accounts']];
        $cases = ['route' => 'loan-management.collection.page', 'active_pages' => ['promise-to-pay']];
        $this->assertTrue(LoanMenuHelper::navigationItemActive($overdue));
        $this->assertFalse(LoanMenuHelper::navigationItemActive($cases));
    }
}
