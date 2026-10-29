<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanImportRoutesTest extends LoanModuleFeatureTestCase
{
    public function test_import_names_resolve_to_the_existing_protected_upload_handler(): void
    {
        foreach (['loan-management.import.upload', 'loan-management.import.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame('Modules\\LoanManagement\\Http\\Controllers\\LoanImportExportController@import', $route->getActionName());
            $this->assertContains('POST', $route->methods());
            $this->assertContains('auth', $route->middleware());
            $this->assertContains('loan.permission:loan_management.import.view', $route->middleware());
            $this->assertNotEmpty(route($name));
        }
    }

    public function test_import_export_page_renders_all_form_and_async_routes(): void
    {
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Cache::put('loan_management.sidebar_badges', [], 600);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        auth()->setUser(new class(['id' => 97001, 'username' => 'Import Staff']) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function can($ability, $arguments = []): bool { return false; }
        });
        $html = view('loanmanagement::tools.import_export', [
            'type' => 'loans', 'importTypes' => ['loans' => ['label' => 'Loans']],
            'exportTypes' => ['loans' => ['label' => 'Loans']], 'templateDetails' => [],
            'recentBatches' => collect(), 'recentExports' => collect(),
        ])->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $form = $document->getElementById('loan_import_form');
        $this->assertNotNull($form);
        $this->assertSame(route('loan-management.import.upload'), $form->getAttribute('action'));
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));
        $this->assertStringContainsString(route('loan-management.import.start'), $html);
        $this->assertStringContainsString(route('loan-management.import.process'), $html);
        $this->assertStringContainsString(route('loan-management.export.download'), $html);
    }
}
