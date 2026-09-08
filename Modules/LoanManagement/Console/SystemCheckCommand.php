<?php

namespace Modules\LoanManagement\Console;

use Illuminate\Console\Command;
use Modules\LoanManagement\Services\SystemHealthCheckService;

class SystemCheckCommand extends Command
{
    protected $signature = 'loan-management:check {--json : Output report as raw JSON}';

    protected $description = 'Check full server environment, PHP extensions, file permissions, database connectivity, and seed status';

    public function handle(): int
    {
        $this->info('===========================================================');
        $this->info('  Loan Management System & Database Health Diagnostics');
        $this->info('===========================================================');

        $report = SystemHealthCheckService::check();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return $report['has_critical_errors'] ? self::FAILURE : self::SUCCESS;
        }

        $serverInfo = $report['server_info'];
        $this->table(
            ['Server Parameter', 'Value'],
            [
                ['PHP Version', $serverInfo['php_version']],
                ['Laravel Version', $serverInfo['laravel_version']],
                ['Operating System', $serverInfo['os']],
                ['Memory Limit', $serverInfo['memory_limit']],
                ['Max Execution Time', $serverInfo['max_execution_time']],
                ['Upload Max Filesize', $serverInfo['upload_max_filesize']],
                ['Post Max Size', $serverInfo['post_max_size']],
                ['Timezone', $serverInfo['timezone']],
            ]
        );

        $this->newLine();

        foreach ($report['categories'] as $catKey => $category) {
            $this->info("=== Category: {$category['name']} ===");
            $rows = [];
            foreach ($category['items'] as $item) {
                $statusTag = match ($item['status']) {
                    'pass' => '<info>[ PASS ]</info>',
                    'warning' => '<comment>[ WARN ]</comment>',
                    'fail' => '<error>[ FAIL ]</error>',
                    default => '[ ? ]',
                };
                $rows[] = [
                    $item['name'],
                    $item['required'] ?? '-',
                    $item['current'] ?? '-',
                    $statusTag,
                ];
            }
            $this->table(['Check Name', 'Requirement', 'Current State', 'Status'], $rows);
            $this->newLine();
        }

        $this->info('-----------------------------------------------------------');
        $this->info(sprintf(
            'Summary: Score %d%% | Total Checks: %d | Passed: %d | Warnings: %d | Errors: %d',
            $report['score'],
            $report['total_checks'],
            $report['passed_count'],
            $report['warning_count'],
            $report['error_count']
        ));
        $this->info('-----------------------------------------------------------');

        if ($report['has_critical_errors'] || $report['warning_count'] > 0) {
            $this->warn('Actions Required:');
            foreach ($report['alerts'] as $index => $alert) {
                $prefix = $alert['severity'] === 'danger' ? '<error>[ERROR]</error>' : '<comment>[WARN]</comment>';
                $this->line(sprintf(' %d. %s %s: %s', $index + 1, $prefix, $alert['title_en'], $alert['message_en']));
                if (! empty($alert['remedy'])) {
                    $this->line("    => Fix: {$alert['remedy']}");
                }
            }
            $this->newLine();
        } else {
            $this->info('All server and database requirements are satisfied! (100% HEALTHY)');
        }

        return $report['has_critical_errors'] ? self::FAILURE : self::SUCCESS;
    }
}
