<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabaseToTelegram extends Command
{
    protected $signature = 'backup:database-telegram';

    protected $description = 'Backup the POS MySQL database and send it to Telegram';

    private const PART_SIZE_MB = 45;

    public function handle(TelegramBotService $telegram): int
    {
        $database = (string) config('database.connections.mysql.database');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (string) config('database.connections.mysql.port', '3306');

        $chatId = '-930580993';

        if ($database === '' || $username === '') {
            $this->error('Database configuration is missing.');
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backup-temp');

        if (! is_dir($backupDir) &&
            ! mkdir($backupDir, 0775, true) &&
            ! is_dir($backupDir)) {
            $this->error('Unable to create backup directory.');
            return self::FAILURE;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        $sqlFile = $backupDir . "/pos_database_{$timestamp}.sql";
        $gzipFile = $sqlFile . '.gz';

        $createdFiles = [];

        try {
            $this->info('Creating POS database backup...');

            $process = new Process([
                'mysqldump',
                '--host=' . $host,
                '--port=' . $port,
                '--user=' . $username,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--result-file=' . $sqlFile,
                $database,
            ]);

            $process->setTimeout(1200);

            $process->setEnv(array_merge($_ENV, [
                'MYSQL_PWD' => $password,
            ]));

            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(
                    'mysqldump failed: ' . trim($process->getErrorOutput())
                );
            }

            if (! is_file($sqlFile) || filesize($sqlFile) === 0) {
                throw new \RuntimeException(
                    'Database dump is empty or was not created.'
                );
            }

            $this->info('Compressing database...');

            $gzip = new Process([
                'gzip',
                '-f',
                $sqlFile,
            ]);

            $gzip->setTimeout(600);
            $gzip->run();

            if (! $gzip->isSuccessful()) {
                throw new \RuntimeException(
                    'gzip failed: ' . trim($gzip->getErrorOutput())
                );
            }

            if (! is_file($gzipFile) || filesize($gzipFile) === 0) {
                throw new \RuntimeException(
                    'Compressed backup was not created.'
                );
            }

            $createdFiles[] = $gzipFile;

            $sizeMb = round(filesize($gzipFile) / 1024 / 1024, 2);

            $this->info("Backup created: {$sizeMb} MB");

            if ($sizeMb <= self::PART_SIZE_MB) {
                $this->info('Sending POS database to Telegram...');

                $caption =
                    "KNEAYERNG POS Database Backup\n" .
                    "Database: {$database}\n" .
                    "Date: " . now()->format('Y-m-d H:i:s') . "\n" .
                    "Size: {$sizeMb} MB";

                $telegram->sendDocumentToChat(
                    $chatId,
                    $gzipFile,
                    $caption,
                    basename($gzipFile)
                );

                $this->info('POS database sent to Telegram successfully.');
            } else {
                $this->warn(
                    "Backup is larger than " . self::PART_SIZE_MB . " MB."
                );

                $this->info('Splitting backup into Telegram-safe parts...');

                $partPrefix = $gzipFile . '.part';

                $split = new Process([
                    'split',
                    '-b',
                    self::PART_SIZE_MB . 'M',
                    '-d',
                    '-a',
                    '3',
                    $gzipFile,
                    $partPrefix,
                ]);

                $split->setTimeout(600);
                $split->run();

                if (! $split->isSuccessful()) {
                    throw new \RuntimeException(
                        'split failed: ' . trim($split->getErrorOutput())
                    );
                }

                $parts = glob($partPrefix . '*');

                if ($parts === false || count($parts) === 0) {
                    throw new \RuntimeException(
                        'Backup splitting failed: no parts created.'
                    );
                }

                sort($parts, SORT_NATURAL);

                foreach ($parts as $part) {
                    $createdFiles[] = $part;
                }

                $totalParts = count($parts);

                $this->info("Backup split into {$totalParts} part(s).");

                foreach ($parts as $index => $part) {
                    $partNumber = $index + 1;
                    $partSizeMb = round(filesize($part) / 1024 / 1024, 2);

                    $this->info(
                        "Sending part {$partNumber}/{$totalParts} ({$partSizeMb} MB)..."
                    );

                    $caption =
                        "KNEAYERNG POS Database Backup\n" .
                        "Database: {$database}\n" .
                        "Date: " . now()->format('Y-m-d H:i:s') . "\n" .
                        "Original Size: {$sizeMb} MB\n" .
                        "Part: {$partNumber}/{$totalParts}\n" .
                        "Part Size: {$partSizeMb} MB\n\n" .
                        "Restore: combine all parts before extracting.";

                    $telegram->sendDocumentToChat(
                        $chatId,
                        $part,
                        $caption,
                        basename($part)
                    );

                    $this->info(
                        "Part {$partNumber}/{$totalParts} sent successfully."
                    );
                }

                $this->info(
                    'All POS database backup parts sent successfully.'
                );
            }

            Log::info('POS database backup sent to Telegram.', [
                'database' => $database,
                'file' => basename($gzipFile),
                'size_mb' => $sizeMb,
            ]);

            foreach (array_unique($createdFiles) as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            $this->info('Temporary backup files removed.');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());

            Log::error('POS Telegram database backup failed.', [
                'error' => $e->getMessage(),
            ]);

            $this->warn(
                'Backup files were kept because the process failed.'
            );

            return self::FAILURE;
        }
    }
}
