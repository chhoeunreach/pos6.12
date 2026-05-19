<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessMonitorCommand extends Command
{
    protected $signature = 'pos:process-monitor
        {--interval=1 : Refresh interval in seconds}
        {--once : Show the process list once and exit}
        {--all-php : Show every PHP process, not only artisan processes}';

    protected $description = 'Show running PHP/Artisan processes in real time like a small task manager';

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));

        do {
            $this->clearScreen();

            $rows = $this->getPhpProcesses();
            $allRows = $rows;

            if (! $this->option('all-php')) {
                $rows = array_values(array_filter($rows, function ($row) {
                    return stripos($row['command'], 'artisan') !== false;
                }));
            }

            $this->info('Laravel Process Monitor');
            $this->line('Updated: '.date('Y-m-d H:i:s').' | Refresh: '.$interval.'s | Press Ctrl+C to stop');
            $this->newLine();

            if (empty($rows) && ! empty($allRows) && ! $this->option('all-php')) {
                $rows = $allRows;
                $this->warn('Command line details are unavailable, so showing all PHP processes.');
                $this->newLine();
            }

            if (empty($rows)) {
                $this->warn($this->option('all-php')
                    ? 'No PHP processes are running.'
                    : 'No Artisan processes are running. Use --all-php to show all PHP processes.');
            } else {
                $this->table(
                    ['PID', 'Memory', 'CPU Time', 'Started', 'Command'],
                    array_map(function ($row) {
                        return [
                            $row['pid'],
                            $row['memory'],
                            $row['cpu_time'],
                            $row['started'],
                            $this->shortenCommand($row['command']),
                        ];
                    }, $rows)
                );
            }

            if ($this->option('once')) {
                break;
            }

            sleep($interval);
        } while (true);

        return self::SUCCESS;
    }

    private function getPhpProcesses(): array
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? $this->getWindowsPhpProcesses()
            : $this->getUnixPhpProcesses();
    }

    private function getWindowsPhpProcesses(): array
    {
        $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'php.exe\' } | Select-Object ProcessId,CommandLine,WorkingSetSize,KernelModeTime,UserModeTime,CreationDate | ConvertTo-Json -Depth 2"';
        $output = shell_exec($command);

        if (! is_string($output) || trim($output) === '') {
            return $this->getWindowsTasklistPhpProcesses();
        }

        $decoded = json_decode($output, true);
        if (! is_array($decoded)) {
            return $this->getWindowsTasklistPhpProcesses();
        }

        $processes = isset($decoded['ProcessId']) ? [$decoded] : $decoded;

        return array_map(function ($process) {
            $kernel = (int) ($process['KernelModeTime'] ?? 0);
            $user = (int) ($process['UserModeTime'] ?? 0);

            return [
                'pid' => (string) ($process['ProcessId'] ?? '-'),
                'memory' => $this->formatBytes((int) ($process['WorkingSetSize'] ?? 0)),
                'cpu_time' => $this->formatSeconds((int) (($kernel + $user) / 10000000)),
                'started' => $this->formatWindowsDate((string) ($process['CreationDate'] ?? '')),
                'command' => trim((string) ($process['CommandLine'] ?? 'php.exe')),
            ];
        }, $processes);
    }

    private function getWindowsTasklistPhpProcesses(): array
    {
        $output = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>NUL');

        if (! is_string($output) || trim($output) === '' || stripos($output, 'INFO:') !== false || stripos($output, 'ERROR:') !== false) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($output));
        array_shift($lines);

        $rows = [];
        foreach ($lines as $line) {
            $columns = str_getcsv($line);

            if (count($columns) < 5) {
                continue;
            }

            $rows[] = [
                'pid' => $columns[1],
                'memory' => trim(str_replace(' K', ' KB', $columns[4])),
                'cpu_time' => '-',
                'started' => '-',
                'command' => 'php.exe',
            ];
        }

        return $rows;
    }

    private function getUnixPhpProcesses(): array
    {
        $output = shell_exec('ps -eo pid=,rss=,time=,lstart=,comm=,args= | grep php | grep -v grep');

        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($output)) as $line) {
            if (! preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+(.{24})\s+\S+\s+(.*)$/', $line, $matches)) {
                continue;
            }

            $rows[] = [
                'pid' => $matches[1],
                'memory' => $this->formatBytes(((int) $matches[2]) * 1024),
                'cpu_time' => $matches[3],
                'started' => trim($matches[4]),
                'command' => trim($matches[5]),
            ];
        }

        return $rows;
    }

    private function clearScreen(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
            return;
        }

        system('clear');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 MB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }

    private function formatSeconds(int $seconds): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            floor($seconds / 3600),
            floor(($seconds % 3600) / 60),
            $seconds % 60
        );
    }

    private function formatWindowsDate(string $date): string
    {
        if ($date === '') {
            return '-';
        }

        if (preg_match('/\/Date\((\d+)\)\//', $date, $matches)) {
            return date('Y-m-d H:i:s', (int) floor(((int) $matches[1]) / 1000));
        }

        try {
            return (new \DateTime($date))->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $date;
        }
    }

    private function shortenCommand(string $command): string
    {
        $command = preg_replace('/\s+/', ' ', trim($command));

        if (strlen($command) <= 110) {
            return $command;
        }

        return substr($command, 0, 107).'...';
    }
}
