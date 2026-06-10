<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class WkhtmltopdfPdfService
{
    public function isEnabled(): bool
    {
        return (bool) config('pdf.wkhtmltopdf.enabled', true);
    }

    public function binaryPath(): string
    {
        return (string) config('pdf.wkhtmltopdf.binary', '/usr/bin/wkhtmltopdf');
    }

    /**
     * Resolve an executable wkhtmltopdf binary path.
     * Tries configured path first, then common paths, then `which wkhtmltopdf`.
     */
    public function resolveBinaryPath(): string
    {
        $candidates = [];

        $configured = $this->binaryPath();
        if (! empty($configured)) {
            $candidates[] = $configured;
        }

        // Common locations
        $candidates[] = '/usr/bin/wkhtmltopdf';
        $candidates[] = '/usr/local/bin/wkhtmltopdf';
        $candidates[] = '/opt/homebrew/bin/wkhtmltopdf';

        foreach (array_unique($candidates) as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $process = new Process(['which', 'wkhtmltopdf']);
        $process->setTimeout(5);
        $process->run();
        $found = trim($process->getOutput());
        if ($process->isSuccessful() && $found !== '' && is_file($found) && is_executable($found)) {
            return $found;
        }

        return $configured ?: '/usr/bin/wkhtmltopdf';
    }

    /**
     * Render a Blade view into a PDF file using wkhtmltopdf.
     *
     * @param  string  $view  Blade view name
     * @param  array  $data   View data
     * @param  string  $outputPath  Absolute output path to write the PDF
     * @param  array  $options  Override wkhtmltopdf options (flag => value|true)
     */
    public function saveViewToPdf(string $view, array $data, string $outputPath, array $options = []): void
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('wkhtmltopdf is disabled (WKHTMLTOPDF_ENABLED=false).');
        }

        $binary = $this->resolveBinaryPath();
        if (! is_file($binary) || ! is_executable($binary)) {
            throw new \RuntimeException("wkhtmltopdf binary not executable at: {$binary}");
        }

        $dir = dirname($outputPath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $html = view($view, $data)->render();

        // wkhtmltopdf is most reliable when reading from a local HTML file with local assets available
        // in the same folder (fonts/images/css). We'll create a per-render working dir.
        $baseTmpDir = storage_path('app/temp');
        if (! File::exists($baseTmpDir)) {
            File::makeDirectory($baseTmpDir, 0755, true);
        }

        $workDir = $baseTmpDir . DIRECTORY_SEPARATOR . 'wkhtml_' . Str::random(16);
        File::makeDirectory($workDir, 0755, true);

        $tmpHtml = $workDir . DIRECTORY_SEPARATOR . 'index.html';
        File::put($tmpHtml, $html);

        // Copy Khmer fonts to workdir so templates can reference them via relative paths (fonts/*.ttf).
        $fontDir = $workDir . DIRECTORY_SEPARATOR . 'fonts';
        File::makeDirectory($fontDir, 0755, true);
        $fontSources = [
            storage_path('fonts/KhmerOSbattambang.ttf'),
            storage_path('fonts/NotoSansKhmer-Regular.ttf'),
        ];
        foreach ($fontSources as $src) {
            if (File::exists($src)) {
                File::copy($src, $fontDir . DIRECTORY_SEPARATOR . basename($src));
            }
        }

        try {
            $mergedOptions = array_merge((array) config('pdf.wkhtmltopdf.options', []), $options);
            $args = $this->buildArgs($mergedOptions, $tmpHtml, $outputPath);

            $process = new Process($args);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (! File::exists($outputPath) || File::size($outputPath) === 0) {
                throw new \RuntimeException('wkhtmltopdf generated an empty PDF file.');
            }
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    /**
     * Returns wkhtmltopdf --version output for debugging.
     */
    public function versionString(): string
    {
        $binary = $this->resolveBinaryPath();
        if (! is_file($binary) || ! is_executable($binary)) {
            return 'wkhtmltopdf binary not executable at: ' . $binary;
        }

        $process = new Process([$binary, '--version']);
        $process->setTimeout(10);
        $process->run();

        return trim($process->getOutput() ?: $process->getErrorOutput());
    }

    /**
     * Build Process args array for wkhtmltopdf (no shell).
     */
    private function buildArgs(array $options, string $inputHtmlPath, string $outputPdfPath): array
    {
        $args = [$this->resolveBinaryPath()];

        foreach ($options as $flag => $value) {
            $flag = ltrim((string) $flag, '-');
            $args[] = '--' . $flag;

            // Boolean flags: true => pass just --flag
            if ($value === true) {
                continue;
            }
            // false/null => skip
            if ($value === false || $value === null) {
                array_pop($args);
                continue;
            }

            $args[] = (string) $value;
        }

        $args[] = $inputHtmlPath;
        $args[] = $outputPdfPath;

        return $args;
    }
}
