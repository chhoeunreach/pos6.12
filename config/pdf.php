
<?php

return [
    'wkhtmltopdf' => [
        'enabled' => env('WKHTMLTOPDF_ENABLED', true),

        // Ubuntu server path
        'binary' => env('WKHTMLTOPDF_BINARY', '/usr/bin/wkhtmltopdf'),

        'options' => [
            'encoding' => 'utf-8',
            'page-size' => 'A4',
            'margin-top' => '8mm',
            'margin-right' => '8mm',
            'margin-bottom' => '8mm',
            'margin-left' => '8mm',

            'enable-local-file-access' => true,

            // Prevent PDF failure when image/css URL is missing
            'load-error-handling' => 'ignore',
            'load-media-error-handling' => 'ignore',
        ],
    ],
];
