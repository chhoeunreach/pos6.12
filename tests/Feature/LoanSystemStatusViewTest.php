<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanSystemStatusViewTest extends LoanModuleFeatureTestCase
{
    public function test_health_reports_render_inside_the_module_workspace(): void
    {
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Cache::put('loan_management.sidebar_badges', [], 600);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        auth()->setUser(new class(['id' => 97002, 'username' => 'System Staff']) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function can($ability, $arguments = []): bool { return false; }
        });
        foreach ([false, true] as $critical) {
            $report = [
                'checked_at' => '2026-10-29 12:00:00', 'status' => $critical ? 'critical' : 'healthy',
                'score' => $critical ? 50 : 100, 'has_critical_errors' => $critical,
                'warning_count' => 0, 'passed_count' => 1, 'error_count' => $critical ? 1 : 0,
                'alerts' => $critical ? [[
                    'title_en' => 'Loan database unavailable', 'title_km' => '',
                    'message_en' => 'Connection check failed.', 'remedy' => 'Check database configuration.',
                ]] : [],
                'server_info' => [
                    'php_version' => PHP_VERSION, 'laravel_version' => app()->version(), 'os' => PHP_OS,
                    'memory_limit' => '256M', 'max_execution_time' => '60', 'upload_max_filesize' => '10M',
                    'timezone' => 'Asia/Bangkok',
                ],
                'categories' => ['database' => ['name' => 'Database Checks', 'status' => $critical ? 'fail' : 'pass', 'items' => [[
                    'name' => 'Loan database', 'required' => 'Connected', 'current' => $critical ? 'Unavailable' : 'Connected',
                    'status' => $critical ? 'fail' : 'pass',
                ]]]],
            ];
            $html = view('loanmanagement::system.status', compact('report'))->render();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $panel = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " lm-workspace ")]//*[contains(concat(" ", normalize-space(@class), " "), " sys-health-wrapper ")]');
            $this->assertSame(1, $panel->length);
            $this->assertStringContainsString('Server & Database Requirements Check', $panel->item(0)->textContent);
            $this->assertStringContainsString('Database Checks', $panel->item(0)->textContent);
            $this->assertStringContainsString($critical ? 'Connection check failed.' : 'All Requirements Satisfied', $panel->item(0)->textContent);
        }
    }
}
