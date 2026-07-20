<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        $candidates[] = 'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe';
        $candidates[] = 'C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltopdf.exe';
        $candidates[] = 'C:\wkhtmltopdf\bin\wkhtmltopdf.exe';

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

    public function resolveImageBinaryPath(): string
    {
        $pdfBinary = $this->resolveBinaryPath();
        $candidates = [];

        if ($pdfBinary !== '') {
            $candidates[] = str_replace('wkhtmltopdf', 'wkhtmltoimage', $pdfBinary);
        }

        $candidates[] = '/usr/bin/wkhtmltoimage';
        $candidates[] = '/usr/local/bin/wkhtmltoimage';
        $candidates[] = '/opt/homebrew/bin/wkhtmltoimage';
        $candidates[] = 'C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe';
        $candidates[] = 'C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltoimage.exe';
        $candidates[] = 'C:\wkhtmltopdf\bin\wkhtmltoimage.exe';

        foreach (array_unique($candidates) as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $process = new Process(['which', 'wkhtmltoimage']);
        $process->setTimeout(5);
        $process->run();
        $found = trim($process->getOutput());
        if ($process->isSuccessful() && $found !== '' && is_file($found) && is_executable($found)) {
            return $found;
        }

        return $candidates[0] ?? '/usr/bin/wkhtmltoimage';
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
        $this->saveHtmlToPdf(view($view, $data)->render(), $outputPath, $options);
    }

    /**
     * Render raw HTML into a PDF file using wkhtmltopdf, with mPDF fallback.
     */
    public function saveHtmlToPdf(string $html, string $outputPath, array $options = []): void
    {
        $dir = dirname($outputPath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (! $this->isEnabled()) {
            $this->saveHtmlWithMpdf($html, $outputPath, $options);

            return;
        }

        $binary = $this->resolveBinaryPath();
        if (! is_file($binary) || ! is_executable($binary)) {
            $this->saveHtmlWithMpdf($html, $outputPath, $options);

            return;
        }

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
        foreach ($this->fontSourcePaths() as $src) {
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
                $this->saveHtmlWithMpdf($html, $outputPath, $options);

                return;
            }

            if (! File::exists($outputPath) || File::size($outputPath) === 0) {
                $this->saveHtmlWithMpdf($html, $outputPath, $options);
            }
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    public function saveHtmlToImage(string $html, string $outputPath, array $options = []): void
    {
        $dir = dirname($outputPath);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $binary = $this->resolveImageBinaryPath();
        if (! is_file($binary) || ! is_executable($binary)) {
            throw new \RuntimeException('wkhtmltoimage binary not executable at: '.$binary);
        }

        $baseTmpDir = storage_path('app/temp');
        if (! File::exists($baseTmpDir)) {
            File::makeDirectory($baseTmpDir, 0755, true);
        }

        $workDir = $baseTmpDir . DIRECTORY_SEPARATOR . 'wkhtml_img_' . Str::random(16);
        File::makeDirectory($workDir, 0755, true);

        $tmpHtml = $workDir . DIRECTORY_SEPARATOR . 'index.html';
        File::put($tmpHtml, $html);

        $fontDir = $workDir . DIRECTORY_SEPARATOR . 'fonts';
        File::makeDirectory($fontDir, 0755, true);
        foreach ($this->fontSourcePaths() as $src) {
            if (File::exists($src)) {
                File::copy($src, $fontDir . DIRECTORY_SEPARATOR . basename($src));
            }
        }

        try {
            $mergedOptions = array_merge([
                'encoding' => 'utf-8',
                'format' => 'png',
                'quality' => '92',
                'width' => '1240',
                'enable-local-file-access' => true,
                'load-error-handling' => 'ignore',
                'load-media-error-handling' => 'ignore',
                'quiet' => true,
            ], $options);

            $args = [$binary];
            foreach ($mergedOptions as $flag => $value) {
                $flag = ltrim((string) $flag, '-');
                $args[] = '--'.$flag;
                if ($value === true) {
                    continue;
                }
                if ($value === false || $value === null) {
                    array_pop($args);
                    continue;
                }
                $args[] = (string) $value;
            }
            $args[] = $tmpHtml;
            $args[] = $outputPath;

            $process = new Process($args);
            $process->setTimeout(90);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'wkhtmltoimage failed.');
            }
            if (! File::exists($outputPath) || File::size($outputPath) === 0) {
                throw new \RuntimeException('wkhtmltoimage generated an empty image.');
            }
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    /**
     * Fallback PDF renderer used when wkhtmltopdf is not available.
     */
    private function saveHtmlWithMpdf(string $html, string $outputPath, array $options = []): void
    {
        $previousBacktrackLimit = ini_get('pcre.backtrack_limit');
        $previousRecursionLimit = ini_get('pcre.recursion_limit');
        $previousMemoryLimit = ini_get('memory_limit');
        $previousTimeLimit = ini_get('max_execution_time');
        ini_set('pcre.backtrack_limit', (string) max((int) $previousBacktrackLimit, 50000000));
        ini_set('pcre.recursion_limit', (string) max((int) $previousRecursionLimit, 50000000));
        ini_set('memory_limit', $this->largerMemoryLimit($previousMemoryLimit, '512M'));
        @set_time_limit(max((int) $previousTimeLimit, 180));

        $tempDir = storage_path('app/temp/mpdf');
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        try {
            $format = strtoupper((string) ($options['page-size'] ?? $options['format'] ?? 'A4'));

            $config = [
                'tempDir' => $tempDir,
                'mode' => 'utf-8',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'autoVietnamese' => true,
                'autoArabic' => true,
                'margin_top' => 8,
                'margin_right' => 8,
                'margin_bottom' => 8,
                'margin_left' => 8,
                'format' => $format ?: 'A4',
            ];

            $fontDirs = $this->existingFontDirectories();
            if (! empty($fontDirs)) {
                $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
                $fontDirs = array_values(array_unique(array_merge($defaultConfig['fontDir'], $fontDirs)));

                $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                if ($this->fontFileExists('KhmerOSbattambang.ttf')) {
                    $fontData['khmerosbattambang'] = [
                        'R' => 'KhmerOSbattambang.ttf',
                        'useOTL' => 0xFF,
                    ];
                    $config['default_font'] = 'khmerosbattambang';
                } elseif ($this->fontFileExists('NotoSansKhmer-Regular.ttf')) {
                    $fontData['notosanskhmer'] = [
                        'R' => 'NotoSansKhmer-Regular.ttf',
                        'useOTL' => 0xFF,
                    ];
                    $config['default_font'] = 'notosanskhmer';
                }

                $config['fontDir'] = $fontDirs;
                $config['fontdata'] = $fontData;
            }

            $mpdf = new \Mpdf\Mpdf($config);
            $mpdf->useSubstitutions = true;
            if (! empty($config['default_font'])) {
                $fontCss = '<style>html, body, * { font-family: ' . $config['default_font'] . ', sans-serif !important; }</style>';
                if (stripos($html, '</head>') !== false) {
                    $html = preg_replace('/<\/head>/i', $fontCss . '</head>', $html, 1);
                } else {
                    $html = $fontCss . $html;
                }
            }
            $mpdf->WriteHTML($html);
            $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

            if (! File::exists($outputPath) || File::size($outputPath) === 0) {
                throw new \RuntimeException('mPDF generated an empty PDF file.');
            }
        } finally {
            if ($previousBacktrackLimit !== false) {
                ini_set('pcre.backtrack_limit', (string) $previousBacktrackLimit);
            }
            if ($previousRecursionLimit !== false) {
                ini_set('pcre.recursion_limit', (string) $previousRecursionLimit);
            }
            if ($previousMemoryLimit !== false) {
                ini_set('memory_limit', (string) $previousMemoryLimit);
            }
            if ($previousTimeLimit !== false) {
                @set_time_limit((int) $previousTimeLimit);
            }
        }
    }

    private function largerMemoryLimit($current, string $minimum): string
    {
        if ($current === false || $current === '' || $current === '-1') {
            return $current === '-1' ? '-1' : $minimum;
        }

        return $this->memoryToBytes((string) $current) >= $this->memoryToBytes($minimum)
            ? (string) $current
            : $minimum;
    }

    private function memoryToBytes(string $value): int
    {
        $value = trim($value);
        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
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

    private function fontSourcePaths(): array
    {
        $paths = [];
        foreach ($this->existingFontDirectories() as $dir) {
            foreach (['KhmerOSbattambang.ttf', 'NotoSansKhmer-Regular.ttf'] as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (File::exists($path)) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function existingFontDirectories(): array
    {
        $dirs = [
            public_path('fonts/khmer'),
            storage_path('fonts'),
            'C:' . DIRECTORY_SEPARATOR . 'Windows' . DIRECTORY_SEPARATOR . 'Fonts',
        ];

        return array_values(array_filter($dirs, function ($dir) {
            return File::exists($dir) && is_dir($dir);
        }));
    }

    private function fontFileExists(string $file): bool
    {
        foreach ($this->existingFontDirectories() as $dir) {
            if (File::exists($dir . DIRECTORY_SEPARATOR . $file)) {
                return true;
            }
        }

        return false;
    }
}
