<?php

namespace Modules\LoanManagement\Services\KhmerNationalIdCard;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleVisionOcrService
{
    public function extractText(string $imagePath): string
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new RuntimeException('Google Cloud Vision API key is not configured.');
        }

        try {
            $response = Http::timeout(config('loanmanagement.google_vision.timeout', 30))
                ->retry(2, 300)
                ->post(config('loanmanagement.google_vision.endpoint').'?key='.$apiKey, [
                    'requests' => [
                        [
                            'image' => [
                                'content' => base64_encode(file_get_contents($imagePath)),
                            ],
                            'features' => [
                                [
                                    'type' => 'DOCUMENT_TEXT_DETECTION',
                                    'maxResults' => 1,
                                ],
                            ],
                            'imageContext' => [
                                'languageHints' => ['km', 'en'],
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::error('Google Vision OCR request failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Unable to read the ID card photo. Please try a clearer image.');
        }

        if (! $response->successful()) {
            Log::error('Google Vision OCR returned an error.', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Unable to read the ID card photo. Please try a clearer image.');
        }

        $payload = $response->json();
        $error = data_get($payload, 'responses.0.error.message');

        if (! empty($error)) {
            Log::error('Google Vision OCR response contained an error.', [
                'message' => $error,
            ]);

            throw new RuntimeException('Unable to read the ID card photo. Please try a clearer image.');
        }

        $text = data_get($payload, 'responses.0.fullTextAnnotation.text')
            ?: data_get($payload, 'responses.0.textAnnotations.0.description');

        if (empty(trim((string) $text))) {
            throw new RuntimeException('No text was detected. Please upload a clearer ID card photo.');
        }

        return trim($text);
    }

    private function apiKey(): ?string
    {
        $candidates = [
            config('loanmanagement.google_vision.api_key'),
            env('GOOGLE_CLOUD_VISION_API_KEY'),
            env('GOOGLE_CLOUD_VISION_KEY'),
            env('GOOGLE_VISION_API_KEY'),
            env('GOOGLE_VISION_KEY'),
            env('GOOGLE_API_KEY'),
            $this->envFileValue('GOOGLE_CLOUD_VISION_API_KEY'),
            $this->envFileValue('GOOGLE_CLOUD_VISION_KEY'),
            $this->envFileValue('GOOGLE_VISION_API_KEY'),
            $this->envFileValue('GOOGLE_VISION_KEY'),
            $this->envFileValue('GOOGLE_API_KEY'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function envFileValue(string $key): ?string
    {
        $path = base_path('.env');

        if (! is_readable($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            if (trim($name) !== $key) {
                continue;
            }

            return trim(trim($value), "\"'");
        }

        return null;
    }
}
