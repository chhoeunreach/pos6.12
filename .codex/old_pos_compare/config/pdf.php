<?php

return [
    /*
    |--------------------------------------------------------------------------
    | wkhtmltopdf (Snappy / direct process)
    |--------------------------------------------------------------------------
    |
    | Used by app/Services/WkhtmltopdfPdfService.php
    |
    | Important:
    | - `binary` must point to the wkhtmltopdf executable on the server.
    | - `options` are passed as CLI flags.
    */
    'wkhtmltopdf' => [
        'enabled' => (bool) env('WKHTMLTOPDF_ENABLED', true),
        // macOS common paths:
        // - Intel Homebrew: /usr/local/bin/wkhtmltopdf
        // - Apple Silicon Homebrew: /opt/homebrew/bin/wkhtmltopdf
        // Ubuntu/Debian: /usr/bin/wkhtmltopdf
        'binary' => env('WKHTMLTOPDF_BINARY', '/opt/homebrew/bin/wkhtmltopdf'),

        // wkhtmltopdf options. Keys become flags: ['encoding' => 'utf-8'] => --encoding utf-8
        'options' => [
            'encoding' => 'utf-8',
            'page-size' => 'A4',
            'margin-top' => '8mm',
            'margin-right' => '8mm',
            'margin-bottom' => '8mm',
            'margin-left' => '8mm',
            'disable-smart-shrinking' => true,
            'print-media-type' => true,
            'enable-local-file-access' => true,
            'quiet' => true,
        ],
    ],
];
